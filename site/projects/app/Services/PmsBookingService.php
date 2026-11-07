<?php

namespace App\Services;

use App\Models\Pms\PmsRoom;
use App\Models\BookingRequest;
use App\Modules\VacationRental\Pms\PmsManager;
use App\Helper\QuoteNormalizer;
use App\Helper\LogRedactor;
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
    /** @var PmsManager|object */
    protected $pms;

    public function __construct(PmsManager $pms)
    {
        $this->pms = $pms;
    }

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

        // Block if the PMS total changed materially since the guest confirmed.
        // Tolerance = 1 minor unit (fix #7): $0.01 USD/EUR, 1 JPY/KRW.
        // A larger gap means pricing changed — the guest's consent was for a
        // different price and the booking must NOT proceed.
        $quotedTotal  = (float)$booking->total_amount;
        $freshTotal   = (float)$quoteCheck['total'];
        $currency     = $quoteCheck['normalized_quote']['currency'] ?? $room->currency ?? 'USD';
        // Fix #4: integer minor-unit comparison — zero tolerance.
        if (\App\Helper\StripeHelper::toStripeAmount($freshTotal, $currency)
            !== \App\Helper\StripeHelper::toStripeAmount($quotedTotal, $currency)) {
            Log::channel('lodgify')->warning('PmsBookingService: quote total changed — blocking reservation', [
                'booking_id'   => $booking->id,
                'pms'          => $room->pms_source,
                'quoted_total' => $quotedTotal,
                'fresh_total'  => $freshTotal,
                'diff'         => round($freshTotal - $quotedTotal, 2),
            ]);
            return $this->fail(
                'The price for this property has changed since you started your booking. '
                . 'Please go back and refresh your quote to confirm the updated total before proceeding.'
            );
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
            Log::channel('lodgify')->warning('PmsBookingService: availability check failed — blocking booking (fail-closed)', [
                'pms'   => $room->pms_source,
                'error' => $e->getMessage(),
            ]);
            // Fail closed: an unknown availability state must never allow a booking.
            // The guest will be shown an error and can retry once the PMS API recovers.
            return ['available' => false, 'reason' => 'Could not confirm availability. Please try again in a moment.'];
        }
    }

    private function refetchQuote(PmsRoom $room, BookingRequest $booking): array
    {
        try {
            $adapter = $this->pms->driver($room->pms_source);

            // Use the stored per-type guest counts (adults/child saved at booking time).
            // Falling back to total_guests as adults is WRONG — it inflates the adult count
            // and suppresses children, so the PMS may return a different price than what
            // the guest saw (extra-guest fees, child rates, and pet fees are all affected).
            $input = [
                'property_id'      => $room->pms_property_id,
                'room_id'          => $room->pms_room_id,
                'checkin'          => $booking->checkin,
                'checkout'         => $booking->checkout,
                'adults'           => (int)($booking->adults ?? $booking->total_guests ?? 1),
                'children'         => (int)($booking->child ?? 0),
                'infants'          => (int)($booking->infants ?? 0),
                'pets'             => (int)($booking->total_pets ?? 0),
                'promo_code'       => $booking->discount_coupon ?? '',
                // Re-apply validated optional add-ons.
                // FeeValidator runs BEFORE the adapter call; unknown IDs are stripped.
                'optional_services'=> \App\Helper\FeeValidator::confirmed(
                    json_decode($booking->optional_services ?? '{}', true) ?: [],
                    $room
                ),
            ];

            $raw   = $adapter->getRawQuote($input);
            $quote = QuoteNormalizer::normalize($raw, $room->pms_source, $input);

            if (!$quote['ok']) {
                return ['ok' => false, 'error' => $quote['error'] ?? 'Quote failed'];
            }

            // Block approximate quotes (Hostaway calendar fallback — taxes excluded).
            // Never allow payment to proceed on a price that omits mandatory taxes.
            if (!empty($quote['approximate'])) {
                Log::channel('lodgify')->warning('PmsBookingService: approximate quote returned — blocking booking', [
                    'pms'        => $room->pms_source,
                    'booking_id' => $booking->id,
                    'note'       => $quote['approximate_note'] ?? 'taxes not included',
                ]);
                return ['ok' => false, 'error' => 'Price could not be confirmed with taxes included. Please try again in a moment.'];
            }

            return ['ok' => true, 'total' => $quote['total'], 'raw_quote' => $raw, 'normalized_quote' => $quote];

        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    protected function createInPms(PmsRoom $room, BookingRequest $booking, ?array $rawQuote): array
    {
        $input = [
            'property_id'      => $room->pms_property_id,
            'room_id'          => $room->pms_room_id,
            'checkin'          => $booking->checkin,
            'checkout'         => $booking->checkout,
            'adults'           => (int)($booking->adults ?? $booking->total_guests ?? 1),
            'children'         => (int)($booking->child ?? 0),
            'infants'          => (int)($booking->infants ?? 0),
            'pets'             => (int)($booking->total_pets ?? 0),
            'optional_services'=> \App\Helper\FeeValidator::confirmed(
                json_decode($booking->optional_services ?? '{}', true) ?: [],
                $room
            ),
            'first_name'   => $booking->first_name ?? explode(' ', $booking->name ?? '')[0] ?? '',
            'last_name'    => $booking->last_name  ?? (explode(' ', $booking->name ?? '')[1] ?? ''),
            'email'        => $booking->email    ?? '',
            'phone'        => $booking->mobile   ?? $booking->phone ?? '',
            'total_amount' => $booking->total_amount,
            'gross_amount' => $booking->gross_amount,
            'tax'          => $booking->tax,
            'raw_quote'    => $rawQuote,
        ];

        try {
            $adapter  = $this->pms->driver($room->pms_source);
            $response = $adapter->createReservation($input);

            $httpStatus = $response['status'] ?? 0;

            if ($httpStatus !== 200 && $httpStatus !== 201) {
                // Clean failure — PMS never assigned an ID, nothing to cancel
                Log::channel('lodgify')->error('PmsBookingService: reservation failed', [
                    'pms'      => $room->pms_source,
                    'status'   => $httpStatus,
                    'response' => LogRedactor::clean($response),
                ]);
                return $this->fail(
                    $response['error']['apiErrorMessage']
                    ?? $response['error']['message']
                    ?? $response['message']
                    ?? 'PMS reservation failed'
                );
            }

            // ── STEP A: Extract and persist ID immediately ────────────────────
            // Storing the PMS booking ID before verification guarantees we never
            // lose it.  If verification subsequently fails, handleVerificationFailure()
            // uses the stored ID to cancel the reservation without a second create().
            $pmsBookingId = $this->extractBookingId($response, $room->pms_source);

            if (empty($pmsBookingId)) {
                // A successful HTTP response without a reservation ID is ambiguous:
                // the PMS may have created a reservation that we cannot identify.
                // Do not confirm the booking, retry creation, or refund automatically.
                $error = [
                    'reason'      => 'PMS returned success without a reservation ID',
                    'pms'         => $room->pms_source,
                    'occurred_at' => now()->toIso8601String(),
                ];

                $booking->update([
                    'pms_booking_id'           => null,
                    'booking_status'           => 'reconciliation-needed',
                    'pms_booking_response_all' => json_encode($response),
                    'pms_sync_error'            => json_encode($error),
                ]);

                Log::channel('lodgify')->critical('PmsBookingService: successful response missing reservation ID', [
                    'pms'        => $room->pms_source,
                    'booking_id' => $booking->id,
                    'response'   => LogRedactor::clean($response),
                ]);

                return $this->fail($error['reason']);
            }

            // payment_status was never updated here, so admin showed "Pending"
            // even after a successful charge. 'paid' = full total collected,
            // 'partially' = deposit installment collected, balance scheduled.
            $paidSoFar = 0.0;
            try {
                $paidSoFar = (float) \App\Models\Payment::where('booking_id', $booking->id)
                    ->whereIn('status', ['complete', 'completed', 'succeeded', 'paid'])
                    ->sum('amount');
            } catch (\Throwable $e) { /* payments table variations — keep status as-is */ }

            $booking->update([
                'pms_booking_id'          => $pmsBookingId,
                'pms_source'              => $room->pms_source,
                'pms_room_id'             => $room->pms_room_id,
                'booking_status'          => 'booking-confirmed',
                'pms_booking_response_all'=> json_encode($response),
                ...($paidSoFar > 0
                    ? ['payment_status' => ($paidSoFar + 0.01 >= (float) $booking->total_amount) ? 'paid' : 'partially']
                    : []),
            ]);

            Log::channel('lodgify')->info('PmsBookingService: reservation created — verifying', [
                'pms'            => $room->pms_source,
                'booking_id'     => $booking->id,
                'pms_booking_id' => $pmsBookingId,
            ]);

            // Confirm the reservation in the PMS. Lodgify creates bookings as
            // "Open", and Open bookings DO NOT block availability — the dates
            // stay sellable on Airbnb/VRBO/direct until the host manually
            // confirms. Payment has already been collected by this point, so
            // flip it to Booked now. Non-fatal: an error here is logged and the
            // host can still confirm manually from the Lodgify dashboard.
            if (method_exists($adapter, 'markReservationBooked')) {
                try {
                    $bookRes = $adapter->markReservationBooked($pmsBookingId);
                    $bookOk  = in_array($bookRes['status'] ?? 0, [200, 201, 204], true);
                    Log::channel('lodgify')->{$bookOk ? 'info' : 'error'}('PmsBookingService: mark-as-Booked ' . ($bookOk ? 'OK' : 'FAILED'), [
                        'pms_booking_id' => $pmsBookingId,
                        'status'         => $bookRes['status'] ?? null,
                        'error'          => $bookRes['error'] ?? null,
                    ]);
                } catch (\Throwable $e) {
                    Log::channel('lodgify')->error('PmsBookingService: mark-as-Booked exception', [
                        'pms_booking_id' => $pmsBookingId, 'error' => $e->getMessage(),
                    ]);
                }
            }

            // ── STEP B: Verify total + fee line items ─────────────────────────
            // Use booking->currency — the currency the guest saw and accepted.
            $currency    = $booking->currency ?? $room->currency ?? 'USD';
            $optServices = json_decode($booking->optional_services ?? '{}', true) ?: [];

            // Build the canonical fee list from the accepted quote (the freshly-fetched
            // quote that exactly matched the accepted total).  This is preferred over
            // optional_fees_json because it reflects the fee amounts the guest actually
            // agreed to, not the current (potentially stale) PMS fee definitions.
            $canonFees = \App\Services\ReservationVerifier::extractQuoteFees(
                $room->pms_source,
                $rawQuote ?? []
            );
            // Fallback: if quote extraction yields nothing (adapter doesn't expose
            // optional fees in the quote, e.g. Hostaway), use optional_fees_json.
            if (empty($canonFees)) {
                $canonFees = json_decode($room->optional_fees_json ?? '[]', true) ?: [];
            }

            $verification = \App\Services\ReservationVerifier::verify(
                $room->pms_source,
                $response,
                (float)$booking->total_amount,
                $currency,
                $optServices,
                $canonFees
            );

            if (!$verification['ok']) {
                Log::channel('lodgify')->error('PmsBookingService: reservation verification failed', [
                    'pms'            => $room->pms_source,
                    'booking_id'     => $booking->id,
                    'pms_booking_id' => $pmsBookingId,
                    'discrepancy'    => $verification['discrepancy'],
                ]);
                return $this->handleVerificationFailure(
                    $room, $booking, $pmsBookingId, $verification['discrepancy']
                );
            }

            // ── STEP C: Verification passed — block dates and return ──────────
            $this->blockDatesLocally($room, $booking);
            return ['ok' => true, 'pms_booking_id' => $pmsBookingId, 'response' => $response];

        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('PmsBookingService: exception', [
                'pms'   => $room->pms_source,
                'error' => $e->getMessage(),
            ]);
            return $this->fail($e->getMessage());
        }
    }

    /**
     * Called when ReservationVerifier reports a discrepancy AFTER the PMS has already
     * returned a reservation ID (which is already persisted).
     *
     * Strategy:
     *   1. Try to cancel the PMS reservation.
     *   2. Try to refund the Stripe PaymentIntent.
     *   3a. Both succeed  → booking_status = 'booking-cancelled'  (guest gets refund)
     *   3b. Either fails  → booking_status = 'reconciliation-needed'  (manual review)
     *
     * Never retries createReservation() — the PMS ID is already stored and a
     * second call would create a duplicate reservation.
     */
    protected function handleVerificationFailure(
        PmsRoom        $room,
        BookingRequest $booking,
        ?string        $pmsBookingId,
        array          $discrepancy
    ): array {
        $cancelOk = false;
        $refundOk = false;

        // ── Attempt PMS cancellation ──────────────────────────────────────────
        if ($pmsBookingId) {
            try {
                $adapter      = $this->pms->driver($room->pms_source);
                $cancelResult = $adapter->cancelReservation(['reservation_id' => $pmsBookingId]);
                $cancelOk     = in_array($cancelResult['status'] ?? 0, [200, 201, 204], true);
                Log::channel('lodgify')->info('PmsBookingService: cancellation attempt', [
                    'booking_id'     => $booking->id,
                    'pms_booking_id' => $pmsBookingId,
                    'cancel_ok'      => $cancelOk,
                    'cancel_status'  => $cancelResult['status'] ?? null,
                ]);
            } catch (\Throwable $e) {
                Log::channel('lodgify')->error('PmsBookingService: cancellation exception', [
                    'booking_id' => $booking->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // ── Attempt Stripe refund ─────────────────────────────────────────────
        $piId     = $booking->stripe_payment_intent_id ?? null;
        $refundId = null;
        if ($piId) {
            $refundId = $this->issueStripeRefund($piId, $booking->id);
            $refundOk = is_string($refundId);
        }

        // ── Update booking status ─────────────────────────────────────────────
        if ($cancelOk && $refundOk) {
            $booking->update([
                'booking_status' => 'booking-cancelled',
                'pms_sync_error' => json_encode([
                    'verification_failed' => $discrepancy,
                    'auto_cancelled'      => true,
                    'stripe_refund_id'    => $refundId,
                    'cancelled_at'        => now()->toIso8601String(),
                ]),
            ]);
            return $this->fail(
                'Your booking could not be confirmed due to a price discrepancy. '
                . 'Your payment has been refunded. Please contact us if you need assistance.'
            );
        }

        // At least one recovery step failed — requires manual reconciliation
        $booking->update([
            'booking_status' => 'reconciliation-needed',
            'pms_sync_error' => json_encode([
                'verification_failed' => $discrepancy,
                'cancel_ok'           => $cancelOk,
                'refund_ok'           => $refundOk,
                'stripe_refund_id'    => $refundId,
                'pms_booking_id'      => $pmsBookingId,
                'flagged_at'          => now()->toIso8601String(),
            ]),
        ]);

        Log::channel('lodgify')->critical('PmsBookingService: MANUAL RECONCILIATION REQUIRED', [
            'booking_id'      => $booking->id,
            'pms_booking_id'  => $pmsBookingId,
            'cancel_ok'       => $cancelOk,
            'refund_ok'       => $refundOk,
            'stripe_refund_id'=> $refundId,
            'discrepancy'     => $discrepancy,
        ]);

        return $this->fail(
            'Your booking requires manual review before it can be confirmed. '
            . 'Our team will contact you within 24 hours. '
            . 'Booking reference: ' . ($booking->request_id ?? (string)$booking->id)
        );
    }

    /**
     * Issue a full Stripe refund for the given PaymentIntent.
     *
     * Idempotent: if a succeeded refund already exists for this PI it is
     * returned immediately without creating a duplicate.
     *
     * A deterministic idempotency key (refund_verify_{pi_id}) is passed to
     * Stripe::Refund::create() so that a Stripe-side duplicate caused by a
     * network retry cannot result in two charges being reversed.
     *
     * After issuing (or finding) a refund the total refunded amount is compared
     * against the full PI amount.  A partial refund returns false so the booking
     * is flagged for manual reconciliation rather than incorrectly marked cancelled.
     *
     * Returns the Stripe refund ID (re_xxx) on success, or false on failure.
     * The caller should persist the refund ID.
     *
     * Extracted as a protected method so tests can override it.
     *
     * @param string     $paymentIntentId  pi_xxx
     * @param int|string $bookingId        for logging only
     * @return string|false  Stripe refund ID on success, false on any failure
     */
    protected function issueStripeRefund(string $paymentIntentId, $bookingId): string|false
    {
        try {
            $stripeKey = class_exists(\ModelHelper::class)
                ? \ModelHelper::getDataFromSetting('stripe_secret_key')
                : config('services.stripe.secret', '');

            if (empty($stripeKey)) {
                Log::channel('lodgify')->error('PmsBookingService: Stripe key not configured — cannot refund', [
                    'booking_id' => $bookingId,
                ]);
                return false;
            }

            \Stripe\Stripe::setApiKey($stripeKey);

            return $this->executeRefundLogic($paymentIntentId, $bookingId);

        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('PmsBookingService: Stripe refund failed', [
                'booking_id'        => $bookingId,
                'payment_intent_id' => $paymentIntentId,
                'error'             => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Core refund logic — separated from Stripe key setup so tests can call it
     * directly after overriding the three protected Stripe helper methods.
     */
    protected function executeRefundLogic(string $paymentIntentId, $bookingId): string|false
    {
        $piAmount        = $this->getPaymentIntentAmount($paymentIntentId);

        if ($piAmount === null || $piAmount <= 0) {
            Log::channel('lodgify')->warning('PmsBookingService: Stripe PaymentIntent amount unavailable — refund not verified', [
                'booking_id'        => $bookingId,
                'payment_intent_id' => $paymentIntentId,
            ]);
            return false;
        }

        $existingRefunds = $this->getExistingSucceededRefunds($paymentIntentId);
        $existingTotal   = (int) array_sum(array_map(fn($r) => (int)$r->amount, $existingRefunds));

        if ($existingTotal > 0) {
            // A succeeded refund already exists.  Verify it covers the full PI amount.
            if ($existingTotal < $piAmount) {
                Log::channel('lodgify')->warning('PmsBookingService: existing Stripe refund is partial — manual action required', [
                    'booking_id'        => $bookingId,
                    'payment_intent_id' => $paymentIntentId,
                    'existing_total'    => $existingTotal,
                    'pi_amount'         => $piAmount,
                ]);
                return false; // partial existing refund → needs reconciliation
            }
            $refundId = $existingRefunds[0]->id;
            Log::channel('lodgify')->info('PmsBookingService: existing full Stripe refund found — reusing', [
                'booking_id'        => $bookingId,
                'payment_intent_id' => $paymentIntentId,
                'stripe_refund_id'  => $refundId,
            ]);
            return $refundId;
        }

        // Fail-closed: if we cannot retrieve the PI's original amount we cannot confirm
        // full coverage.  Do NOT create a refund in this state — a partial refund would
        // leave the guest partially charged with no way to detect it automatically.
        if ($piAmount === null) {
            Log::channel('lodgify')->warning('PmsBookingService: cannot verify full refund coverage — PI amount unknown; aborting refund', [
                'booking_id'        => $bookingId,
                'payment_intent_id' => $paymentIntentId,
            ]);
            return false;
        }

        // No existing refund — create one with a deterministic idempotency key so
        // Stripe-side network retries cannot double-reverse the charge.
        $idempotencyKey = $this->buildRefundIdempotencyKey($paymentIntentId);
        $refund         = $this->createStripeRefund($paymentIntentId, $idempotencyKey);

        // Verify the new refund covers the full PI amount.
        // A partial refund (unusual but possible) must not silently pass as a full refund.
        if ((int)$refund->amount < $piAmount) {
            Log::channel('lodgify')->warning('PmsBookingService: new Stripe refund is partial — not a full refund', [
                'booking_id'        => $bookingId,
                'payment_intent_id' => $paymentIntentId,
                'refund_id'         => $refund->id,
                'refund_amount'     => $refund->amount,
                'pi_amount'         => $piAmount,
            ]);
            return false;
        }

        Log::channel('lodgify')->info('PmsBookingService: Stripe refund issued', [
            'booking_id'        => $bookingId,
            'payment_intent_id' => $paymentIntentId,
            'stripe_refund_id'  => $refund->id,
            'idempotency_key'   => $idempotencyKey,
        ]);
        return $refund->id;
    }

    /**
     * Returns a deterministic Stripe idempotency key for a verification refund.
     * Deterministic = same PI always produces the same key, so Stripe deduplicates
     * on its side even if the network drops the response after the refund is created.
     */
    protected function buildRefundIdempotencyKey(string $paymentIntentId): string
    {
        return 'refund_verify_' . $paymentIntentId;
    }

    /**
     * Retrieve the full charged amount (minor units) from Stripe for the given PI.
     * Returns null if the PI cannot be retrieved; refund logic then fails closed.
     */
    protected function getPaymentIntentAmount(string $paymentIntentId): ?int
    {
        try {
            $pi = \Stripe\PaymentIntent::retrieve($paymentIntentId);
            return (int)$pi->amount;
        } catch (\Throwable $e) {
            Log::channel('lodgify')->warning('PmsBookingService: could not retrieve PI amount for refund verification', [
                'payment_intent_id' => $paymentIntentId,
                'error'             => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Return all succeeded Stripe refunds for the given PaymentIntent.
     * Stripe raises an error on duplicate full-refund attempts; checking first is required.
     */
    protected function getExistingSucceededRefunds(string $paymentIntentId): array
    {
        $list      = \Stripe\Refund::all(['payment_intent' => $paymentIntentId, 'limit' => 10]);
        $succeeded = [];
        foreach ($list->data ?? [] as $r) {
            if ($r->status === 'succeeded') {
                $succeeded[] = $r;
            }
        }
        return $succeeded;
    }

    /**
     * Call Stripe::Refund::create with the supplied idempotency key.
     * Extracted so tests can override without touching the Stripe SDK.
     */
    protected function createStripeRefund(string $paymentIntentId, string $idempotencyKey): object
    {
        return \Stripe\Refund::create(
            ['payment_intent' => $paymentIntentId],
            ['idempotency_key' => $idempotencyKey]
        );
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

    /**
     * Insert booked nights into pms_unavailable_dates so the datepicker
     * blocks them immediately without waiting for the next PMS sync.
     */
    protected function blockDatesLocally(PmsRoom $room, \App\Models\BookingRequest $booking): void
    {
        try {
            $pmsProperty = \App\Models\Pms\PmsProperty::where('pms_property_id', $room->pms_property_id)->first();
            if (!$pmsProperty) return;

            $current = new \DateTime($booking->checkin);
            $end     = new \DateTime($booking->checkout); // checkout night is NOT blocked

            while ($current < $end) {
                \App\Models\Pms\PmsUnavailableDate::firstOrCreate([
                    'property_id' => $pmsProperty->id,
                    'un_date'     => $current->format('Y-m-d'),
                ]);
                $current->modify('+1 day');
            }
        } catch (\Throwable $e) {
            // Non-critical — log and continue; next PMS sync will correct it
            Log::channel('lodgify')->warning('blockDatesLocally failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
