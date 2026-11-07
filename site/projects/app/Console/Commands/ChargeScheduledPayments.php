<?php

namespace App\Console\Commands;

use App\Models\ScheduledPayment;
use App\Modules\VacationRental\Pms\PmsManager;
use App\Helper\StripeHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe;
use ModelHelper;
use MailHelper;

/**
 * ChargeScheduledPayments — daily cron command that:
 *   1. Finds scheduled_payments that are due (due_date <= today, status = pending)
 *   2. Charges the guest's saved Stripe Customer (off-session, no new card input needed)
 *   3. On success → marks the row charged + calls the PMS adapter's recordPayment()
 *   4. On failure → increments attempts; after MAX_ATTEMPTS sets status = failed
 *
 * Register in Kernel: $schedule->command('payments:charge-scheduled')->dailyAt('09:00');
 *
 * Run manually:  php projects/artisan payments:charge-scheduled
 * Dry run:       php projects/artisan payments:charge-scheduled --dry-run
 */
class ChargeScheduledPayments extends Command
{
    protected $signature   = 'payments:charge-scheduled {--dry-run : Preview due payments without charging}';
    protected $description = 'Charge installment payments that are due today or overdue';

    private const MAX_ATTEMPTS = 3;

    public function __construct(private PmsManager $pms)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        // ── 7-day advance reminders ───────────────────────────────────────────
        // Send a heads-up email to guests whose payment is due in exactly 7 days.
        $reminderDate = now()->addDays(7)->toDateString();
        $upcoming = ScheduledPayment::where('status', 'pending')
            ->where('due_date', $reminderDate)
            ->where('reminder_sent', false)
            ->with('bookingRequest')
            ->get();

        foreach ($upcoming as $sp) {
            $this->sendReminderEmail($sp);
        }

        if ($upcoming->isNotEmpty()) {
            $this->info("Sent {$upcoming->count()} reminder email(s) for payments due on {$reminderDate}.");
        }

        // ── Charge due payments ───────────────────────────────────────────────
        $due    = ScheduledPayment::due()->with('bookingRequest')->get();

        if ($due->isEmpty()) {
            $this->info('No payments due today.');
            return 0;
        }

        $this->info("Found {$due->count()} due payment(s)" . ($dryRun ? ' (DRY RUN)' : '') . '.');

        Stripe\Stripe::setApiKey(ModelHelper::getDataFromSetting('stripe_secret_key'));

        $charged = $failed = 0;

        foreach ($due as $sp) {
            $context = [
                'scheduled_payment_id' => $sp->id,
                'booking_id'           => $sp->booking_request_id,
                'pms'                  => $sp->pms_source,
                'pms_booking_id'       => $sp->pms_booking_id,
                'amount'               => $sp->amount,
                'currency'             => $sp->currency,
                'due_date'             => $sp->due_date->toDateString(),
            ];

            if ($dryRun) {
                $this->line("  [DRY RUN] Would charge \${$sp->amount} {$sp->currency} for booking #{$sp->booking_request_id} (due {$sp->due_date->toDateString()})");
                continue;
            }

            // ── Stripe off-session charge ──────────────────────────────────────
            // New bookings have stripe_payment_method_id (PaymentIntents flow).
            // Old bookings only have stripe_customer_id (legacy Charges flow).
            try {
                if (!empty($sp->stripe_payment_method_id)) {
                    // ── New flow: PaymentIntents off-session ───────────────────
                    $pi = Stripe\PaymentIntent::create([
                        'amount'         => StripeHelper::toStripeAmount((float)$sp->amount, $sp->currency),
                        'currency'       => strtolower($sp->currency),
                        'customer'       => $sp->stripe_customer_id,
                        'payment_method' => $sp->stripe_payment_method_id,
                        'off_session'    => true,
                        'confirm'        => true,
                        'description'    => "Scheduled installment — booking #{$sp->booking_request_id}",
                    ]);

                    if ($pi->status !== 'succeeded') {
                        throw new \RuntimeException('PaymentIntent not succeeded: ' . $pi->status);
                    }

                    $sp->update([
                        'status'          => 'charged',
                        'charged_at'      => now(),
                        'stripe_charge_id'=> $pi->id,
                        'attempts'        => $sp->attempts + 1,
                        'error_message'   => null,
                    ]);
                } else {
                    // ── Legacy flow: Charges API (old bookings without PM ID) ──
                    $charge     = Stripe\Charge::create([
                        'customer'    => $sp->stripe_customer_id,
                        'amount'      => StripeHelper::toStripeAmount((float)$sp->amount, $sp->currency),
                        'currency'    => strtolower($sp->currency),
                        'description' => "Scheduled installment — booking #{$sp->booking_request_id}",
                    ]);
                    $chargeJson = $charge->jsonSerialize();

                    if (!($chargeJson['paid'] ?? false) || !empty($chargeJson['failure_code'])) {
                        throw new \RuntimeException(
                            'Stripe charge returned unpaid: ' . ($chargeJson['failure_message'] ?? 'unknown')
                        );
                    }

                    $sp->update([
                        'status'           => 'charged',
                        'charged_at'       => now(),
                        'stripe_charge_id' => $chargeJson['id'],
                        'attempts'         => $sp->attempts + 1,
                        'error_message'    => null,
                    ]);
                }

                $chargeRef = $sp->stripe_charge_id; // pi_xxx or ch_xxx
                $this->info("  ✓ Charged \${$sp->amount} for booking #{$sp->booking_request_id}");
                Log::channel('lodgify')->info('ChargeScheduledPayments: charged', $context + ['charge_ref' => $chargeRef]);
                $charged++;

                // ── Notify PMS ─────────────────────────────────────────────────
                $this->notifyPms($sp);

            } catch (\Stripe\Exception\CardException $e) {
                $this->handleFailure($sp, 'Card declined: ' . $e->getError()->message, $context);
                $failed++;
            } catch (\Throwable $e) {
                $this->handleFailure($sp, $e->getMessage(), $context);
                $failed++;
            }
        }

        if (!$dryRun) {
            $this->info("Done. Charged: {$charged}, Failed: {$failed}.");
        }

        return 0;
    }

    /**
     * Tell the PMS that a payment was collected, so its ledger stays in sync.
     *
     * Safety rules:
     * - pms_notified=true means already recorded — skip to prevent duplicate PMS rows.
     *   Idempotency via Stripe PI/charge ID is the second line of defence at the adapter.
     * - Failure here NEVER reverses or repeats the Stripe charge.
     * - Lodgify: returns not_supported — log and continue; manual reconciliation required.
     */
    private function notifyPms(ScheduledPayment $sp): void
    {
        if (in_array(strtolower((string) $sp->pms_source), ['crm', 'direct', 'none'], true)) {
            $sp->update(['pms_notified' => true, 'pms_notify_error' => null]);
            $this->line('    ↳ No PMS notification required for direct booking');
            return;
        }

        if (empty($sp->pms_booking_id) || empty($sp->pms_source)) {
            $sp->update(['pms_notified' => false, 'pms_notify_error' => 'No pms_booking_id or pms_source']);
            return;
        }

        // Duplicate prevention: skip if already recorded in the PMS.
        if ($sp->pms_notified) {
            $this->line("    ↳ Already notified PMS ({$sp->pms_source}) — skipping");
            return;
        }

        // Pass the Stripe charge/PI ID so adapters can scope idempotency keys to it.
        $stripeRef = $sp->stripe_charge_id ?? '';  // pi_xxx (new flow) or ch_xxx (legacy)

        try {
            $adapter = $this->pms->driver($sp->pms_source);
            $result  = $adapter->recordPayment(
                $sp->pms_booking_id,
                (float)$sp->amount,
                $sp->currency,
                $stripeRef
            );

            if ($result['not_supported'] ?? false) {
                // Lodgify: no confirmed endpoint — log for manual reconciliation.
                $msg = $result['error'] ?? 'PMS does not support external payment recording';
                $sp->update(['pms_notified' => false, 'pms_notify_error' => $msg]);
                $this->warn("    ↳ PMS sync not supported ({$sp->pms_source}) — manual reconciliation required");
                Log::channel('lodgify')->info('ChargeScheduledPayments: PMS recordPayment not supported', [
                    'id'  => $sp->id,
                    'pms' => $sp->pms_source,
                ]);
                return;
            }

            if ($result['ok'] ?? false) {
                $pmsPaymentId = $result['payment_id']       // Guesty
                              ?? $result['charge_id']        // Hostaway
                              ?? $result['transaction_uid']  // Hostfully
                              ?? null;

                // Store PMS ID on THIS installment row — not on the booking.
                // Each payment/installment must carry its own PMS ID so refunds
                // can reference the exact transaction and retries remain idempotent.
                $sp->update([
                    'pms_notified'     => true,
                    'pms_notify_error' => null,
                    'pms_payment_id'   => $pmsPaymentId,
                ]);

                $this->line("    ↳ PMS notified ({$sp->pms_source})" . ($pmsPaymentId ? " — {$pmsPaymentId}" : ''));
            } else {
                $err = $result['error'] ?? 'PMS recordPayment returned ok=false';
                $sp->update(['pms_notified' => false, 'pms_notify_error' => $err]);
                $this->warn("    ↳ PMS notification failed ({$sp->pms_source}): {$err}");
                Log::channel('lodgify')->warning('ChargeScheduledPayments: PMS notify failed', [
                    'id'         => $sp->id,
                    'pms'        => $sp->pms_source,
                    'stripe_ref' => $stripeRef,
                    'error'      => $err,
                ]);
            }
        } catch (\Throwable $e) {
            $sp->update(['pms_notified' => false, 'pms_notify_error' => $e->getMessage()]);
            $this->warn("    ↳ PMS notification exception ({$sp->pms_source}): " . $e->getMessage());
            Log::channel('lodgify')->error('ChargeScheduledPayments: PMS notify exception', [
                'id'         => $sp->id,
                'pms'        => $sp->pms_source,
                'stripe_ref' => $stripeRef,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function handleFailure(ScheduledPayment $sp, string $error, array $context): void
    {
        $attempts = $sp->attempts + 1;
        $status   = $attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';

        $sp->update([
            'attempts'      => $attempts,
            'error_message' => $error,
            'status'        => $status,
        ]);

        $this->error("  ✗ Failed booking #{$sp->booking_request_id}: {$error}");

        Log::channel('lodgify')->error('ChargeScheduledPayments: charge failed', $context + [
            'attempts' => $attempts,
            'status'   => $status,
            'error'    => $error,
        ]);

        // After max attempts, alert the admin so they can follow up manually
        if ($status === 'failed') {
            $this->sendFailureAlert($sp, $error, $attempts);
        }
    }

    private function sendReminderEmail(ScheduledPayment $sp): void
    {
        try {
            $booking  = $sp->bookingRequest;
            if (!$booking || !$booking->email) return;

            $property = \App\Models\Pms\PmsRoom::find($booking->property_id);

            $html = view('mail.payment-reminder-user', [
                'guestName'    => $booking->name    ?? 'Guest',
                'propertyName' => $property->name   ?? 'your property',
                'amount'       => $sp->amount,
                'currency'     => ModelHelper::getDataFromSetting('payment_currency') ?: '$',
                'dueDate'      => $sp->due_date->toDateString(),
                'bookingId'    => $sp->booking_request_id,
                'checkin'      => $booking->checkin ?? '',
            ])->render();

            MailHelper::emailSenderByController(
                $html,
                $booking->email,
                "Upcoming Payment Reminder — Booking #{$sp->booking_request_id}"
            );

            $sp->update(['reminder_sent' => true]);

            Log::channel('lodgify')->info('ChargeScheduledPayments: reminder sent', [
                'id'    => $sp->id,
                'email' => $booking->email,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('ChargeScheduledPayments: reminder email failed', [
                'id'    => $sp->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendFailureAlert(ScheduledPayment $sp, string $error, int $attempts): void
    {
        try {
            $booking    = $sp->bookingRequest;
            $adminEmail = ModelHelper::getDataFromSetting('payment_receiving_mail');
            $subject    = "Action Required: Scheduled Payment Failed — Booking #{$sp->booking_request_id}";

            $html = view('mail.payment-failed-alert', [
                'bookingId'    => $sp->booking_request_id,
                'guestName'    => $booking->name  ?? 'Unknown',
                'guestEmail'   => $booking->email ?? 'Unknown',
                'amount'       => $sp->amount,
                'currency'     => $sp->currency,
                'dueDate'      => $sp->due_date->toDateString(),
                'pmsSource'    => $sp->pms_source,
                'pmsBookingId' => $sp->pms_booking_id,
                'errorMessage' => $error,
                'attempts'     => $attempts,
            ])->render();

            MailHelper::emailSenderByController($html, $adminEmail, $subject);

            Log::channel('lodgify')->info('ChargeScheduledPayments: failure alert sent', [
                'id'    => $sp->id,
                'admin' => $adminEmail,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('ChargeScheduledPayments: could not send failure alert', [
                'id'    => $sp->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
