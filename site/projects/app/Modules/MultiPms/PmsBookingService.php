<?php

namespace App\Modules\MultiPms;

use App\Modules\MultiPms\Models\PmsRoom;
use App\Models\BookingRequest;
use App\Modules\MultiPms\Pms\PmsManager;
use App\Modules\MultiPms\Helper\QuoteNormalizer;
use Illuminate\Support\Facades\Log;

/**
 * PmsBookingService — the single place where a booking is sent to the correct PMS.
 *
 * Responsibilities:
 *   1. Re-fetch the live quote from the PMS immediately before creating the reservation
 *      so the amount is always authoritative.
 *   2. Re-check availability to prevent double-booking.
 *   3. Call the correct adapter's createReservation() based on pms_source.
 *   4. Persist the PMS reservation ID and response to the BookingRequest record.
 *   5. Never mutate payment state — payment is handled by the caller.
 *
 * Usage:
 *   $service = app(PmsBookingService::class);
 *   $result  = $service->create($bookingRequest);
 *   if ($result['ok']) { ... $result['pms_booking_id'] ... }
 */
class PmsBookingService
{
    public function __construct(protected PmsManager $pms) {}

    // ── Public API ─────────────────────────────────────────────────────────────

    /**
     * Create a reservation in the correct PMS for the given BookingRequest.
     *
     * Returns:
     *   ['ok' => true,  'pms_booking_id' => '...', 'response' => [...]]
     *   ['ok' => false, 'error' => '...']
     */
    public function create(BookingRequest $booking): array
    {
        $room = PmsRoom::find($booking->property_id);
        if (!$room) {
            return $this->fail('Property not found');
        }
        if (empty($room->pms_source)) {
            return $this->fail('No PMS configured for this property');
        }

        // ── Step 0: Validate guest count against property capacity ───────────
        $maxPeople = (int)($room->max_people ?? 0);
        $totalGuests = (int)($booking->adults ?? 0) + (int)($booking->child ?? 0);
        if ($maxPeople > 0 && $totalGuests > $maxPeople) {
            return $this->fail("This property accommodates a maximum of {$maxPeople} guests. Your booking is for {$totalGuests}.");
        }

        // ── Step 1: Re-verify availability + minimum stay ─────────────────────
        $nights     = (new \DateTime($booking->checkin))->diff(new \DateTime($booking->checkout))->days;
        $availCheck = $this->checkAvailability($room, $booking->checkin, $booking->checkout, $nights);
        if (!$availCheck['available']) {
            return $this->fail($availCheck['reason'] ?? 'Selected dates are no longer available.');
        }

        // ── Step 2: Re-fetch authoritative quote ─────────────────────────────
        $quoteCheck = $this->refetchQuote($room, $booking);
        if (!$quoteCheck['ok']) {
            return $this->fail('Could not verify quote: ' . ($quoteCheck['error'] ?? ''));
        }

        // Warn (log) if total changed since the guest saw it — but don't block for small diffs
        $quotedTotal  = (float)$booking->total_amount;
        $freshTotal   = (float)$quoteCheck['total'];
        if (abs($quotedTotal - $freshTotal) > 0.50) {
            Log::channel('lodgify')->warning('PmsBookingService: quote total changed', [
                'booking_id'    => $booking->id,
                'pms'           => $room->pms_source,
                'quoted_total'  => $quotedTotal,
                'fresh_total'   => $freshTotal,
            ]);
            // Update the booking with the fresh total before proceeding
            $booking->update(['total_amount' => $freshTotal]);
            $booking->refresh();
        }

        // ── Step 3: Create reservation in PMS ────────────────────────────────
        $result = $this->createInPms($room, $booking, $quoteCheck['raw_quote'] ?? null);

        // Attach the normalized quote so callers (e.g. StripeController) can read the
        // installment schedule without having to re-fetch it.
        if ($result['ok']) {
            $result['normalized_quote'] = $quoteCheck['normalized_quote'] ?? null;
        }

        return $result;
    }

    /**
     * Idempotency check — if the booking already has a PMS reservation ID, return it.
     * Call this before create() to avoid duplicate reservations on retry.
     */
    public function alreadyCreated(BookingRequest $booking): bool
    {
        return !empty($booking->pms_booking_id);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function checkAvailability(PmsRoom $room, string $checkin, string $checkout, int $nights = 0): array
    {
        try {
            $adapter = $this->pms->driver($room->pms_source);
            $raw = $adapter->getAvailability([
                'property_id' => $room->pms_property_id,
                'room_id'     => $room->pms_room_id,
                'checkin'     => $checkin,
                'checkout'    => $checkout,
                'adults'      => 1,
            ]);

            $days = $raw['data']['result'] ?? $raw['data'] ?? [];
            $maxMinStay = 0;

            foreach ((array)$days as $day) {
                $date = $day['date'] ?? '';
                if ($date < $checkin || $date >= $checkout) continue;

                // Blocked date check
                $avail  = $day['available'] ?? $day['isAvailable'] ?? true;
                $status = strtolower($day['status'] ?? 'available');
                if (!$avail || $status === 'unavailable' || $status === 'booked') {
                    return ['available' => false, 'reason' => "The date {$date} is no longer available."];
                }

                // Track the highest minimum stay required across all dates in range
                $ms = (int)($day['minimumStay'] ?? $day['minNights'] ?? $day['min_nights'] ?? 0);
                if ($ms > $maxMinStay) $maxMinStay = $ms;
            }

            // Also respect the property's custom_min_stay override
            $localMin = (int)($room->custom_min_stay ?? 0);
            $effectiveMin = max($maxMinStay, $localMin);

            if ($nights > 0 && $effectiveMin > 0 && $nights < $effectiveMin) {
                return [
                    'available' => false,
                    'reason'    => "A minimum stay of {$effectiveMin} nights is required for the selected dates. Your stay is {$nights} night" . ($nights > 1 ? 's' : '') . ".",
                ];
            }

            return ['available' => true];
        } catch (\Throwable $e) {
            Log::channel('lodgify')->warning('PmsBookingService: availability check failed', [
                'pms'   => $room->pms_source,
                'error' => $e->getMessage(),
            ]);
            return ['available' => true]; // optimistic fallback — don't block on API error
        }
    }

    private function refetchQuote(PmsRoom $room, BookingRequest $booking): array
    {
        try {
            $adapter = $this->pms->driver($room->pms_source);

            $input = [
                'property_id' => $room->pms_property_id,
                'room_id'     => $room->pms_room_id,
                'checkin'     => $booking->checkin,
                'checkout'    => $booking->checkout,
                'adults'      => (int)($booking->total_guests ?? 1),
                'children'    => 0,
                'pets'        => (int)($booking->total_pets ?? 0),
                'promo_code'  => $booking->discount_coupon ?? '', // re-apply promo so totals stay consistent
            ];

            $raw   = $adapter->getRawQuote($input);
            $quote = QuoteNormalizer::normalize($raw, $room->pms_source, $input);

            if (!$quote['ok']) {
                return ['ok' => false, 'error' => $quote['error'] ?? 'Quote failed'];
            }

            return ['ok' => true, 'total' => $quote['total'], 'raw_quote' => $raw, 'normalized_quote' => $quote];

        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    private function createInPms(PmsRoom $room, BookingRequest $booking, ?array $rawQuote): array
    {
        $input = [
            'property_id'  => $room->pms_property_id,
            'room_id'      => $room->pms_room_id,
            'checkin'      => $booking->checkin,
            'checkout'     => $booking->checkout,
            'adults'       => (int)($booking->total_guests ?? 1),
            'children'     => 0,
            'pets'         => (int)($booking->total_pets ?? 0),
            'first_name'   => $booking->first_name ?? explode(' ', $booking->name ?? '')[0] ?? '',
            'last_name'    => $booking->last_name  ?? (explode(' ', $booking->name ?? '')[1] ?? ''),
            'email'        => $booking->email    ?? '',
            'phone'        => $booking->mobile   ?? $booking->phone ?? '',
            'total_amount' => $booking->total_amount,
            'raw_quote'    => $rawQuote,  // Hostaway needs financeField[] from priceDetails
        ];

        try {
            $adapter  = $this->pms->driver($room->pms_source);
            $response = $adapter->createReservation($input);

            $httpStatus = $response['status'] ?? 0;

            if ($httpStatus === 200 || $httpStatus === 201) {
                $pmsBookingId = $this->extractBookingId($response, $room->pms_source);

                // Persist to BookingRequest
                $booking->update([
                    'pms_booking_id'          => $pmsBookingId,
                    'booking_status'          => 'booking-confirmed',
                    'pms_booking_response_all'=> json_encode($response),
                ]);

                Log::channel('lodgify')->info('PmsBookingService: reservation created', [
                    'pms'            => $room->pms_source,
                    'booking_id'     => $booking->id,
                    'pms_booking_id' => $pmsBookingId,
                ]);

                return ['ok' => true, 'pms_booking_id' => $pmsBookingId, 'response' => $response];
            }

            Log::channel('lodgify')->error('PmsBookingService: reservation failed', [
                'pms'      => $room->pms_source,
                'status'   => $httpStatus,
                'response' => $response,
            ]);

            return $this->fail(
                $response['error']['message'] ?? $response['message'] ?? 'PMS reservation failed'
            );

        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('PmsBookingService: exception', [
                'pms'   => $room->pms_source,
                'error' => $e->getMessage(),
            ]);
            return $this->fail($e->getMessage());
        }
    }

    /**
     * Extract the PMS-side reservation/booking ID from the adapter response.
     * Each PMS uses a different field name.
     */
    private function extractBookingId(array $response, string $pms): ?string
    {
        $data = $response['data'] ?? $response;

        return match ($pms) {
            'hostfully' => (string)($data['lead']['uid'] ?? $data['uid'] ?? ''),
            'hostaway'  => (string)($data['result']['id'] ?? $data['id'] ?? ''),
            'guesty'    => (string)($data['reservationId'] ?? $data['_id'] ?? $data['id'] ?? ''),
            // Lodgify v1/reservation/booking returns a plain integer (the booking ID)
            'lodgify'   => is_numeric($data)
                ? (string)$data
                : (string)($data['id'] ?? $data['booking_id'] ?? ($data['items'][0]['id'] ?? '')),
            default     => (string)($data['id'] ?? $data['uid'] ?? ''),
        };
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
