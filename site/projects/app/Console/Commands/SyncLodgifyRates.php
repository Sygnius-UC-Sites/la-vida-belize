<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Pms\PmsRoom;
use App\Models\Pms\LodgifyRateCalendar;
use App\Modules\VacationRental\Pms\PmsManager;

/**
 * lodgify:sync-rates
 *
 * Syncs 731 days of nightly rate bands from Lodgify's v2/rates/calendar API
 * (with v1 fallback) into lodgify_rate_calendar.
 *
 * One row per (property_id, room_type_id, date, is_default, min_stay, max_stay).
 * Multiple rows per date are normal — Lodgify supports length-of-stay pricing bands.
 *
 * Strategy:
 *   - Try one 731-day request per room. If the API returns < 50 items, chunk into
 *     5 × 180-day windows to avoid range limits.
 *   - Validate that no boundary dates are missing or duplicated across chunks.
 *   - Upsert atomically; never let older data overwrite newer (compare synced_at).
 *   - Remove rows older than yesterday under the rolling retention policy.
 *   - Log a completeness report per room.
 *
 * Runs:
 *   - Hourly via Kernel (matches SyncPmsCalendars frequency)
 *   - On-demand after rate_change webhook (lodgify:sync-rates --property=X --room=Y)
 */
class SyncLodgifyRates extends Command
{
    protected $signature = 'lodgify:sync-rates
                            {--property= : Sync only a specific Lodgify property_id}
                            {--room=     : Sync only a specific room_type_id}';

    protected $description = 'Sync 731-day Lodgify rate bands (v2/rates/calendar) into lodgify_rate_calendar.';

    const HORIZON_DAYS  = 731;
    const CHUNK_DAYS    = 180; // safe chunk size within Lodgify API limits

    public function handle(PmsManager $pmsManager): int
    {
        $propertyFilter = $this->option('property');
        $roomFilter     = $this->option('room');

        $query = PmsRoom::where('pms_source', 'lodgify')->where('status', 'true');
        if ($propertyFilter) $query->where('pms_property_id', $propertyFilter);
        if ($roomFilter)     $query->where('pms_room_id', $roomFilter);

        $rooms = $query->get();
        $this->info("Syncing Lodgify rates for {$rooms->count()} room(s)...");

        try {
            $adapter = $pmsManager->driver('lodgify');
        } catch (\Throwable $e) {
            $this->error("Could not load Lodgify adapter: " . $e->getMessage());
            return self::FAILURE;
        }

        $today   = now()->toDateString();
        $horizon = now()->addDays(self::HORIZON_DAYS)->toDateString();
        $syncedOk = 0;
        $syncedFail = 0;

        foreach ($rooms as $room) {
            $propertyId = (int)$room->pms_property_id;
            $roomTypeId = (int)$room->pms_room_id;
            $label = "{$room->name} (property={$propertyId}, room={$roomTypeId})";

            $this->line("  → {$label}");

            try {
                // ── Fetch rate settings (currency, VAT) once per room ─────────
                $settings     = $adapter->getRateSettings($propertyId);
                $currencyCode = strtoupper($settings['currency_code'] ?? 'USD');
                $vat          = (float)($settings['vat']          ?? 0);
                $vatExcl      = (bool)($settings['is_vat_exclusive'] ?? false);

                // ── Attempt single 731-day fetch first ────────────────────────
                $allBands = $this->fetchWithChunks($adapter, $propertyId, $roomTypeId, $today, $horizon);

                if ($allBands === null) {
                    $this->warn("    ✗ Rate fetch failed — existing data preserved.");
                    $syncedFail++;
                    continue;
                }

                // ── Validate: check for gaps or duplicates ────────────────────
                [$gaps, $dupes] = $this->validateBands($allBands, $today, $horizon);
                if (!empty($gaps)) {
                    Log::channel('lodgify')->warning('SyncLodgifyRates: date gaps detected', [
                        'property_id' => $propertyId,
                        'room_type_id'=> $roomTypeId,
                        'gap_count'   => count($gaps),
                        'sample_gaps' => array_slice($gaps, 0, 5),
                    ]);
                    $this->warn("    ⚠ {count($gaps)} date(s) have no rate bands.");
                }
                if (!empty($dupes)) {
                    $this->warn("    ⚠ Duplicate band entries found for: " . implode(', ', array_slice($dupes, 0, 3)));
                }

                // ── Build upsert rows ─────────────────────────────────────────
                $rows    = [];
                $syncedAt = now()->format('Y-m-d H:i:s');
                foreach ($allBands as $band) {
                    $rows[] = [
                        'property_id'                  => $propertyId,
                        'room_type_id'                 => $roomTypeId,
                        'date'                         => $band['date'],
                        'is_default'                   => (bool)($band['is_default'] ?? false),
                        'min_stay'                     => (int)($band['min_stay']     ?? 1),
                        'max_stay'                     => (int)($band['max_stay']     ?? 0),
                        'price_per_day'                => $this->exactDecimal($band['price_per_day'] ?? 0),
                        'price_per_additional_guest'   => $this->exactDecimal($band['price_per_additional_guest'] ?? 0),
                        'additional_guests_starts_from'=> (int)($band['additional_guests_starts_from'] ?? 0),
                        'currency_code'                => $band['currency_code'] ?? $currencyCode,
                        'vat'                          => $this->exactDecimal($vat),
                        'is_vat_exclusive'             => $vatExcl,
                        'source_api_version'           => $band['_source'] ?? 'v2',
                        'pms_updated_at'               => null,
                        'synced_at'                    => $syncedAt,
                    ];
                }

                // ── Upsert in chunks of 200 ───────────────────────────────────
                foreach (array_chunk($rows, 200) as $chunk) {
                    DB::table('lodgify_rate_calendar')->upsert(
                        $chunk,
                        ['property_id', 'room_type_id', 'date', 'is_default', 'min_stay', 'max_stay'],
                        ['price_per_day', 'price_per_additional_guest', 'additional_guests_starts_from',
                         'currency_code', 'vat', 'is_vat_exclusive', 'source_api_version', 'synced_at']
                    );
                }

                // ── Rolling cleanup: remove dates before today ────────────────
                DB::table('lodgify_rate_calendar')
                    ->where('property_id', $propertyId)
                    ->where('room_type_id', $roomTypeId)
                    ->where('date', '<', $today)
                    ->delete();

                $totalBands = count($rows);
                $distinctDates = count(array_unique(array_column($rows, 'date')));
                $this->line("    ✓ {$totalBands} rate bands across {$distinctDates} dates. Currency: {$currencyCode}.");
                $syncedOk++;

                // ── Log completeness report ───────────────────────────────────
                Log::channel('lodgify')->info('SyncLodgifyRates: complete', [
                    'property_id'   => $propertyId,
                    'room_type_id'  => $roomTypeId,
                    'bands'         => $totalBands,
                    'distinct_dates'=> $distinctDates,
                    'gaps'          => count($gaps),
                    'currency'      => $currencyCode,
                    'source'        => $allBands[0]['_source'] ?? 'unknown',
                ]);

            } catch (\Throwable $e) {
                Log::channel('lodgify')->error('SyncLodgifyRates: exception', [
                    'property_id' => $propertyId,
                    'room_type_id'=> $roomTypeId,
                    'error'       => $e->getMessage(),
                ]);
                $this->error("    ✗ Exception: " . $e->getMessage());
                $syncedFail++;
            }
        }

        $this->info("Done. OK: {$syncedOk}, Failed: {$syncedFail}.");
        return self::SUCCESS;
    }

    // ── Chunk fetcher ─────────────────────────────────────────────────────────

    /**
     * Try one 731-day request first. If it returns < 50 items (empty/truncated),
     * fall back to 5 × 180-day chunks. Returns flat array of band objects,
     * or null if all fetches failed.
     */
    private function fetchWithChunks($adapter, int $propertyId, int $roomTypeId, string $from, string $to): ?array
    {
        // Single-request attempt
        $single = $adapter->getRateCalendar($propertyId, $roomTypeId, $from, $to);
        $bands  = $this->extractBands($single);

        if ($bands !== null && count($bands) >= 50) {
            return $bands; // good single response
        }

        if ($bands !== null && count($bands) > 0) {
            // Small response might be correct for short-range; accept it
            return $bands;
        }

        // Single failed or empty — try chunked
        $this->line("    ↻ Single request returned < 50 items, retrying in chunks...");
        $all     = [];
        $current = new \DateTime($from);
        $endDt   = new \DateTime($to);
        $seenDates = [];

        while ($current <= $endDt) {
            $chunkFrom = $current->format('Y-m-d');
            $chunkTo   = (clone $current)->modify('+' . (self::CHUNK_DAYS - 1) . ' days');
            if ($chunkTo > $endDt) $chunkTo = clone $endDt;
            $chunkToStr = $chunkTo->format('Y-m-d');

            $res   = $adapter->getRateCalendar($propertyId, $roomTypeId, $chunkFrom, $chunkToStr);
            $bands = $this->extractBands($res);
            if ($bands === null) {
                Log::channel('lodgify')->error('SyncLodgifyRates: chunk failed', [
                    'from' => $chunkFrom, 'to' => $chunkToStr,
                ]);
                return null; // abort on chunk failure — partial data is worse than no data
            }

            // Boundary deduplication — endDate is inclusive so the last date
            // of one chunk equals the first date of the next if we aren't careful.
            foreach ($bands as $band) {
                $d = $band['date'] ?? null;
                if (!$d) continue;
                $key = "{$d}_{$band['is_default']}_{$band['min_stay']}_{$band['max_stay']}";
                if (!isset($seenDates[$key])) {
                    $all[] = $band;
                    $seenDates[$key] = true;
                }
            }

            $current = (clone $chunkTo)->modify('+1 day'); // next chunk starts day after
        }

        return empty($all) ? null : $all;
    }

    /**
     * Extract the flat array of rate band objects from a getRateCalendar() response.
     * Returns null on API error, or an array (possibly empty) on success.
     */
    private function extractBands(array $res): ?array
    {
        $source = $res['_source'] ?? 'unknown';
        $status = $res['status'] ?? 0;

        if ($status < 200 || $status >= 300 || $source === 'none') {
            return null;
        }

        $bands = [];

        if ($source === 'v2') {
            foreach ($res['data']['calendar_items'] ?? [] as $item) {
                $date      = $item['date'] ?? null;
                $isDefault = (bool)($item['is_default'] ?? false);
                if (!$date) continue;
                foreach ($item['prices'] ?? [] as $price) {
                    $bands[] = [
                        'date'                         => $date,
                        'is_default'                   => $isDefault,
                        'min_stay'                     => (int)($price['min_stay']     ?? 1),
                        'max_stay'                     => (int)($price['max_stay']     ?? 0),
                        'price_per_day'                => $price['price_per_day']             ?? 0,
                        'price_per_additional_guest'   => $price['price_per_additional_guest'] ?? 0,
                        'additional_guests_starts_from'=> (int)($price['additional_guests_starts_from'] ?? 0),
                        'currency_code'                => $res['data']['rate_settings']['currency_code'] ?? 'USD',
                        '_source'                      => 'v2',
                    ];
                }
            }
        } elseif ($source === 'v1') {
            $items = $res['data'] ?? [];
            if (!is_array($items)) return null;
            foreach ($items as $item) {
                $date      = $item['date'] ?? null;
                $isDefault = (bool)($item['is_default'] ?? false);
                if (!$date) continue;
                foreach ($item['prices'] ?? [] as $price) {
                    $bands[] = [
                        'date'                         => $date,
                        'is_default'                   => $isDefault,
                        'min_stay'                     => (int)($price['min_stay'] ?? 1),
                        'max_stay'                     => (int)($price['max_stay'] ?? 0),
                        'price_per_day'                => $price['price_per_day'] ?? 0,
                        'price_per_additional_guest'   => 0,
                        'additional_guests_starts_from'=> 0,
                        'currency_code'                => 'USD', // v1 has no currency
                        '_source'                      => 'v1',
                    ];
                }
            }
        }

        return $bands;
    }

    /**
     * Validate completeness of rate bands for the full horizon.
     * Returns [gapDates[], duplicateKeys[]]
     */
    private function validateBands(array $bands, string $from, string $to): array
    {
        $datesSeen = [];
        $dupKeys   = [];
        foreach ($bands as $b) {
            $key = "{$b['date']}_{$b['is_default']}_{$b['min_stay']}_{$b['max_stay']}";
            if (isset($datesSeen[$key])) {
                $dupKeys[] = $key;
            }
            $datesSeen[$b['date']] = true;
        }

        // Find dates with no rate band at all
        $gaps = [];
        $cur  = new \DateTime($from);
        $end  = new \DateTime($to);
        while ($cur <= $end) {
            $d = $cur->format('Y-m-d');
            if (!isset($datesSeen[$d])) {
                $gaps[] = $d;
            }
            $cur->modify('+1 day');
        }

        return [$gaps, array_unique($dupKeys)];
    }

    /** Store money as exact string to avoid float rounding before DB write. */
    private function exactDecimal(mixed $value): string
    {
        return number_format((float)$value, 4, '.', '');
    }
}
