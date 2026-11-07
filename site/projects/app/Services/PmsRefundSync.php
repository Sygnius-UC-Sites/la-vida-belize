<?php

namespace App\Services;

use App\Helper\PiiSanitizer;
use App\Models\BookingRequest;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Pms\PmsRoom;
use App\Modules\VacationRental\Pms\PmsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use ModelHelper;
use MailHelper;

/**
 * PmsRefundSync — records a Stripe refund in the PMS ledger.
 *
 * Used by BookingRequestController (admin-initiated refunds),
 * StripeWebhookController (Stripe-dashboard refunds), and
 * RetryPmsPaymentSync (automated retry of failed refunds).
 *
 * Safety guarantees:
 *   1. Creates or finds a PaymentRefund row by stripe_refund_id (UNIQUE constraint).
 *      A duplicate INSERT throws QueryException → treated as "already handled".
 *   2. Optimistic lock via status CAS: pending/failed → syncing.
 *      If another process already set it to syncing/synced, this call is a no-op.
 *   3. Adapter-level idempotency (Guesty: Idempotency-Key; Hostfully: transactionId)
 *      is the third defence — safe even if the lock is somehow bypassed.
 *   4. Failure is written to the PaymentRefund row, never reverses the Stripe refund.
 *   5. After MAX_RETRIES the row is left as 'failed' and an admin alert is sent.
 */
class PmsRefundSync
{
    public const MAX_RETRIES = 3;

    public function __construct(private PmsManager $pms) {}

    /**
     * Record a single Stripe refund in the PMS.
     *
     * Creates (or finds) a PaymentRefund row for $stripeRefundId,
     * acquires an optimistic lock, calls the PMS adapter, and persists
     * the result. Returns the final PaymentRefund row.
     *
     * @param Payment        $payment       The Stripe charge being refunded
     * @param BookingRequest $booking       Booking this payment belongs to
     * @param string         $stripeRefundId Stripe refund ID (re_xxx) — UNIQUE key
     * @param float          $refundAmount  Refund amount in major currency units
     * @param string         $stripeChargeId Original Stripe charge/PI (for cross-reference)
     */
    public function syncRefund(
        Payment        $payment,
        BookingRequest $booking,
        string         $stripeRefundId,
        float          $refundAmount,
        string         $stripeChargeId = ''
    ): PaymentRefund {
        // ── Step 1: Create or find the PaymentRefund row ──────────────────────
        try {
            $refundRow = PaymentRefund::firstOrCreate(
                ['stripe_refund_id' => $stripeRefundId],
                [
                    'payment_id'      => $payment->id,
                    'amount'          => $refundAmount,
                    'currency'        => $booking->currency ?? 'USD',
                    'pms_sync_status' => 'pending',
                    'retry_count'     => 0,
                ]
            );
        } catch (QueryException $e) {
            // UNIQUE constraint violation: another process created this row concurrently.
            // Fetch it and return — the other process will handle (or has handled) the sync.
            $refundRow = PaymentRefund::where('stripe_refund_id', $stripeRefundId)->firstOrFail();
            Log::channel('lodgify')->info('PmsRefundSync: duplicate row race — returning existing', [
                'stripe_refund_id' => $stripeRefundId,
                'existing_status'  => $refundRow->pms_sync_status,
            ]);
            return $refundRow;
        }

        // ── Step 2: Skip if already resolved ─────────────────────────────────
        if (in_array($refundRow->pms_sync_status, ['synced', 'not_supported', 'syncing'])) {
            Log::channel('lodgify')->info('PmsRefundSync: already handled — skipping', [
                'refund_row_id'    => $refundRow->id,
                'stripe_refund_id' => $stripeRefundId,
                'status'           => $refundRow->pms_sync_status,
            ]);
            return $refundRow;
        }

        // ── Step 3: Optimistic lock — CAS pending/failed → syncing ────────────
        // If two processes race, only one update returns 1 affected row.
        $locked = DB::table('payment_refunds')
            ->where('id', $refundRow->id)
            ->whereIn('pms_sync_status', ['pending', 'failed'])
            ->update(['pms_sync_status' => 'syncing']);

        if (!$locked) {
            Log::channel('lodgify')->info('PmsRefundSync: lock not acquired — another process is handling', [
                'refund_row_id' => $refundRow->id,
            ]);
            return $refundRow->fresh();
        }

        // ── Step 4: Retry guard ───────────────────────────────────────────────
        $refundRow->refresh();
        if ($refundRow->retry_count >= self::MAX_RETRIES) {
            $refundRow->update(['pms_sync_status' => 'failed']); // reset from syncing
            $this->sendExhaustionAlert($refundRow, $booking, $payment);
            return $refundRow;
        }

        // ── Step 5: Call PMS adapter ──────────────────────────────────────────
        $room = PmsRoom::find($booking->property_id);
        $pms  = $room?->pms_source ?? '';

        if (empty($pms) || empty($booking->pms_booking_id)) {
            $refundRow->update([
                'pms_sync_status' => 'refund_pending_manual',
                'pms_sync_error'  => 'No pms_source or pms_booking_id on booking',
                'retry_count'     => $refundRow->retry_count,
            ]);
            return $refundRow->fresh();
        }

        try {
            $adapter  = $this->pms->driver($pms);
            $currency = $booking->currency ?? 'USD';
            $result   = null;

            if ($pms === 'guesty') {
                $result = $adapter->recordRefund(
                    $booking->pms_booking_id,
                    $refundAmount,
                    $currency,
                    $stripeRefundId,
                    $stripeChargeId
                );

            } elseif ($pms === 'hostfully') {
                $orderUid   = $booking->pms_order_uid ?? null;
                // pms_payment_id on the Payment row is the Hostfully SALE transaction UID —
                // the exact transaction being refunded. One per payment record, not one per booking.
                $origTxnUid = $payment->pms_payment_id ?? null;

                if (!$orderUid || !$origTxnUid) {
                    $missing = !$orderUid ? 'pms_order_uid' : 'pms_payment_id';
                    $refundRow->update([
                        'pms_sync_status' => 'refund_pending_manual',
                        'pms_sync_error'  => "Hostfully refund skipped: {$missing} not set — cannot supply referenceTransactionId",
                        'retry_count'     => $refundRow->retry_count + 1,
                    ]);
                    Log::channel('lodgify')->warning('PmsRefundSync: Hostfully skipped', [
                        'refund_row_id' => $refundRow->id,
                        'missing'       => $missing,
                    ]);
                    return $refundRow->fresh();
                }

                $result = $adapter->recordRefund($orderUid, $refundAmount, $stripeRefundId, $origTxnUid);

            } else {
                // Hostaway: no confirmed external-refund endpoint.
                // Lodgify: not confirmed.
                $refundRow->update([
                    'pms_sync_status' => 'refund_pending_manual',
                    'pms_sync_error'  => "{$pms}: no confirmed external-refund endpoint. Stripe refund: {$stripeRefundId}",
                    'retry_count'     => $refundRow->retry_count,
                ]);
                Log::channel('lodgify')->warning("PmsRefundSync: {$pms} refund requires manual reconciliation", [
                    'refund_row_id' => $refundRow->id,
                    'pms_booking'   => $booking->pms_booking_id,
                ]);
                return $refundRow->fresh();
            }

            // ── Step 6: Persist result ────────────────────────────────────────
            $ok           = $result['ok'] ?? false;
            $pmsRefundId  = $result['payment_id'] ?? $result['transaction_uid'] ?? null;
            $newStatus    = $ok ? 'synced' : 'failed';
            $newError     = $ok ? null : ($result['error'] ?? 'recordRefund returned ok=false');
            $newRetry     = $refundRow->retry_count + ($ok ? 0 : 1);

            $refundRow->update([
                'pms_sync_status' => $newStatus,
                'pms_refund_id'   => $pmsRefundId,
                'pms_sync_error'  => $newError,
                'retry_count'     => $newRetry,
            ]);

            Log::channel('lodgify')->info("PmsRefundSync: {$pms} refund {$newStatus}", [
                'refund_row_id' => $refundRow->id,
                'pms_refund_id' => $pmsRefundId,
                'error'         => $newError,
            ]);

            if (!$ok && $newRetry >= self::MAX_RETRIES) {
                $this->sendExhaustionAlert($refundRow->fresh(), $booking, $payment);
            }

        } catch (\Throwable $e) {
            $newRetry = $refundRow->retry_count + 1;
            $refundRow->update([
                'pms_sync_status' => 'failed',
                'pms_sync_error'  => 'Exception: ' . $e->getMessage(),
                'retry_count'     => $newRetry,
            ]);
            Log::channel('lodgify')->error('PmsRefundSync: exception', [
                'refund_row_id' => $refundRow->id,
                'pms'           => $pms,
                'error'         => $e->getMessage(),
            ]);
            if ($newRetry >= self::MAX_RETRIES) {
                $this->sendExhaustionAlert($refundRow->fresh(), $booking, $payment);
            }
        }

        return $refundRow->fresh();
    }

    /**
     * Retry all PaymentRefund rows with pms_sync_status = 'failed' and retry_count < MAX_RETRIES.
     * Returns ['synced' => N, 'failed' => N, 'exhausted' => N, 'skipped' => N].
     */
    public function retryFailedRefunds(bool $dryRun = false): array
    {
        $counts = ['synced' => 0, 'failed' => 0, 'exhausted' => 0, 'skipped' => 0];

        PaymentRefund::retryable(self::MAX_RETRIES)
            ->with('payment.bookingRequest')
            ->get()
            ->each(function (PaymentRefund $refundRow) use (&$counts, $dryRun) {
                $payment = $refundRow->payment;
                $booking = $payment?->bookingRequest;

                if (!$payment || !$booking) {
                    $counts['skipped']++;
                    return;
                }

                if ($dryRun) {
                    $counts['skipped']++;
                    return;
                }

                $result = $this->syncRefund(
                    $payment,
                    $booking,
                    $refundRow->stripe_refund_id,
                    (float)$refundRow->amount,
                );

                match ($result->pms_sync_status) {
                    'synced'                => $counts['synced']++,
                    'failed'                => $result->retry_count >= self::MAX_RETRIES
                                                ? $counts['exhausted']++
                                                : $counts['failed']++,
                    default                 => $counts['skipped']++,
                };
            });

        return $counts;
    }

    /**
     * Send an admin alert when a refund row exceeds MAX_RETRIES without syncing.
     * Non-fatal — if the email fails, logs the error.
     */
    private function sendExhaustionAlert(PaymentRefund $refundRow, BookingRequest $booking, Payment $payment): void
    {
        try {
            $adminEmail = ModelHelper::getDataFromSetting('payment_receiving_mail');
            if (empty($adminEmail)) return;

            $room = PmsRoom::find($booking->property_id);
            $pms  = $room?->pms_source ?? 'unknown';

            $subject = "Action Required: PMS Refund Sync Failed — Booking #{$booking->id}";
            $html    = "<p><strong>A Stripe refund could not be recorded in {$pms} after " . self::MAX_RETRIES . " attempts.</strong></p>"
                     . "<table cellpadding='6' border='1'>"
                     . "<tr><td>Booking ID</td><td>{$booking->id}</td></tr>"
                     . "<tr><td>PMS</td><td>{$pms}</td></tr>"
                     . "<tr><td>PMS Booking ID</td><td>{$booking->pms_booking_id}</td></tr>"
                     . "<tr><td>Stripe Refund</td><td><code>{$refundRow->stripe_refund_id}</code></td></tr>"
                     . "<tr><td>Amount</td><td>{$refundRow->amount} {$refundRow->currency}</td></tr>"
                     . "<tr><td>Last Error</td><td>" . PiiSanitizer::clean((string)$refundRow->pms_sync_error) . "</td></tr>"
                     . "</table>"
                     . "<p>The Stripe refund has already been issued. Please update the {$pms} ledger manually.</p>";

            MailHelper::emailSenderByController($html, $adminEmail, $subject);

            Log::channel('lodgify')->info('PmsRefundSync: exhaustion alert sent', [
                'refund_row_id' => $refundRow->id,
                'admin'         => $adminEmail,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('PmsRefundSync: exhaustion alert failed', [
                'refund_row_id' => $refundRow->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
