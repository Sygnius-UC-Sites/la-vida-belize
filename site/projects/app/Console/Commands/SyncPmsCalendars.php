<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pms\PmsRoom;
use App\Models\Pms\PmsCalendar;
use App\Models\Pms\PmsUnavailableDate;
use App\Models\Pms\PmsProperty;
use App\Modules\VacationRental\Pms\PmsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * pms:sync-calendars
 *
 * For every active room in every PMS:
 *   1. Fetch the rolling 731-day availability window from the PMS API.
 *   2. Write one row per date to pms_calendar (upsert — idempotent).
 *   3. Mirror blocked dates to pms_unavailable_dates (legacy datepicker table).
 *   4. Remove pms_unavailable_dates rows for dates now re-opened by the PMS.
 *   5. Remove pms_calendar rows older than yesterday (rolling cleanup).
 *
 * Using 731 (not 730) days ensures that:
 *   - Leap years are covered without an off-by-one.
 *   - Inclusive end-date ranges cover a full two years when the start is today.
 *
 * State values in pms_calendar:
 *   -1 = unknown  (not yet fetched — must never be bookable)
 *    0 = unavailable
 *    1 = available
 *
 * NOTE: pms_rooms.status is stored as the string 'true' or 'false' (varchar).
 * The query must use WHERE status = 'true', NOT WHERE status = 1 or TRUE.
 */
class SyncPmsCalendars extends Command
{
    protected $signature = 'pms:sync-calendars
                            {--provider= : Sync only a specific provider}
                            {--room=     : Sync only a specific pms_room_id}';

    protected $description = 'Fetch 731-day availability + rates + restrictions for all active PMS rooms and write to pms_calendar.';

    // Horizon: 731 days (covers two full years inclusive, handles leap years)
    const HORIZON_DAYS = 731;

    public function handle(PmsManager $pmsManager): int
    {
        $providerFilter = $this->option('provider');
        $roomFilter     = $this->option('room');

        // ── IMPORTANT: status is stored as varchar 'true'/'false', not boolean 1/0 ──
        $query = PmsRoom::where('status', 'true');
        if ($providerFilter) $query->where('pms_source', $providerFilter);
        if ($roomFilter)     $query->where('pms_room_id', $roomFilter);

        $rooms = $query->get();
        $this->info("Syncing calendars for {$rooms->count()} active rooms (horizon: " . self::HORIZON_DAYS . " days)...");

        $today    = now()->toDateString();
        $horizon  = now()->addDays(self::HORIZON_DAYS)->toDateString();

        $totalSynced  = 0;
        $totalFailed  = 0;

        foreach ($rooms as $room) {
            $label = "{$room->pms_source} / {$room->name} ({$room->pms_room_id})";
            $this->line("  → {$label}");

            try {
                $adapter = $pmsManager->driver($room->pms_source);

                // ── Fetch availability from PMS ───────────────────────────────
                // Some PMS APIs have a per-request date-range limit.
                // We chunk into 90-day windows to stay well within limits and join safely.
                $allDays = $this->fetchInChunks($adapter, $room, $today, $horizon);

                if ($allDays === null) {
                    // API returned an error — do not wipe existing data, just skip
                    $this->warn("    ✗ API error — existing calendar data preserved.");
                    $totalFailed++;
                    continue;
                }

                // ── Lodgify: overlay nightly rates (not in availability response) ──
                // v1/availability returns period-based availability only — no per-day price.
                // We make a separate v1/rates call to get nightly prices and merge them in.
                if ($room->pms_source === 'lodgify') {
                    $allDays = $this->overlayLodgifyRates($adapter, $room->pms_property_id, $today, $horizon, $allDays);
                }

                // ── Normalize and upsert into pms_calendar ────────────────────
                $rows = $this->normalize($allDays, $room, $today, $horizon);
                $this->upsertCalendar($rows, $room);

                // ── Mirror to pms_unavailable_dates (legacy, for datepicker) ──
                $this->syncUnavailableDates($rows, $room);

                // ── Rolling cleanup: remove rows before today ─────────────────
                PmsCalendar::where('pms_source', $room->pms_source)
                    ->where('pms_room_id', $room->pms_room_id)
                    ->where('date', '<', $today)
                    ->delete();

                $synced = count($rows);
                $this->line("    ✓ {$synced} dates written.");
                $totalSynced++;

            } catch (\Throwable $e) {
                Log::channel('lodgify')->error('SyncPmsCalendars: exception', [
                    'room'  => $room->pms_room_id,
                    'pms'   => $room->pms_source,
                    'error' => $e->getMessage(),
                ]);
                $this->error("    ✗ Exception: {$e->getMessage()}");
                $totalFailed++;
                // Continue — one failing room must not abort the entire run.
            }
        }

        $this->info("Done. Synced: {$totalSynced} rooms, Failed: {$totalFailed} rooms.");
        return self::SUCCESS;
    }

    // ── Chunk fetcher ─────────────────────────────────────────────────────────

    /**
     * Fetch availability in 90-day chunks to respect per-request range limits.
     * Returns a flat array of day objects, or null on API failure.
     */
    private function fetchInChunks($adapter, PmsRoom $room, string $from, string $to): ?array
    {
        $allDays  = [];
        $current  = new \DateTime($from);
        $end      = new \DateTime($to);
        $chunkLen = 90; // days per API request (safe for all four PMS)

        while ($current <= $end) {
            $chunkStart = $current->format('Y-m-d');
            $chunkEnd   = (clone $current)->modify("+{$chunkLen} days");
            if ($chunkEnd > $end) $chunkEnd = clone $end;
            $chunkEndStr = $chunkEnd->format('Y-m-d');

            $res = $adapter->getAvailability([
                'property_id' => $room->pms_property_id,
                'room_id'     => $room->pms_room_id,
                'checkin'     => $chunkStart,
                'checkout'    => $chunkEndStr,
                'adults'      => 1,
            ]);

            $httpStatus = $res['status'] ?? 0;
            if ($httpStatus < 200 || $httpStatus >= 300) {
                Log::channel('lodgify')->warning('SyncPmsCalendars: chunk fetch failed', [
                    'pms'    => $room->pms_source,
                    'room'   => $room->pms_room_id,
                    'chunk'  => "{$chunkStart} → {$chunkEndStr}",
                    'status' => $httpStatus,
                    'error'  => $res['error'] ?? null,
                ]);
                return null; // Fail the whole property — partial data is worse than stale
            }

            $days = $this->extractDays($res, $room->pms_source);
            $allDays = array_merge($allDays, $days);

            $current->modify("+{$chunkLen} days")->modify('+1 day');
        }

        return $allDays;
    }

    /**
     * Extract the per-day array from the raw adapter response, normalizing for PMS differences.
     */
    private function extractDays(array $res, string $pms): array
    {
        $data = $res['data'] ?? [];
        return match ($pms) {
            'guesty'     => $data['data']   ?? (isset($data[0]) ? $data : []),
            'hostaway'   => $data['result'] ?? [],
            'hostfully'  => $this->extractHostfullyDays($data),
            'lodgify'    => $this->expandLodgifyPeriods($data),
            default      => [],
        };
    }

    private function extractHostfullyDays(array $data): array
    {
        $entries = [];
        foreach ($data['calendars'] ?? [] as $cal) {
            foreach ($cal['entries'] ?? [] as $entry) {
                $entries[] = $entry;
            }
        }
        return $entries;
    }

    /**
     * Lodgify returns period objects, not per-day objects.
     * Expand each period into individual day rows.
     * For unavailable periods, period_end is the checkout date (not blocked).
     */
    private function expandLodgifyPeriods(array $periods): array
    {
        $days = [];
        foreach ($periods as $period) {
            $start       = $period['period_start'] ?? null;
            $end         = $period['period_end']   ?? null;
            $isAvailable = (bool)($period['is_available'] ?? true);
            if (!$start || !$end) continue;

            $cur   = new \DateTime($start);
            $endDt = new \DateTime($end);

            while ($isAvailable ? $cur <= $endDt : $cur < $endDt) {
                $days[] = [
                    'date'        => $cur->format('Y-m-d'),
                    'available'   => $isAvailable,
                    'isAvailable' => $isAvailable ? 1 : 0,
                    'status'      => $isAvailable ? 'available' : 'unavailable',
                    '_source'     => 'lodgify_period',
                ];
                $cur->modify('+1 day');
            }
        }
        return $days;
    }

    // ── Lodgify rate overlay ──────────────────────────────────────────────────

    /**
     * Fetch Lodgify nightly rates separately and merge them into the expanded day objects.
     *
     * Lodgify's v1/availability response contains no per-day price. We call v1/rates
     * in the same 90-day chunks used for availability, build a date→price map, then
     * inject 'price' into each day object so normalize() can pick it up normally.
     *
     * If the rates endpoint is unavailable (UnsupportedApiVersion or plan restriction),
     * the days are returned unchanged — nightly_rate stays null rather than crashing.
     */
    private function overlayLodgifyRates($adapter, string $propertyId, string $from, string $to, array $days): array
    {
        if (!method_exists($adapter, 'getDailyRates')) return $days;

        try {
            $rateMap = $adapter->getDailyRates($propertyId, $from, $to);
            if (empty($rateMap)) return $days;

            // Inject price into each expanded day object
            foreach ($days as &$day) {
                $date = $day['date'] ?? null;
                if ($date && isset($rateMap[$date])) {
                    $day['price'] = $rateMap[$date];
                }
            }
            unset($day);

        } catch (\Throwable $e) {
            Log::channel('lodgify')->info('SyncPmsCalendars: Lodgify rate overlay failed — rates will be null', [
                'property_id' => $propertyId,
                'error'       => $e->getMessage(),
            ]);
        }

        return $days;
    }

    // ── Normalizer ────────────────────────────────────────────────────────────

    /**
     * Convert raw PMS day objects into pms_calendar rows.
     * Dates not returned by the PMS (beyond its horizon) are left as state=-1 (unknown).
     */
    private function normalize(array $rawDays, PmsRoom $room, string $from, string $to): array
    {
        $pms      = $room->pms_source;
        $currency = $room->currency ?: null;
        $syncedAt = now()->format('Y-m-d H:i:s');
        $rows     = [];

        foreach ($rawDays as $day) {
            $date = $day['date'] ?? null;
            if (!$date || $date < $from || $date > $to) continue;

            $state = $this->parseState($day, $pms);
            $row = [
                'pms_source'          => $pms,
                'pms_property_id'     => $room->pms_property_id,
                'pms_room_id'         => $room->pms_room_id,
                'date'                => $date,
                'state'               => $state,
                'inventory'           => $this->parseInventory($day, $pms),
                'nightly_rate'        => $this->parseRate($day, $pms),
                'currency'            => $currency,
                'min_stay'            => $this->parseMinStay($day, $pms),
                'max_stay'            => $this->parseMaxStay($day, $pms),
                'closed_to_arrival'   => $this->parseCta($day, $pms),
                'closed_to_departure' => $this->parseCtd($day, $pms),
                'stop_sell'           => $this->parseStopSell($day, $pms),
                'pms_updated_at'      => $this->parsePmsUpdatedAt($day, $pms),
                'synced_at'           => $syncedAt,
            ];

            $rows[$date] = $row; // keyed by date to auto-deduplicate overlapping chunks
        }

        return array_values($rows);
    }

    private function parseState(array $day, string $pms): int
    {
        // Guesty, Hostaway: status field
        $status = strtolower($day['status'] ?? '');
        if ($status === 'unavailable' || $status === 'booked' || $status === 'blocked') {
            return PmsCalendar::STATE_UNAVAILABLE;
        }
        if ($status === 'available') {
            return PmsCalendar::STATE_AVAILABLE;
        }

        // Hostaway: isAvailable field
        if (array_key_exists('isAvailable', $day)) {
            return $day['isAvailable'] ? PmsCalendar::STATE_AVAILABLE : PmsCalendar::STATE_UNAVAILABLE;
        }

        // Hostfully: availability.unavailable
        if (isset($day['availability']['unavailable'])) {
            return $day['availability']['unavailable'] ? PmsCalendar::STATE_UNAVAILABLE : PmsCalendar::STATE_AVAILABLE;
        }

        // Lodgify expanded: available field
        if (array_key_exists('available', $day)) {
            return $day['available'] ? PmsCalendar::STATE_AVAILABLE : PmsCalendar::STATE_UNAVAILABLE;
        }

        return PmsCalendar::STATE_UNKNOWN;
    }

    private function parseInventory(array $day, string $pms): ?int
    {
        // Only Hostaway surfaces a unit count in the calendar
        if (isset($day['availableCount'])) return (int)$day['availableCount'];
        return null;
    }

    private function parseRate(array $day, string $pms): ?float
    {
        $price = match ($pms) {
            'hostfully' => $day['pricing']['price'] ?? $day['price'] ?? null,
            default     => $day['price'] ?? null,
        };
        return ($price !== null && $price > 0) ? (float)$price : null;
    }

    private function parseMinStay(array $day, string $pms): ?int
    {
        $v = $day['minimumStay'] ?? $day['minNights'] ?? $day['min_nights'] ?? null;
        return ($v !== null && (int)$v > 0) ? (int)$v : null;
    }

    private function parseMaxStay(array $day, string $pms): ?int
    {
        $v = $day['maximumStay'] ?? $day['maxNights'] ?? null;
        return ($v !== null && (int)$v > 0) ? (int)$v : null;
    }

    private function parseCta(array $day, string $pms): bool
    {
        $r = is_array($day['restrictions'] ?? null) ? $day['restrictions'] : [];
        return (bool)($day['closedOnArrival']
            ?? $r['closedToCheckIn'] ?? $r['closedOnArrival']
            ?? false);
    }

    private function parseCtd(array $day, string $pms): bool
    {
        $r = is_array($day['restrictions'] ?? null) ? $day['restrictions'] : [];
        return (bool)($day['closedOnDeparture']
            ?? $r['closedToCheckOut'] ?? $r['closedOnDeparture']
            ?? false);
    }

    private function parseStopSell(array $day, string $pms): bool
    {
        return (bool)($day['stopSell'] ?? $day['stop_sell'] ?? false);
    }

    private function parsePmsUpdatedAt(array $day, string $pms): ?string
    {
        $v = $day['updatedAt'] ?? $day['updated_at'] ?? $day['lastModified'] ?? null;
        if (!$v) return null;
        try {
            return (new \DateTime($v))->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── DB writers ────────────────────────────────────────────────────────────

    /**
     * Bulk upsert calendar rows. Uses chunked DB::table()->upsert() to avoid
     * hitting MySQL's max_allowed_packet limit on large property calendars.
     */
    private function upsertCalendar(array $rows, PmsRoom $room): void
    {
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('pms_calendar')->upsert(
                $chunk,
                ['pms_source', 'pms_room_id', 'date'],  // unique keys
                [   // columns to update on conflict
                    'state', 'inventory', 'nightly_rate', 'currency',
                    'min_stay', 'max_stay', 'closed_to_arrival', 'closed_to_departure',
                    'stop_sell', 'pms_updated_at', 'synced_at',
                ]
            );
        }
    }

    /**
     * Mirror unavailable dates to the legacy pms_unavailable_dates table.
     * The datepicker still reads from this table, so we keep it in sync.
     * Only deletes rows for dates we have fresh data for — stale rows for
     * dates beyond the API horizon are left untouched.
     */
    private function syncUnavailableDates(array $rows, PmsRoom $room): void
    {
        $pmsProperty = PmsProperty::where('pms_property_id', $room->pms_property_id)
            ->where('pms_source', $room->pms_source)
            ->first();

        if (!$pmsProperty) return;

        $propertyId = $pmsProperty->id;

        // Build the set of dates we have fresh data for (any non-unknown state)
        $knownDates     = [];
        $unavailDates   = [];
        foreach ($rows as $row) {
            if ($row['state'] === PmsCalendar::STATE_UNKNOWN) continue;
            $knownDates[] = $row['date'];
            if ($row['state'] === PmsCalendar::STATE_UNAVAILABLE) {
                $unavailDates[] = $row['date'];
            }
        }

        if (empty($knownDates)) return;

        // Remove rows for dates that are now available (clear stale blocks)
        DB::table('pms_unavailable_dates')
            ->where('property_id', $propertyId)
            ->whereIn('un_date', $knownDates)
            ->whereNotIn('un_date', $unavailDates)
            ->delete();

        // Insert rows for newly unavailable dates (ignore duplicates)
        $toInsert = array_map(fn($d) => ['property_id' => $propertyId, 'un_date' => $d], $unavailDates);
        foreach (array_chunk($toInsert, 200) as $chunk) {
            DB::table('pms_unavailable_dates')->insertOrIgnore($chunk);
        }
    }
}
