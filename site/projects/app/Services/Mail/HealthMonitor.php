<?php

namespace App\Services\Mail;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tracks delivery / bounce / complaint events in a rolling window and
 * auto-pauses outbound sending when thresholds are exceeded — tighter than
 * SES's own 5%/0.1% so this install pauses before AWS does.
 *
 * Events come from two places: SesMailer::send() records `delivery` after a
 * successful API call; the SES webhook records `bounce` / `complaint` events
 * when SNS notifies us.
 */
class HealthMonitor
{
    public function __construct(private SesSettings $settings) {}

    public function recordDelivery(?string $email, ?string $messageId): void
    {
        $this->insert('delivery', $email, $messageId);
    }

    public function recordBounce(?string $email, ?string $messageId): void
    {
        $this->insert('bounce', $email, $messageId);
        $this->evaluateAndMaybePause();
    }

    public function recordComplaint(?string $email, ?string $messageId): void
    {
        $this->insert('complaint', $email, $messageId);
        $this->evaluateAndMaybePause();
    }

    /** @return array{deliveries:int, bounces:int, complaints:int, bounce_rate:float, complaint_rate:float, sample_size:int} */
    public function rollingStats(?int $minutesAgo = null): array
    {
        $minutes = $minutesAgo ?? (int) config('mailer.health.window_minutes', 1440);
        $since = now()->subMinutes($minutes);

        $rows = DB::table('mail_health_events')
            ->where('occurred_at', '>=', $since)
            ->selectRaw('event_type, COUNT(*) as cnt')
            ->groupBy('event_type')
            ->pluck('cnt', 'event_type')
            ->toArray();

        $deliveries = (int) ($rows['delivery'] ?? 0);
        $bounces    = (int) ($rows['bounce'] ?? 0);
        $complaints = (int) ($rows['complaint'] ?? 0);
        $sample     = $deliveries + $bounces;

        return [
            'deliveries'     => $deliveries,
            'bounces'        => $bounces,
            'complaints'     => $complaints,
            'sample_size'    => $sample,
            'bounce_rate'    => $sample > 0 ? round($bounces / $sample, 6) : 0.0,
            'complaint_rate' => $deliveries > 0 ? round($complaints / $deliveries, 6) : 0.0,
        ];
    }

    private function evaluateAndMaybePause(): void
    {
        $stats = $this->rollingStats();
        $minSample = (int) config('mailer.health.min_sample_size', 50);
        if ($stats['sample_size'] < $minSample) {
            return;
        }

        $reason = null;
        if ($stats['bounce_rate'] >= $this->settings->bounceRateThreshold()) {
            $reason = sprintf('bounce_rate %.4f exceeded threshold %.4f over %d events',
                $stats['bounce_rate'], $this->settings->bounceRateThreshold(), $stats['sample_size']);
        } elseif ($stats['complaint_rate'] >= $this->settings->complaintRateThreshold()) {
            $reason = sprintf('complaint_rate %.6f exceeded threshold %.6f over %d events',
                $stats['complaint_rate'], $this->settings->complaintRateThreshold(), $stats['sample_size']);
        }

        if ($reason !== null && !$this->settings->sendDisabled()) {
            $this->settings->put('mail_send_disabled', '1');
            try {
                Log::channel((string) config('mailer.log_channel', 'mail'))
                    ->error('mail_send_auto_disabled', ['reason' => $reason] + $stats);
            } catch (\Throwable $e) {
                Log::error('mail_send_auto_disabled (fallback)', ['reason' => $reason] + $stats);
            }
        }
    }

    private function insert(string $type, ?string $email, ?string $messageId): void
    {
        DB::table('mail_health_events')->insert([
            'event_type'     => $type,
            'email'          => $email ? strtolower(trim($email)) : null,
            'ses_message_id' => $messageId,
            'occurred_at'    => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
}
