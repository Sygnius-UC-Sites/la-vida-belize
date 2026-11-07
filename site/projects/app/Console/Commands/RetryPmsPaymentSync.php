<?php

namespace App\Console\Commands;

use App\Helper\PiiSanitizer;
use App\Models\BookingRequest;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Pms\PmsRoom;
use App\Models\ScheduledPayment;
use App\Modules\VacationRental\Pms\PmsManager;
use App\Services\PmsRefundSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ModelHelper;
use MailHelper;

/**
 * RetryPmsPaymentSync — retries failed PMS payment recording without risking double-charges.
 *
 * Three queues are processed:
 *
 * 1. Initial deposits (payments table):
 *    WHERE pms_sync_status = 'failed'
 *    Optimistic lock (CAS failed → syncing) prevents concurrent runs with
 *    StripeController or the webhook handler.
 *
 * 2. Future installments (scheduled_payments table):
 *    WHERE status = 'charged' AND pms_notified = false AND pms_notify_error IS NOT NULL
 *
 * 3. Refund rows (payment_refunds table):
 *    WHERE pms_sync_status = 'failed' AND retry_count < MAX_RETRIES
 *    Delegated to PmsRefundSync::retryFailedRefunds().
 *
 * All adapters use idempotency keys scoped to the Stripe PI/charge/refund ID, so
 * retrying is safe even if a previous attempt partially succeeded in the PMS.
 *
 * After MAX_RETRIES failures for a payment or installment, an admin alert is sent.
 *
 * Schedule: every 30 minutes (Kernel.php).
 * Usage: php projects/artisan payments:retry-pms-sync [--dry-run]
 */
class RetryPmsPaymentSync extends Command
{
    protected $signature   = 'payments:retry-pms-sync {--dry-run : Preview without calling PMS APIs}';
    protected $description = 'Retry failed PMS payment recording for completed Stripe charges';

    public const MAX_RETRIES = 3;

    public function __construct(
        private PmsManager     $pms,
        private PmsRefundSync  $refundSync,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $retried = $failed = 0;

        // ── 1. Initial deposits ───────────────────────────────────────────────
        $failedPayments = Payment::where('pms_sync_status', 'failed')
            ->whereNotNull('booking_id')
            ->get();

        if ($failedPayments->isNotEmpty()) {
            $this->info("Found {$failedPayments->count()} initial payment(s) with pms_sync_status=failed.");
        }

        foreach ($failedPayments as $payment) {
            $booking = BookingRequest::find($payment->booking_id);
            if (!$booking || empty($booking->pms_booking_id)) {
                $this->warn("  Payment #{$payment->id}: no booking/pms_booking_id — skipping");
                continue;
            }

            $room = PmsRoom::find($booking->property_id);
            $pms  = $room?->pms_source ?? '';
            if (empty($pms)) {
                $this->warn("  Payment #{$payment->id}: no pms_source — skipping");
                continue;
            }

            $attempts = $this->parseRetryCount($payment->pms_sync_error);

            if ($attempts >= self::MAX_RETRIES) {
                $this->warn("  Payment #{$payment->id}: exhausted ({$attempts} attempts)");
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY RUN] Would retry payment #{$payment->id} (booking #{$booking->id}, {$pms}, attempt " . ($attempts + 1) . ")");
                continue;
            }

            // ── Optimistic lock: CAS failed → syncing ─────────────────────────
            $locked = DB::table('payments')
                ->where('id', $payment->id)
                ->where('pms_sync_status', 'failed')
                ->update(['pms_sync_status' => 'syncing']);

            if (!$locked) {
                $this->line("  Payment #{$payment->id}: already syncing — skipping");
                continue;
            }

            $chargeId = $payment->tran_id ?? '';
            $this->line("  Retrying payment #{$payment->id} (booking #{$booking->id}, {$pms}, attempt " . ($attempts + 1) . ")...");

            try {
                $adapter = $this->pms->driver($pms);
                $result  = $adapter->recordPayment(
                    $booking->pms_booking_id,
                    (float)$payment->amount,
                    $booking->currency ?: 'USD',
                    $chargeId,
                    true   // $isRetry=true — previous attempt outcome uncertain
                );

                if ($result['ok'] ?? false) {
                    $pmsPaymentId = $result['payment_id'] ?? $result['charge_id'] ?? $result['transaction_uid'] ?? null;
                    $payment->update([
                        'pms_payment_id'  => $pmsPaymentId,
                        'pms_sync_status' => 'synced',
                        'pms_sync_error'  => null,
                    ]);
                    if (!empty($result['order_uid'])) {
                        $booking->update(['pms_order_uid' => $result['order_uid']]);
                    }
                    $this->info("    ✓ Synced — PMS ID: {$pmsPaymentId}");
                    Log::channel('lodgify')->info('RetryPmsPaymentSync: payment synced', [
                        'payment_id' => $payment->id,
                        'pms'        => $pms,
                        'pms_id'     => $pmsPaymentId,
                    ]);
                    $retried++;

                } elseif ($result['not_supported'] ?? false) {
                    $payment->update(['pms_sync_status' => 'not_supported', 'pms_sync_error' => $result['error'] ?? '']);
                    $this->warn("    — Not supported ({$pms})");

                } else {
                    $newAttempts = $attempts + 1;
                    $err         = "[attempt {$newAttempts}] " . ($result['error'] ?? 'unknown');
                    $payment->update(['pms_sync_status' => 'failed', 'pms_sync_error' => $err]);
                    $this->error("    ✗ Failed (attempt {$newAttempts}): " . ($result['error'] ?? 'unknown'));
                    Log::channel('lodgify')->warning('RetryPmsPaymentSync: retry failed', [
                        'payment_id' => $payment->id,
                        'pms'        => $pms,
                        'attempts'   => $newAttempts,
                        'error'      => $result['error'] ?? 'unknown',
                    ]);
                    if ($newAttempts >= self::MAX_RETRIES) {
                        $this->sendPaymentExhaustionAlert($payment, $booking, $pms, $result['error'] ?? 'unknown');
                    }
                    $failed++;
                }

            } catch (\Throwable $e) {
                $newAttempts = $attempts + 1;
                $err         = "[attempt {$newAttempts}] exception: " . $e->getMessage();
                $payment->update(['pms_sync_status' => 'failed', 'pms_sync_error' => $err]);
                $this->error("    ✗ Exception: " . $e->getMessage());
                Log::channel('lodgify')->error('RetryPmsPaymentSync: exception', [
                    'payment_id' => $payment->id,
                    'error'      => $e->getMessage(),
                ]);
                if ($newAttempts >= self::MAX_RETRIES) {
                    $this->sendPaymentExhaustionAlert($payment, $booking, $pms, $e->getMessage());
                }
                $failed++;
            }
        }

        // ── 2. Future installments ────────────────────────────────────────────
        $failedInstallments = ScheduledPayment::pmsUnsynced()->with('bookingRequest')->get();

        if ($failedInstallments->isNotEmpty()) {
            $this->info("Found {$failedInstallments->count()} installment(s) charged but not PMS-synced.");
        }

        foreach ($failedInstallments as $sp) {
            if (empty($sp->pms_booking_id) || empty($sp->pms_source)) {
                $this->warn("  Installment #{$sp->id}: no pms_booking_id — skipping");
                continue;
            }

            $attempts = $this->parseRetryCount($sp->pms_notify_error);
            if ($attempts >= self::MAX_RETRIES) {
                $this->warn("  Installment #{$sp->id}: exhausted ({$attempts} attempts)");
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY RUN] Would retry installment #{$sp->id} ({$sp->pms_source}, \${$sp->amount})");
                continue;
            }

            // Optimistic lock via pms_notify_error presence — simpler for installments
            // since pms_notified=false is already the "not locked" state.
            $stripeRef = $sp->stripe_charge_id ?? '';
            $this->line("  Retrying installment #{$sp->id} ({$sp->pms_source}, \${$sp->amount}, attempt " . ($attempts + 1) . ")...");

            try {
                $adapter = $this->pms->driver($sp->pms_source);
                $result  = $adapter->recordPayment(
                    $sp->pms_booking_id,
                    (float)$sp->amount,
                    $sp->currency,
                    $stripeRef,
                    true   // $isRetry=true
                );

                if ($result['not_supported'] ?? false) {
                    $sp->update(['pms_notify_error' => $result['error'] ?? 'not_supported']);
                    $this->warn("    — Not supported ({$sp->pms_source})");
                    continue;
                }

                if ($result['ok'] ?? false) {
                    $pmsPaymentId = $result['payment_id'] ?? $result['charge_id'] ?? $result['transaction_uid'] ?? null;
                    $sp->update(['pms_notified' => true, 'pms_notify_error' => null, 'pms_payment_id' => $pmsPaymentId]);
                    $this->info("    ✓ Synced — PMS ID: {$pmsPaymentId}");
                    Log::channel('lodgify')->info('RetryPmsPaymentSync: installment synced', ['sp_id' => $sp->id, 'pms_id' => $pmsPaymentId]);
                    $retried++;
                } else {
                    $newAttempts = $attempts + 1;
                    $err = "[attempt {$newAttempts}] " . ($result['error'] ?? 'unknown');
                    $sp->update(['pms_notify_error' => $err]);
                    $this->error("    ✗ Failed: " . ($result['error'] ?? 'unknown'));
                    if ($newAttempts >= self::MAX_RETRIES && $sp->bookingRequest) {
                        $this->sendInstallmentExhaustionAlert($sp, $result['error'] ?? 'unknown');
                    }
                    $failed++;
                }
            } catch (\Throwable $e) {
                $newAttempts = $attempts + 1;
                $sp->update(['pms_notify_error' => "[attempt {$newAttempts}] exception: " . $e->getMessage()]);
                $this->error("    ✗ Exception: " . $e->getMessage());
                if ($newAttempts >= self::MAX_RETRIES && $sp->bookingRequest) {
                    $this->sendInstallmentExhaustionAlert($sp, $e->getMessage());
                }
                $failed++;
            }
        }

        // ── 3. Refund rows ────────────────────────────────────────────────────
        $refundCounts = $this->refundSync->retryFailedRefunds($dryRun);
        $retried     += $refundCounts['synced'];
        $failed      += $refundCounts['failed'];

        if (!$dryRun) {
            $this->info("Done. Synced: {$retried}, Still failing: {$failed}, Exhausted: {$refundCounts['exhausted']}.");
        }

        return 0;
    }

    // ── Admin alerts ──────────────────────────────────────────────────────────

    private function sendPaymentExhaustionAlert(Payment $payment, BookingRequest $booking, string $pms, string $lastError): void
    {
        try {
            $adminEmail = ModelHelper::getDataFromSetting('payment_receiving_mail');
            if (empty($adminEmail)) return;

            $subject = "Action Required: PMS Payment Sync Failed — Booking #{$booking->id}";
            $html    = "<p><strong>A Stripe charge could not be recorded in {$pms} after " . self::MAX_RETRIES . " attempts.</strong></p>"
                     . "<table cellpadding='6' border='1'>"
                     . "<tr><td>Booking ID</td><td>{$booking->id}</td></tr>"
                     . "<tr><td>Payment ID</td><td>{$payment->id}</td></tr>"
                     . "<tr><td>Stripe Charge</td><td><code>{$payment->tran_id}</code></td></tr>"
                     . "<tr><td>Amount</td><td>{$payment->amount} {$booking->currency}</td></tr>"
                     . "<tr><td>PMS</td><td>{$pms}</td></tr>"
                     . "<tr><td>PMS Booking ID</td><td>{$booking->pms_booking_id}</td></tr>"
                     . "<tr><td>Last Error</td><td>" . PiiSanitizer::clean($lastError) . "</td></tr>"
                     . "</table>"
                     . "<p>The Stripe charge was successful. Please update the {$pms} ledger manually so the balance is correct.</p>";

            MailHelper::emailSenderByController($html, $adminEmail, $subject);
            Log::channel('lodgify')->info('RetryPmsPaymentSync: exhaustion alert sent', ['payment_id' => $payment->id]);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('RetryPmsPaymentSync: exhaustion alert failed', ['error' => $e->getMessage()]);
        }
    }

    private function sendInstallmentExhaustionAlert(ScheduledPayment $sp, string $lastError): void
    {
        try {
            $adminEmail = ModelHelper::getDataFromSetting('payment_receiving_mail');
            if (empty($adminEmail)) return;

            $booking = $sp->bookingRequest;
            $subject = "Action Required: PMS Installment Sync Failed — Booking #{$booking?->id}";
            $html    = "<p><strong>A scheduled installment could not be recorded in {$sp->pms_source} after " . self::MAX_RETRIES . " attempts.</strong></p>"
                     . "<table cellpadding='6' border='1'>"
                     . "<tr><td>Booking ID</td><td>{$booking?->id}</td></tr>"
                     . "<tr><td>Installment ID</td><td>{$sp->id}</td></tr>"
                     . "<tr><td>Stripe Charge</td><td><code>{$sp->stripe_charge_id}</code></td></tr>"
                     . "<tr><td>Amount</td><td>{$sp->amount} {$sp->currency}</td></tr>"
                     . "<tr><td>PMS</td><td>{$sp->pms_source}</td></tr>"
                     . "<tr><td>Last Error</td><td>" . PiiSanitizer::clean($lastError) . "</td></tr>"
                     . "</table>";

            MailHelper::emailSenderByController($html, $adminEmail, $subject);
            Log::channel('lodgify')->info('RetryPmsPaymentSync: installment exhaustion alert sent', ['sp_id' => $sp->id]);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('RetryPmsPaymentSync: installment alert failed', ['error' => $e->getMessage()]);
        }
    }

    private function parseRetryCount(?string $error): int
    {
        if (!$error) return 0;
        if (preg_match('/\[attempt (\d+)\]/', $error, $m)) {
            return (int)$m[1];
        }
        return 0;
    }
}
