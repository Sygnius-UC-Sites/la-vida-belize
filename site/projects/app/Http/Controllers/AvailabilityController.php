<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Pms\PmsRoom;
use App\Models\Pms\PmsCalendar;
use App\Modules\VacationRental\Pms\PmsManager;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
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

        try {
            $startDate = Carbon::parse(
                $request->input('start_date', now()->startOfMonth()->toDateString())
            )->toDateString();
            $endDate = Carbon::parse(
                $request->input('end_date', now()->addMonths(18)->endOfMonth()->toDateString())
            )->min(Carbon::parse($startDate)->addMonthsNoOverflow(24))->toDateString();
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => 'Invalid date range'], 200);
        }

        if ($endDate < $startDate) {
            return response()->json(['ok' => false, 'error' => 'Invalid date range'], 200);
        }

        $synced = $this->fromSyncedCalendar($room, $startDate, $endDate);
        if ($synced !== null) {
            return response()->json($this->reconcileWithLocal($synced, $room));
        }

        try {
            $adapter = app(PmsManager::class)->driver($room->pms_source);

            // Include a per-property version in the cache key so webhooks can
            // bust the cache instantly by bumping pms_avail_v_{pms}_{propertyId}.
            $version  = \Illuminate\Support\Facades\Cache::get("pms_avail_v_{$room->pms_source}_{$room->pms_property_id}", 0);
            $cacheKey = "pms_avail_{$room->pms_source}_{$room->pms_property_id}_{$startDate}_{$endDate}_v{$version}";

            // Cache for 1 hour — matches pms:sync-calendars cron frequency.
            // Webhook fires bump the version key, which immediately invalidates
            // all cached windows for that property without needing tag support.
            $normalized = \Illuminate\Support\Facades\Cache::remember($cacheKey, 3600, function () use ($adapter, $room, $startDate, $endDate) {
                $raw = $adapter->getAvailability([
                    'property_id' => $room->pms_property_id,
                    'room_id'     => $room->pms_room_id,
                    'checkin'     => $startDate,
                    'checkout'    => $endDate,
                    'adults'      => 1,
                ]);

                Log::channel('lodgify')->info('AvailabilityController: live API fetch', [
                    'pms'    => $room->pms_source,
                    'pid'    => $room->pms_property_id,
                    'status' => $raw['status'] ?? null,
                ]);

                return $this->normalize($raw, $room->pms_source, $startDate, $endDate);
            });

            return response()->json($this->reconcileWithLocal($normalized, $room));

        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('AvailabilityController error', [
                'room_id' => $room->id,
                'pms'     => $room->pms_source,
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'error' => 'Could not fetch availability'], 200);
        }
    }

    private function fromSyncedCalendar(PmsRoom $room, string $from, string $to): ?array
    {
        $rows = PmsCalendar::forRoom($room->pms_source, (string) $room->pms_room_id)
            ->inRange($from, $to)
            ->orderBy('date')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $byDate = $rows->keyBy(fn ($row) => $row->date->format('Y-m-d'));
        $blocked = [];
        $noCheckin = [];
        $noCheckout = [];
        $minStayByDate = [];
        $rates = [];
        $globalMin = 1;
        $globalMax = null;

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $ymd = $date->format('Y-m-d');
            $row = $byDate->get($ymd);
            $available = $row
                && $row->state === PmsCalendar::STATE_AVAILABLE
                && !$row->stop_sell
                && ($row->inventory === null || $row->inventory > 0);

            if (!$available) {
                $blocked[] = $ymd;
                continue;
            }

            if ($row->closed_to_arrival) {
                $noCheckin[] = $ymd;
            }
            if ($row->closed_to_departure) {
                $noCheckout[] = $ymd;
            }
            if ((int) $row->min_stay > 1) {
                $minStayByDate[$ymd] = (int) $row->min_stay;
                $globalMin = max($globalMin, (int) $row->min_stay);
            }
            if ((int) $row->max_stay > 0) {
                $globalMax = $globalMax
                    ? min($globalMax, (int) $row->max_stay)
                    : (int) $row->max_stay;
            }
            if ((float) $row->nightly_rate > 0) {
                $rates[$ymd] = (float) $row->nightly_rate;
            }
        }

        return [
            'ok' => true,
            'source' => 'synced_calendar',
            'synced_at' => optional($rows->max('synced_at'))->toIso8601String(),
            'currency' => optional($rows->first(fn ($row) => !empty($row->currency)))->currency
                ?? $room->currency
                ?? 'USD',
            'blocked' => $blocked,
            'no_checkin' => $noCheckin,
            'no_checkout' => $noCheckout,
            'min_stay' => $globalMin,
            'min_stay_by_date' => (object) $minStayByDate,
            'max_stay' => $globalMax,
            'rates' => (object) $rates,
        ];
    }

    /**
     * Merge the site's OWN availability (bookings made on this site, iCal, manual
     * blocks — all reconciled by LiveCart) into the PMS-synced/live availability,
     * then recompute Airbnb-style turnover once over the unioned occupied nights.
     *
     * Why: the PMS calendar only reflects what the PMS knows. A booking made on
     * THIS site lands in `booking_requests` and isn't in the PMS until it syncs
     * out and back — so without this merge a fresh booking never blocks the picker
     * (double-booking risk) and turnover half-days are wrong. LiveCart already
     * reconciles pms_calendar + pms_unavailable_dates + booking_requests + iCal +
     * manual blocks, so it is the single source of truth for occupancy.
     *
     * PMS arrival/departure RESTRICTIONS (closed_to_arrival/departure) are NOT
     * occupancy, so they are preserved separately (re-unioned after, minus blocks).
     */
    private function reconcileWithLocal(array $availability, PmsRoom $room): array
    {
        if (($availability['ok'] ?? false) !== true) {
            return $availability;
        }

        $core = \App\Models\Property::where('pms_room_id', $room->id)->first()
             ?? \App\Models\Property::where('pms_room_id', $room->pms_room_id)->first();

        // Occupied NIGHTS = PMS blocked nights ∪ LiveCart occupied nights.
        // (A "blocked" date is an unavailable night; the checkout day is NOT a night.)
        $nights = [];
        foreach (($availability['blocked'] ?? []) as $d) { $nights[$d] = true; }

        // Preserve PMS-only arrival/departure restrictions to re-apply after.
        $pmsNoCheckin  = array_values($availability['no_checkin']  ?? []);
        $pmsNoCheckout = array_values($availability['no_checkout'] ?? []);

        if ($core) {
            $live = (new \App\Helper\LiveCart())->iCalDataCheckInCheckOutCheckinCheckout($core->id);
            foreach (($live['blocked'] ?? []) as $d) { $nights[$d] = true; }      // interior nights
            foreach (($live['checkin'] ?? []) as $d) { $nights[$d] = true; }      // arrival night = occupied
            // $live['checkout'] = day AFTER last night → NOT an occupied night
        }

        // Derive turnover from contiguous runs of occupied nights.
        $occupied = array_keys($nights);
        sort($occupied);
        $blocked = []; $noCheckin = []; $noCheckout = []; $run = [];
        $flush = function () use (&$run, &$blocked, &$noCheckin, &$noCheckout) {
            if (empty($run)) return;
            $noCheckin[]  = $run[0];                                            // first night = arrival day
            $noCheckout[] = date('Y-m-d', strtotime(end($run) . ' +1 day'));   // day after last = departure day
            foreach (array_slice($run, 1) as $interior) { $blocked[] = $interior; }
            $run = [];
        };
        foreach ($occupied as $night) {
            if (!empty($run) && $night !== date('Y-m-d', strtotime(end($run) . ' +1 day'))) { $flush(); }
            $run[] = $night;
        }
        $flush();

        // Re-apply PMS arrival/departure restrictions (not occupancy), dropping any
        // that are now fully blocked.
        $noCheckin  = array_values(array_unique(array_merge($noCheckin, $pmsNoCheckin)));
        $noCheckout = array_values(array_unique(array_merge($noCheckout, $pmsNoCheckout)));
        $noCheckin  = array_values(array_diff($noCheckin, $blocked));
        $noCheckout = array_values(array_diff($noCheckout, $blocked));

        // A day that is BOTH no-checkin and no-checkout can host no stay → block it.
        $both = array_intersect($noCheckin, $noCheckout);
        if (!empty($both)) {
            $blocked    = array_merge($blocked, $both);
            $noCheckin  = array_values(array_diff($noCheckin, $both));
            $noCheckout = array_values(array_diff($noCheckout, $both));
        }

        sort($noCheckin); sort($noCheckout);
        $blocked = array_values(array_unique($blocked));
        sort($blocked);

        $availability['blocked']     = $blocked;
        $availability['no_checkin']  = $noCheckin;
        $availability['no_checkout'] = $noCheckout;

        return $this->applyBookingWindowAndRateMinStay($availability, $room);
    }

    /**
     * Two PMS realities the synced calendar doesn't capture on its own:
     *
     *  1. MIN-STAY. pms_calendar rarely carries min_stay; Lodgify keeps it in the
     *     rate bands (lodgify_rate_calendar). We merge the most-permissive band
     *     min-stay per date so the picker enforces the REAL minimum, and also
     *     honour an admin-set Property::min_stay as a floor (editable lever for
     *     properties whose rates haven't been synced yet).
     *
     *  2. BOOKING WINDOW. A PMS only accepts bookings within a finite advance
     *     window (Lodgify ~1yr); past it every date is effectively blocked even
     *     though our calendar may hold rows further out. We cap the selectable
     *     horizon via `max_date`. Window length is the `booking_window_months`
     *     setting (default 12 = 1 year) so each owner can match their channel.
     */
    private function applyBookingWindowAndRateMinStay(array $availability, PmsRoom $room): array
    {
        if (($availability['ok'] ?? false) !== true) {
            return $availability;
        }

        // ── Per-date min-stay from the Lodgify rate calendar ──────────────────
        try {
            $byDate = (array) json_decode(json_encode($availability['min_stay_by_date'] ?? []), true);
            $globalMin = (int) ($availability['min_stay'] ?? 1);

            if ($room->pms_source === 'lodgify' && $room->pms_property_id && $room->pms_room_id) {
                $rows = \App\Models\Pms\LodgifyRateCalendar::where('property_id', (int) $room->pms_property_id)
                    ->where('room_type_id', (int) $room->pms_room_id)
                    ->whereDate('date', '>=', now()->toDateString())
                    ->get(['date', 'min_stay']);

                if ($rows->isNotEmpty()) {
                    $perDate = [];
                    foreach ($rows as $r) {
                        $ymd = $r->date instanceof \DateTimeInterface
                            ? $r->date->format('Y-m-d')
                            : substr((string) $r->date, 0, 10);
                        $ms = max(1, (int) $r->min_stay);
                        // A date can have several rate bands — keep the most permissive (smallest).
                        $perDate[$ymd] = isset($perDate[$ymd]) ? min($perDate[$ymd], $ms) : $ms;
                    }
                    foreach ($perDate as $ymd => $ms) {
                        $byDate[$ymd] = max((int) ($byDate[$ymd] ?? 1), $ms);
                    }
                    // Universal floor = smallest per-date min across the window, so the
                    // picker's single global minimum never over-restricts a laxer date.
                    if (!empty($perDate)) {
                        $globalMin = max($globalMin, min($perDate));
                    }
                }
            }

            // Admin-editable Property::min_stay acts as a floor (immediate lever when
            // the PMS rate sync hasn't populated this room yet).
            $core = \App\Models\Property::where('pms_room_id', $room->id)->first()
                 ?? \App\Models\Property::where('pms_room_id', $room->pms_room_id)->first();
            if ($core && (int) $core->min_stay > 1) {
                $globalMin = max($globalMin, (int) $core->min_stay);
            }

            $availability['min_stay']         = max(1, $globalMin);
            $availability['min_stay_by_date'] = (object) $byDate;
        } catch (\Throwable $e) {
            // Non-fatal: keep whatever min-stay we already had.
        }

        // ── Booking window cap ────────────────────────────────────────────────
        try {
            $months = (int) (\ModelHelper::getDataFromSetting('booking_window_months') ?: 12);
            if ($months < 1) {
                $months = 12;
            }
            $availability['max_date'] = now()->addMonthsNoOverflow($months)->toDateString();
        } catch (\Throwable $e) {
            $availability['max_date'] = now()->addMonthsNoOverflow(12)->toDateString();
        }

        return $availability;
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
    // api/v3.3/property-calendar actual response shape (confirmed live):
    //   { "calendars": [{ "entries": [
    //     { "date": "YYYY-MM-DD",
    //       "pricing": { "currency": "USD", "value": float },  ← key is "value" not "price"
    //       "availability": {
    //         "unavailable": bool,
    //         "unavailabilityReason": "BOOKING"|"BLOCK"|null,
    //         "availableForCheckIn": bool,   ← positive flag (invert for no_checkin)
    //         "availableForCheckOut": bool,  ← positive flag (invert for no_checkout)
    //         "minimumStayLength": int,      ← nested here, not top-level
    //         "maximumStayLength": int
    //       }
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
            $avail       = is_array($day['availability'] ?? null) ? $day['availability'] : [];
            $unavail     = (bool)($avail['unavailable'] ?? false);
            // pricing.value is the correct key (API returns "value", not "price")
            $price       = (float)($day['pricing']['value'] ?? $day['pricing']['price'] ?? $day['price'] ?? 0);
            // availableForCheckIn/Out are positive flags — invert for no_checkin/no_checkout
            $canCheckIn  = isset($avail['availableForCheckIn'])  ? (bool)$avail['availableForCheckIn']  : true;
            $canCheckOut = isset($avail['availableForCheckOut']) ? (bool)$avail['availableForCheckOut'] : true;
            return [
                'date'        => $day['date']                                          ?? null,
                'available'   => !$unavail,
                'no_checkin'  => !$canCheckIn,
                'no_checkout' => !$canCheckOut,
                'min_stay'    => (int)($avail['minimumStayLength'] ?? $day['minimumStay'] ?? 1),
                'max_stay'    => (int)($avail['maximumStayLength'] ?? $day['maximumStay'] ?? 0) ?: null,
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
            // Guesty calendar v1 returns closedOnArrival / closedOnDeparture at the
            // top level on some accounts, and inside a 'restrictions' sub-object on others.
            $restrictions = is_array($day['restrictions'] ?? null) ? $day['restrictions'] : [];
            return [
                'date'        => $day['date']       ?? null,
                'available'   => ($s === 'available'),
                'no_checkin'  => (bool)($day['closedOnArrival']
                                    ?? $restrictions['closedToCheckIn']
                                    ?? $restrictions['closedOnArrival']
                                    ?? false),
                'no_checkout' => (bool)($day['closedOnDeparture']
                                    ?? $restrictions['closedToCheckOut']
                                    ?? $restrictions['closedOnDeparture']
                                    ?? false),
                'min_stay'    => (int)($day['minNights'] ?? 1),
                'max_stay'    => isset($day['maxNights']) && $day['maxNights'] > 0 ? (int)$day['maxNights'] : null,
                'price'       => isset($day['price']) && $day['price'] > 0 ? (float)$day['price'] : null,
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

        $periods    = $raw['data'] ?? [];
        // Rate map injected by LodgifyAdapter::getAvailability() from lodgify_rate_calendar.
        // These are the LOWEST price_per_day per date across all rate bands.
        // The calendar displays "From $X" because Lodgify supports multiple stay-length bands.
        // Falls back to [] when no rates are stored yet for this range.
        $dailyRates = $raw['_daily_rates'] ?? [];
        // Signal to the frontend that these are "From" prices (multiple bands may exist)
        $hasRateBands = !empty($dailyRates);

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
            // For unavailable (booked) periods, period_end is the checkout date.
            // The checkout date itself must stay open — a new guest can arrive the
            // same day a previous guest departs (turnover day). Available periods
            // keep the <= boundary since their period_end is an inclusive open day.
            while ($isAvailable ? $cur <= $endDt : $cur < $endDt) {
                $dateStr = $cur->format('Y-m-d');
                $days[] = [
                    'date'        => $dateStr,
                    'available'   => $isAvailable,
                    'no_checkin'  => false,
                    'no_checkout' => false,
                    'min_stay'    => 1,
                    'max_stay'    => null,
                    'price'       => $dailyRates[$dateStr] ?? null,
                ];
                $cur->modify('+1 day');
            }
        }

        $result = $this->buildFromDays($days, fn($day) => $day);
        // Tell the frontend these are "From" prices — Lodgify can have multiple
        // rate bands per date (different min/max stay lengths).
        if ($hasRateBands) {
            $result['rates_from'] = true;
        }
        return $result;
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
