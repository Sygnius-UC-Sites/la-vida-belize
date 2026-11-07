<?php

namespace App\Console\Commands;

use App\Models\BookingRequest;
use App\Models\Pms\PmsRoom;
use App\Services\PmsBookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use ModelHelper;
use MailHelper;

/**
 * RecoverPmsFailedBookings
 *
 * Finds bookings stuck in `payment-captured-pms-failed` and retries PMS
 * booking creation. These are bookings where:
 *   - Stripe payment was successfully charged
 *   - PmsBookingService::create() failed (PMS timeout, API error, etc.)
 *   - Guest is charged but has no confirmed reservation
 *
 * This is a safety net — it runs every 15 minutes via the scheduler.
 * It caps attempts at MAX_RECOVERY_ATTEMPTS (3) to avoid hammering the PMS.
 * After 3 failures, status becomes `payment-captured-pms-failed-permanent`
 * and an admin alert is sent.
 *
 * Usage:
 *   php projects/artisan bookings:recover-pms-failed
 *   php projects/artisan bookings:recover-pms-failed --dry-run
 *
 * Database columns needed (added by migration 2026_06_09_000001):
 *   booking_requests.pms_recovery_attempts  (tinyint, default 0)
 *   booking_requests.pms_recovery_last_at   (timestamp, nullable)
 */
class RecoverPmsFailedBookings extends Command
{
    protected $signature   = 'bookings:recover-pms-failed
                                {--dry-run : Preview bookings without attempting recovery}';
    protected $description = 'Retry PMS booking creation for bookings where payment was collected but PMS creation failed';

    private const MAX_RECOVERY_ATTEMPTS  = 3;
    // Don't retry a booking more than once every 10 minutes to avoid hammering PMS
    private const MIN_RETRY_INTERVAL_MIN = 10;

    public function __construct(private PmsBookingService $bookingService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $stuck = BookingRequest::where('booking_status', 'payment-captured-pms-failed')
            ->where('pms_recovery_attempts', '<', self::MAX_RECOVERY_ATTEMPTS)
            ->where(function ($q) {
                // Retry if never attempted, or if last attempt was > MIN_RETRY_INTERVAL_MIN ago
                $q->whereNull('pms_recovery_last_at')
                  ->orWhere('pms_recovery_last_at', '<', now()->subMinutes(self::MIN_RETRY_INTERVAL_MIN));
            })
            ->orderBy('created_at') // oldest first
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck bookings found.');
            return 0;
        }

        $this->info("Found {$stuck->count()} stuck booking(s)" . ($dryRun ? ' [DRY RUN]' : '') . '.');

        $recovered = $failed = 0;

        foreach ($stuck as $booking) {
            $context = [
                'booking_id'       => $booking->id,
                'email'            => $booking->email,
                'amount'           => $booking->total_amount,
                'attempts_so_far'  => $booking->pms_recovery_attempts,
                'created_at'       => $booking->created_at->toDateTimeString(),
            ];

            if ($dryRun) {
                $this->line("  [DRY RUN] Would retry booking #{$booking->id} (attempt {$booking->pms_recovery_attempts} of " . self::MAX_RECOVERY_ATTEMPTS . ")");
                continue;
            }

            // Mark attempt immediately — prevents a second worker from picking it up
            $booking->update([
                'pms_recovery_attempts' => $booking->pms_recovery_attempts + 1,
                'pms_recovery_last_at'  => now(),
            ]);

            $this->line("  Retrying booking #{$booking->id} (attempt {$booking->pms_recovery_attempts} of " . self::MAX_RECOVERY_ATTEMPTS . ")...");

            try {
                // If the PMS booking was somehow created on a prior attempt but the
                // status was never updated, alreadyCreated() will catch that
                if ($this->bookingService->alreadyCreated($booking->fresh())) {
                    $booking->update(['booking_status' => 'booking-confirmed']);
                    $this->info("    ✓ Already created in PMS — marking confirmed.");
                    $recovered++;
                    continue;
                }

                $result = $this->bookingService->create($booking->fresh());

                if ($result['ok']) {
                    $this->sendConfirmationEmails($booking->fresh());
                    $this->info("    ✓ Recovered — PMS booking ID: {$result['pms_booking_id']}");
                    Log::channel('lodgify')->info('RecoverPmsFailedBookings: recovered', $context + [
                        'pms_booking_id' => $result['pms_booking_id'],
                    ]);
                    $recovered++;
                } else {
                    $this->warn("    ✗ Still failing: " . ($result['error'] ?? 'unknown'));
                    Log::channel('lodgify')->error('RecoverPmsFailedBookings: still failing', $context + [
                        'error' => $result['error'] ?? 'unknown',
                    ]);

                    // If this was the final allowed attempt, escalate permanently
                    if ($booking->pms_recovery_attempts >= self::MAX_RECOVERY_ATTEMPTS) {
                        $this->escalateToAdmin($booking->fresh(), $result['error'] ?? 'PMS refused booking after all retries');
                    }

                    $failed++;
                }

            } catch (\Throwable $e) {
                $this->error("    ✗ Exception: " . $e->getMessage());
                Log::channel('lodgify')->error('RecoverPmsFailedBookings: exception', $context + [
                    'error' => $e->getMessage(),
                ]);

                if ($booking->pms_recovery_attempts >= self::MAX_RECOVERY_ATTEMPTS) {
                    $this->escalateToAdmin($booking->fresh(), $e->getMessage());
                }

                $failed++;
            }
        }

        if (!$dryRun) {
            $this->info("Done. Recovered: {$recovered}, Still failing: {$failed}.");
        }

        return 0;
    }

    /**
     * After MAX_RECOVERY_ATTEMPTS the booking is moved to a permanent-failed status
     * and the admin receives a detailed alert.
     */
    private function escalateToAdmin(BookingRequest $booking, string $lastError): void
    {
        $booking->update(['booking_status' => 'payment-captured-pms-failed-permanent']);

        try {
            $adminEmail = ModelHelper::getDataFromSetting('payment_receiving_mail');
            $room       = PmsRoom::find($booking->property_id);
            $subject    = "⚠️ Action Required: Booking #{$booking->id} — Payment Collected, PMS Booking Failed Permanently";

            $html = "<h2 style='color:red'>Manual intervention required</h2>"
                  . "<p>A guest was charged but their PMS reservation could not be created after "
                  . self::MAX_RECOVERY_ATTEMPTS . " automatic retry attempts.</p>"
                  . "<table cellpadding='6' border='1' style='border-collapse:collapse'>"
                  . "<tr><th>Booking ID</th><td>{$booking->id}</td></tr>"
                  . "<tr><th>Guest</th><td>{$booking->name} &lt;{$booking->email}&gt;</td></tr>"
                  . "<tr><th>Property</th><td>" . ($room->name ?? "ID {$booking->property_id}") . "</td></tr>"
                  . "<tr><th>Check-in</th><td>{$booking->checkin}</td></tr>"
                  . "<tr><th>Check-out</th><td>{$booking->checkout}</td></tr>"
                  . "<tr><th>Amount Charged</th><td>{$booking->total_amount}</td></tr>"
                  . "<tr><th>Last PMS Error</th><td>{$lastError}</td></tr>"
                  . "<tr><th>Recovery Attempts</th><td>" . self::MAX_RECOVERY_ATTEMPTS . "</td></tr>"
                  . "</table>"
                  . "<p><strong>Required actions:</strong></p>"
                  . "<ol>"
                  . "<li>Log in to the PMS and create the reservation manually.</li>"
                  . "<li>Update the booking record in the admin panel with the PMS booking ID.</li>"
                  . "<li>Send a confirmation email to the guest.</li>"
                  . "<li>If unable to create the booking, issue a full Stripe refund.</li>"
                  . "</ol>";

            MailHelper::emailSenderByController($html, $adminEmail, $subject);

            Log::channel('lodgify')->critical('RecoverPmsFailedBookings: escalated to admin', [
                'booking_id' => $booking->id,
                'admin'      => $adminEmail,
            ]);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('RecoverPmsFailedBookings: could not send escalation email', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send confirmation emails after a successful recovery.
     * Same templates as the normal booking confirmation flow.
     */
    private function sendConfirmationEmails(BookingRequest $booking): void
    {
        try {
            $property = PmsRoom::find($booking->property_id);
            if (!$property) return;

            $payment  = \App\Models\Payment::where('booking_id', $booking->id)->latest()->first();
            app(\App\Services\Email\BookingLifecycleMailer::class)
                ->sendConfirmationAndReceipt($booking, $property, $payment);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('RecoverPmsFailedBookings: confirmation email failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
