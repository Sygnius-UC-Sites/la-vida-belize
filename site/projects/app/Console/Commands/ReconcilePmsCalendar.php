<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pms\PmsRoom;
use App\Models\Pms\PmsCalendar;
use App\Modules\VacationRental\Pms\PmsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use MailHelper;
use ModelHelper;

/**
 * pms:reconcile-calendar
 *
 * Daily job that compares the local pms_calendar table against a fresh PMS
 * API fetch for every active room, and reports discrepancies.
 *
 * Checks per date:
 *   - State (available / unavailable)
 *   - Minimum stay
 *   - Nightly rate (within a configurable tolerance)
 *   - Closed-to-arrival / closed-to-departure
 *
 * Discrepancies are:
 *   1. Written to the pms_calendar_discrepancies table (if it exists, else logged only).
 *   2. Emailed to the admin address for Critical/High severity items.
 *
 * Run schedule: daily at 04:00 (off-peak) — registered in Kernel.php.
 *
 * A temporary API failure is distinguished from a real discrepancy: if the
 * PMS returns a non-200 status, the room is skipped and counted as an error
 * rather than flagged as a discrepancy.
 *
 * --days option: number of days to check (default 14 — a 2-week sample
 * is fast enough for a daily run; full 731-day checks run weekly via --full).
 * --full: check all 731 days (slow — use for weekly/manual audits).
 * --provider: limit to one PMS provider.
 * --room: limit to one pms_room_id.
 */
class ReconcilePmsCalendar extends Command
{
    protected $signature = 'pms:reconcile-calendar
                            {--days=14    : Number of days to sample (default 14)}
                            {--full       : Check all 731 days (overrides --days)}
                            {--provider=  : Limit to a specific PMS provider}
                            {--room=      : Limit to a specific pms_room_id}';

    protected $description = 'Compare local pms_calendar data against the live PMS API and report discrepancies.';

    // Tolerance for nightly rate differences (in currency units).
    // Differences below this threshold are logged but not alerted.
    const RATE_TOLERANCE = 1.00;

    public function handle(PmsManager $pmsManager): int
    {
        $days           = $this->option('full') ? 731 : (int)$this->option('days');
        $providerFilter = $this->option('provider');
        $roomFilter     = $this->option('room');

        $today   = now()->toDateString();
        $horizon = now()->addDays($days)->toDateString();

        $query = PmsRoom::where('status', 'true');
        if ($providerFilter) $query->where('pms_source', $providerFilter);
        if ($roomFilter)     $query->where('pms_room_id', $roomFilter);
        $rooms = $query->get();

        $this->info("Reconciling {$rooms->count()} rooms over {$days} days ({$today} → {$horizon})...");

        $allDiscrepancies = [];
        $errors           = 0;

        foreach ($rooms as $room) {
            $label = "{$room->pms_source} / {$room->name} ({$room->pms_room_id})";

            try {
                $adapter = $pmsManager->driver($room->pms_source);
                $res = $adapter->getAvailability([
                    'property_id' => $room->pms_property_id,
                    'room_id'     => $room->pms_room_id,
                    'checkin'     => $today,
                    'checkout'    => $horizon,
                    'adults'      => 1,
                ]);

                if (($res['status'] ?? 0) < 200 || ($res['status'] ?? 0) >= 300) {
                    $this->warn("  ✗ API error for {$label} (status {$res['status']}) — skipped.");
                    $errors++;
                    continue;
                }

                $discrepancies = $this->compare($res, $room, $today, $horizon);

                if (!empty($discrepancies)) {
                    $allDiscrepancies = array_merge($allDiscrepancies, $discrepancies);
                    $count = count($discrepancies);
                    $this->warn("  ⚠ {$label}: {$count} discrepancy(-ies) found.");
                } else {
                    $this->line("  ✓ {$label}: no discrepancies.");
                }

            } catch (\Throwable $e) {
                $this->error("  ✗ Exception for {$label}: {$e->getMessage()}");
                Log::channel('lodgify')->error('ReconcilePmsCalendar: exception', [
                    'room'  => $room->pms_room_id,
                    'pms'   => $room->pms_source,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }
        }

        // ── Persist and alert ─────────────────────────────────────────────────
        $this->persistDiscrepancies($allDiscrepancies);
        $this->alertIfNeeded($allDiscrepancies, $days);

        $total = count($allDiscrepancies);
        $this->info("Reconciliation complete. Discrepancies: {$total}. API errors: {$errors}.");
        return self::SUCCESS;
    }

    // ── Compare live PMS data vs local pms_calendar ───────────────────────────

    private function compare(array $res, PmsRoom $room, string $from, string $to): array
    {
        $pms     = $room->pms_source;
        $rawDays = $this->extractDays($res, $pms);

        // Build a map of live PMS data: date → ['state', 'min_stay', 'rate', 'cta', 'ctd']
        $live = [];
        foreach ($rawDays as $day) {
            $date = $day['date'] ?? null;
            if (!$date || $date < $from || $date > $to) continue;
            $live[$date] = $this->parseLiveDay($day, $pms);
        }

        // Load local pms_calendar rows for the same date range
        $local = PmsCalendar::where('pms_source', $pms)
            ->where('pms_room_id', $room->pms_room_id)
            ->whereBetween('date', [$from, $to])
            ->get()
            ->keyBy(fn($r) => $r->date->format('Y-m-d'));

        $discrepancies = [];
        $now = now()->toDateTimeString();

        foreach ($live as $date => $liveDay) {
            $localRow = $local->get($date);

            // Missing from local: pms_calendar has no row for this date
            if (!$localRow) {
                $discrepancies[] = $this->disc($room, $date, 'missing_local', null, 'Row missing from pms_calendar', 'critical', $now);
                continue;
            }

            // Unknown state: local row was never written with real data
            if ($localRow->state === PmsCalendar::STATE_UNKNOWN) {
                $discrepancies[] = $this->disc($room, $date, 'state_unknown', 'unknown', $liveDay['state'], 'critical', $now);
                continue;
            }

            // State mismatch
            $localState = $localRow->state === PmsCalendar::STATE_AVAILABLE ? 'available' : 'unavailable';
            if ($localState !== $liveDay['state']) {
                $severity = ($liveDay['state'] === 'available' && $localState === 'unavailable')
                    ? 'high'    // we're blocking a date the PMS says is open
                    : 'critical'; // we're showing a date as open that the PMS has blocked
                $discrepancies[] = $this->disc($room, $date, 'availability', $localState, $liveDay['state'], $severity, $now);
            }

            // Min-stay mismatch
            $localMin = $localRow->min_stay ?? 1;
            $liveMin  = $liveDay['min_stay'] ?? 1;
            if ($localMin !== $liveMin) {
                $discrepancies[] = $this->disc($room, $date, 'min_stay', $localMin, $liveMin, 'medium', $now);
            }

            // Rate mismatch (only flag if both sides have a value)
            if ($localRow->nightly_rate !== null && $liveDay['rate'] !== null) {
                $diff = abs((float)$localRow->nightly_rate - (float)$liveDay['rate']);
                if ($diff > self::RATE_TOLERANCE) {
                    $discrepancies[] = $this->disc($room, $date, 'nightly_rate', (float)$localRow->nightly_rate, $liveDay['rate'], 'high', $now);
                }
            }

            // CTA / CTD mismatches
            if ((bool)$localRow->closed_to_arrival !== (bool)$liveDay['cta']) {
                $discrepancies[] = $this->disc($room, $date, 'closed_to_arrival', (int)$localRow->closed_to_arrival, (int)$liveDay['cta'], 'high', $now);
            }
            if ((bool)$localRow->closed_to_departure !== (bool)$liveDay['ctd']) {
                $discrepancies[] = $this->disc($room, $date, 'closed_to_departure', (int)$localRow->closed_to_departure, (int)$liveDay['ctd'], 'high', $now);
            }
        }

        // Check for local rows that have no matching live data (horizon gap)
        foreach ($local as $date => $localRow) {
            if (!isset($live[$date]) && $localRow->state === PmsCalendar::STATE_AVAILABLE) {
                // Available locally but not in PMS response — could be a horizon mismatch
                $discrepancies[] = $this->disc($room, $date, 'missing_live', 'available', 'not_returned', 'low', $now);
            }
        }

        return $discrepancies;
    }

    private function parseLiveDay(array $day, string $pms): array
    {
        // State
        $status = strtolower($day['status'] ?? '');
        if ($status === 'available') {
            $state = 'available';
        } elseif (in_array($status, ['unavailable', 'booked', 'blocked'])) {
            $state = 'unavailable';
        } elseif (array_key_exists('isAvailable', $day)) {
            $state = $day['isAvailable'] ? 'available' : 'unavailable';
        } elseif (isset($day['availability']['unavailable'])) {
            $state = $day['availability']['unavailable'] ? 'unavailable' : 'available';
        } elseif (array_key_exists('available', $day)) {
            $state = $day['available'] ? 'available' : 'unavailable';
        } else {
            $state = 'unknown';
        }

        // Rate
        $price = match ($pms) {
            'hostfully' => $day['pricing']['price'] ?? $day['price'] ?? null,
            default     => $day['price'] ?? null,
        };
        $rate = ($price !== null && $price > 0) ? (float)$price : null;

        // CTA/CTD
        $r = is_array($day['restrictions'] ?? null) ? $day['restrictions'] : [];
        $cta = (bool)($day['closedOnArrival']   ?? $r['closedToCheckIn']  ?? $r['closedOnArrival']   ?? false);
        $ctd = (bool)($day['closedOnDeparture'] ?? $r['closedToCheckOut'] ?? $r['closedOnDeparture'] ?? false);

        return [
            'state'    => $state,
            'min_stay' => (int)($day['minimumStay'] ?? $day['minNights'] ?? 1),
            'rate'     => $rate,
            'cta'      => $cta,
            'ctd'      => $ctd,
        ];
    }

    private function extractDays(array $res, string $pms): array
    {
        $data = $res['data'] ?? [];
        return match ($pms) {
            'guesty'    => $data['data']   ?? (isset($data[0]) ? $data : []),
            'hostaway'  => $data['result'] ?? [],
            'hostfully' => (function () use ($data) {
                $e = [];
                foreach ($data['calendars'] ?? [] as $cal) {
                    foreach ($cal['entries'] ?? [] as $entry) { $e[] = $entry; }
                }
                return $e;
            })(),
            'lodgify'   => $this->expandLodgifyPeriods($data),
            default     => [],
        };
    }

    private function expandLodgifyPeriods(array $periods): array
    {
        $days = [];
        foreach ($periods as $period) {
            $start = $period['period_start'] ?? null;
            $end   = $period['period_end']   ?? null;
            $avail = (bool)($period['is_available'] ?? true);
            if (!$start || !$end) continue;
            $cur   = new \DateTime($start);
            $endDt = new \DateTime($end);
            while ($avail ? $cur <= $endDt : $cur < $endDt) {
                $days[] = ['date' => $cur->format('Y-m-d'), 'available' => $avail, 'status' => $avail ? 'available' : 'unavailable'];
                $cur->modify('+1 day');
            }
        }
        return $days;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function disc(PmsRoom $room, string $date, string $field, $localVal, $liveVal, string $severity, string $now): array
    {
        return [
            'pms_source'      => $room->pms_source,
            'pms_property_id' => $room->pms_property_id,
            'pms_room_id'     => $room->pms_room_id,
            'property_name'   => $room->name,
            'date'            => $date,
            'field'           => $field,
            'local_value'     => $localVal,
            'live_value'      => $liveVal,
            'severity'        => $severity,
            'detected_at'     => $now,
        ];
    }

    private function persistDiscrepancies(array $discrepancies): void
    {
        if (empty($discrepancies)) return;

        // Write to pms_calendar_discrepancies table if it exists
        if (!DB::getSchemaBuilder()->hasTable('pms_calendar_discrepancies')) {
            Log::channel('lodgify')->warning('ReconcilePmsCalendar: pms_calendar_discrepancies table does not exist — logging discrepancies only.');
            foreach ($discrepancies as $d) {
                Log::channel('lodgify')->warning('Calendar discrepancy', $d);
            }
            return;
        }

        foreach (array_chunk($discrepancies, 100) as $chunk) {
            DB::table('pms_calendar_discrepancies')->insert($chunk);
        }
    }

    private function alertIfNeeded(array $discrepancies, int $days): void
    {
        $critical = array_filter($discrepancies, fn($d) => in_array($d['severity'], ['critical', 'high']));
        if (empty($critical)) return;

        $count = count($critical);

        // Group by PMS for a readable email
        $byPms = [];
        foreach ($critical as $d) {
            $byPms[$d['pms_source']][] = $d;
        }

        $lines = ["PMS Calendar Reconciliation found {$count} critical/high discrepancy(-ies) over {$days} days.\n"];
        foreach ($byPms as $pms => $items) {
            $lines[] = strtoupper($pms) . " (" . count($items) . " issues):";
            foreach (array_slice($items, 0, 20) as $d) {
                $lines[] = "  [{$d['severity']}] {$d['property_name']} / {$d['date']} / {$d['field']}: local={$d['local_value']} live={$d['live_value']}";
            }
            if (count($items) > 20) {
                $lines[] = "  ... and " . (count($items) - 20) . " more.";
            }
            $lines[] = '';
        }

        $body    = implode("\n", $lines);
        $subject = "[PMS Alert] {$count} calendar discrepancies detected";

        try {
            $adminEmail = ModelHelper::getDataFromSetting('payment_receiving_mail');
            if ($adminEmail) {
                MailHelper::emailSenderByController(
                    nl2br(htmlspecialchars($body)),
                    $adminEmail,
                    $subject
                );
            }
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('ReconcilePmsCalendar: alert email failed', ['error' => $e->getMessage()]);
        }

        Log::channel('lodgify')->warning('ReconcilePmsCalendar: alert', [
            'critical_count' => $count,
            'subject'        => $subject,
        ]);
    }
}
