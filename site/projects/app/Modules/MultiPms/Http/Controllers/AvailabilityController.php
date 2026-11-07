<?php

namespace App\Modules\MultiPms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Modules\MultiPms\Models\PmsRoom;
use App\Modules\MultiPms\Pms\PmsManager;
use Illuminate\Support\Facades\Log;

/**
 * Real-time availability from the PMS.
 *
 * GET /pms-availability?room_id=X&start_date=YYYY-MM-DD&end_date=YYYY-MM-DD
 *
 * Returns a canonical availability object for the date picker:
 *   ok              bool
 *   blocked         string[]   — fully unavailable dates (guest cannot select)
 *   no_checkin      string[]   — closedOnArrival: can check-out but not check-in
 *   no_checkout     string[]   — closedOnDeparture: can check-in but not check-out
 *   min_stay        int        — global minimum stay in nights (fallback)
 *   min_stay_by_date object    — { "YYYY-MM-DD": N } per-date overrides
 *   max_stay        int|null   — global maximum stay in nights
 */
class AvailabilityController extends Controller
{
    public function get(Request $request): JsonResponse
    {
        $room = PmsRoom::find($request->room_id)
             ?? PmsRoom::where('pms_room_id', $request->room_id)->first()
             ?? PmsRoom::where('pms_property_id', $request->room_id)->first();

        if (!$room) {
            return response()->json(['ok' => false, 'error' => 'Property not found'], 200);
        }

        if (empty($room->pms_source)) {
            return response()->json(['ok' => false, 'error' => 'No PMS configured'], 200);
        }

        // Request a 3-month window by default so navigation is smooth
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->input('end_date',
            now()->addMonths(3)->endOfMonth()->toDateString()
        );

        try {
            $adapter = app(PmsManager::class)->driver($room->pms_source);

            $raw = $adapter->getAvailability([
                'property_id' => $room->pms_property_id,
                'room_id'     => $room->pms_room_id,
                'checkin'     => $startDate,
                'checkout'    => $endDate,
                'adults'      => 1,
            ]);

            Log::channel('lodgify')->info('AvailabilityController raw', [
                'pms'         => $room->pms_source,
                'property_id' => $room->pms_property_id,
                'room_id'     => $room->pms_room_id,
                'status'      => $raw['status'] ?? null,
                'data_sample' => is_array($raw['data'] ?? null) ? array_slice((array)$raw['data'], 0, 2) : ($raw['data'] ?? $raw['error'] ?? null),
            ]);

            $normalized = $this->normalize($raw, $room->pms_source, $startDate, $endDate);

            Log::channel('lodgify')->info('AvailabilityController normalized', [
                'ok'      => $normalized['ok'] ?? false,
                'blocked' => count($normalized['blocked'] ?? []),
                'error'   => $normalized['error'] ?? null,
            ]);

            return response()->json($normalized);

        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('AvailabilityController error', [
                'room_id' => $room->id,
                'pms'     => $room->pms_source,
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => 'Could not fetch availability'], 200);
        }
    }

    // ── Normalizer ─────────────────────────────────────────────────────────────

    /** Public alias so blade templates (e.g. common-dates) can reuse the normalizer */
    public function normalizePublic(array $raw, string $pms, string $from, string $to): array
    {
        return $this->normalize($raw, $pms, $from, $to);
    }

    private function normalize(array $raw, string $pms, string $from, string $to): array
    {
        return match ($pms) {
            'hostfully' => $this->fromHostfully($raw),
            'hostaway'  => $this->fromHostaway($raw),
            'guesty'    => $this->fromGuesty($raw),
            'lodgify'   => $this->fromLodgify($raw),
            default     => ['ok' => false, 'error' => "Unsupported PMS: {$pms}"],
        };
    }

    // ── Hostfully ──────────────────────────────────────────────────────────────
    // api/v3.3/property-calendar actual response shape (confirmed from logs):
    //   { "calendars": [{ "entries": [
    //     { "date": "YYYY-MM-DD",
    //       "availability": { "unavailable": bool, "unavailabilityReason": "BOOKING"|"BLOCK"|... },
    //       "pricing": { "price": float },
    //       "minimumStay": int,
    //       "closedOnArrival": bool,
    //       "closedOnDeparture": bool
    //     }
    //   ]}]}
    private function fromHostfully(array $raw): array
    {
        $status = $raw['status'] ?? 0;
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => 'Hostfully availability unavailable'];
        }

        $body = $raw['data'] ?? [];

        // Collect entries from all calendars (usually one per property)
        $entries = [];
        if (!empty($body['calendars']) && is_array($body['calendars'])) {
            foreach ($body['calendars'] as $cal) {
                if (!empty($cal['entries']) && is_array($cal['entries'])) {
                    $entries = array_merge($entries, $cal['entries']);
                }
            }
        }

        if (empty($entries)) {
            return ['ok' => true, 'blocked' => [], 'no_checkin' => [], 'no_checkout' => [],
                    'min_stay' => 1, 'min_stay_by_date' => (object)[], 'max_stay' => null, 'rates' => (object)[]];
        }

        return $this->buildFromDays($entries, function ($day) {
            $avail     = is_array($day['availability'] ?? null) ? $day['availability'] : [];
            $unavail   = (bool)($avail['unavailable'] ?? false);
            $price     = (float)(($day['pricing']['price'] ?? $day['price'] ?? 0));
            return [
                'date'        => $day['date']                          ?? null,
                'available'   => !$unavail,
                'no_checkin'  => (bool)($day['closedOnArrival']        ?? false),
                'no_checkout' => (bool)($day['closedOnDeparture']      ?? false),
                'min_stay'    => (int)($day['minimumStay']             ?? 1),
                'max_stay'    => (int)($day['maximumStay']             ?? 0) ?: null,
                'price'       => $price > 0 ? $price : null,
            ];
        });
    }

    // ── Hostaway ───────────────────────────────────────────────────────────────
    // Response: data.result[] — each item: { date, isAvailable, minimumStay,
    //   maximumStay, closedOnArrival, closedOnDeparture, price }
    private function fromHostaway(array $raw): array
    {
        $status = $raw['status'] ?? 0;
        if ($status !== 200) {
            return ['ok' => false, 'error' => 'Hostaway availability unavailable'];
        }

        $days = $raw['data']['result'] ?? $raw['result'] ?? [];

        return $this->buildFromDays($days, function ($day) {
            return [
                'date'        => $day['date']                  ?? null,
                'available'   => (bool)($day['isAvailable']    ?? true),
                'no_checkin'  => (bool)($day['closedOnArrival']   ?? false),
                'no_checkout' => (bool)($day['closedOnDeparture'] ?? false),
                'min_stay'    => (int)($day['minimumStay']     ?? 1),
                'max_stay'    => (int)($day['maximumStay']     ?? 0) ?: null,
                'price'       => isset($day['price']) && $day['price'] > 0 ? (float)$day['price'] : null,
            ];
        });
    }

    // ── Guesty ─────────────────────────────────────────────────────────────────
    // v1/listings/{id}/calendar response: {"data": [{date, status, minNights, price}]}
    // The adapter wraps in ['status'=>200, 'data'=><body>], so $raw['data'] = {"data":[...]}
    private function fromGuesty(array $raw): array
    {
        $status = $raw['status'] ?? 0;
        if ($status !== 200) {
            return ['ok' => false, 'error' => 'Guesty availability unavailable'];
        }

        $body = $raw['data'] ?? [];
        // Guesty wraps the array in {"data": [...]}, handle both shapes
        $days = $body['data'] ?? (isset($body[0]) ? $body : []);
        if (!is_array($days) || empty($days)) {
            return ['ok' => true, 'blocked' => [], 'no_checkin' => [], 'no_checkout' => [], 'min_stay' => 1, 'min_stay_by_date' => [], 'max_stay' => null];
        }

        return $this->buildFromDays($days, function ($day) {
            $s = strtolower($day['status'] ?? 'available');
            return [
                'date'       => $day['date']       ?? null,
                'available'  => ($s === 'available'),
                'no_checkin' => false,
                'no_checkout'=> false,
                'min_stay'   => (int)($day['minNights'] ?? 1),
                'max_stay'   => null,
                'price'      => isset($day['price']) && $day['price'] > 0 ? (float)$day['price'] : null,
            ];
        });
    }

    // ── Lodgify ────────────────────────────────────────────────────────────────
    // Response from v1/availability/{propertyId}:
    // Array of period objects: [{property_id, room_type_id, period_start, period_end,
    //   available (int), is_available (bool), booking_ids, closed_period_id}]
    // Each period covers one or more consecutive days with the same availability.
    // We expand each period into individual day entries for the date picker.
    private function fromLodgify(array $raw): array
    {
        $status = $raw['status'] ?? 0;
        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'error' => 'Lodgify availability unavailable'];
        }

        $periods = $raw['data'] ?? [];
        if (!is_array($periods) || empty($periods)) {
            return [
                'ok' => true, 'blocked' => [], 'no_checkin' => [],
                'no_checkout' => [], 'min_stay' => 1,
                'min_stay_by_date' => (object)[], 'max_stay' => null, 'rates' => (object)[],
            ];
        }

        // Expand each period into individual day objects
        $days = [];
        foreach ($periods as $period) {
            $start       = $period['period_start'] ?? null;
            $end         = $period['period_end']   ?? null;
            $isAvailable = (bool)($period['is_available'] ?? true);
            if (!$start || !$end) continue;

            $cur    = new \DateTime($start);
            $endDt  = new \DateTime($end);
            while ($cur <= $endDt) {
                $days[] = [
                    'date'        => $cur->format('Y-m-d'),
                    'available'   => $isAvailable,
                    'no_checkin'  => false,
                    'no_checkout' => false,
                    'min_stay'    => 1,
                    'max_stay'    => null,
                    'price'       => null,
                ];
                $cur->modify('+1 day');
            }
        }

        return $this->buildFromDays($days, fn($day) => $day);
    }

    // ── Shared builder ─────────────────────────────────────────────────────────

    private function buildFromDays(array $days, callable $mapper): array
    {
        $blocked       = [];
        $noCheckin     = [];
        $noCheckout    = [];
        $minStayByDate = [];
        $rates         = [];   // date → nightly price
        $globalMin     = 1;
        $globalMax     = null;

        foreach ($days as $day) {
            $mapped = $mapper($day);
            $date   = $mapped['date'] ?? null;
            if (!$date) continue;

            if (!$mapped['available']) {
                $blocked[] = $date;
            } else {
                if ($mapped['no_checkin'])  $noCheckin[]  = $date;
                if ($mapped['no_checkout']) $noCheckout[] = $date;
            }

            $ms = (int)($mapped['min_stay'] ?? 1);
            if ($ms > 1) {
                $minStayByDate[$date] = $ms;
                if ($ms > $globalMin) $globalMin = $ms;
            }

            if ($mapped['max_stay'] && (!$globalMax || $mapped['max_stay'] < $globalMax)) {
                $globalMax = $mapped['max_stay'];
            }

            // Collect nightly rate for available days only
            if ($mapped['available'] && isset($mapped['price']) && $mapped['price'] > 0) {
                $rates[$date] = $mapped['price'];
            }
        }

        return [
            'ok'               => true,
            'blocked'          => array_values(array_unique($blocked)),
            'no_checkin'       => array_values(array_unique($noCheckin)),
            'no_checkout'      => array_values(array_unique($noCheckout)),
            'min_stay'         => $globalMin,
            'min_stay_by_date' => (object)$minStayByDate,
            'max_stay'         => $globalMax,
            'rates'            => (object)$rates,  // {"YYYY-MM-DD": price}
        ];
    }
}
