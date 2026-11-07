<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\BookingRequest;
use App\Models\Payment;
use App\Models\Pms\PmsRoom;
use App\Modules\VacationRental\Pms\PmsManager;
use App\Services\PmsBookingService;
use App\Services\PmsRefundSync;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ModelHelper;
use MailHelper;
use Stripe;

/**
 * StripeWebhookController — receives and processes Stripe webhook events.
 *
 * IMPORTANT: This endpoint MUST be excluded from CSRF verification (see VerifyCsrfToken).
 * It uses Stripe's own signature verification instead.
 *
 * Setup:
 *  1. Create a webhook in Stripe Dashboard → Developers → Webhooks
 *  2. Add the endpoint URL: https://your-site.com/stripe/webhook
 *  3. Subscribe to events:
 *       payment_intent.succeeded       — primary event for new PaymentIntents flow
 *       payment_intent.payment_failed  — card declined / 3DS failed
 *       charge.succeeded               — async confirmation for legacy charges
 *       charge.failed                  — notify admin of failed charge
 *       charge.dispute.created         — alert admin of chargeback (URGENT: respond within deadline)
 *       charge.refunded                — sync refund/deposit status to DB
 *  4. Copy the "Signing Secret" (starts with whsec_) into admin payment settings
 *     or .env as STRIPE_WEBHOOK_SECRET.
 *
 * Stripe retries delivery for up to 3 days on non-200 responses.
 * Every handler is idempotent — processing the same event twice has no side effects.
 */
class StripeWebhookController extends Controller
{
    public function handle(Request $request): \Illuminate\Http\JsonResponse
    {
        $webhookSecret = config('services.stripe.webhook_secret')
            ?: (string) ModelHelper::getDataFromSetting('stripe_webhook_secret');

        if (empty($webhookSecret)) {
            Log::channel('lodgify')->critical('StripeWebhook: STRIPE_WEBHOOK_SECRET not configured — event rejected.');
            // Return 200 so Stripe does not retry endlessly before it's configured
            return response()->json(['status' => 'not_configured'], 200);
        }

        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            Stripe\Stripe::setApiKey(ModelHelper::getDataFromSetting('stripe_secret_key'));
            $event = Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (Stripe\Exception\SignatureVerificationException $e) {
            Log::channel('lodgify')->warning('StripeWebhook: invalid signature', [
                'error' => $e->getMessage(),
                'ip'    => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('StripeWebhook: could not parse event', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Parse error'], 400);
        }

        Log::channel('lodgify')->info('StripeWebhook: received', [
            'type'       => $event->type,
            'event_id'   => $event->id,
        ]);

        // Route to the appropriate handler
        match ($event->type) {
            // PaymentIntents events (new flow)
            'payment_intent.succeeded'        => $this->handlePaymentIntentSucceeded($event),
            'payment_intent.payment_failed'   => $this->handlePaymentIntentFailed($event),
            // Charge events (legacy + deposit events)
            'charge.succeeded'                => $this->handleChargeSucceeded($event),
            'charge.failed'                   => $this->handleChargeFailed($event),
            'charge.dispute.created'          => $this->handleDisputeCreated($event),
            'charge.refunded'                 => $this->handleChargeRefunded($event),
            default                           => null,
        };

        return response()->json(['status' => 'ok'], 200);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * payment_intent.succeeded — the primary webhook for new PaymentIntents bookings.
     *
     * The synchronous path (GET /return) handles most bookings. This webhook handles:
     *   - Bookings where the browser closed before hitting the return URL
     *   - 3DS flows where the redirect somehow failed
     *   - Any booking stuck in payment-captured-pms-failed that just got confirmed by Stripe
     */
    private function handlePaymentIntentSucceeded(Stripe\Event $event): void
    {
        $pi       = $event->data->object;
        $piId     = $pi->id ?? null;
        $metadata = $pi->metadata ?? null;
        $bookingId = $metadata->booking_id ?? null;

        if (!$bookingId) {
            Log::channel('lodgify')->info('StripeWebhook: payment_intent.succeeded — no booking_id in metadata', ['pi_id' => $piId]);
            return;
        }

        $booking = BookingRequest::find($bookingId);
        if (!$booking) return;

        Log::channel('lodgify')->info('StripeWebhook: payment_intent.succeeded', [
            'booking_id' => $bookingId,
            'pi_id'      => $piId,
        ]);

        // If already confirmed (return URL handled it) — nothing to do
        if ($booking->booking_status === 'booking-confirmed') return;

        // If in pms-failed state, this confirms the money is real — trigger recovery
        if ($booking->booking_status === 'payment-captured-pms-failed') {
            $this->attemptPmsRecovery($booking);
            return;
        }

        // If payment not yet recorded (browser closed before return URL) — record and create booking
        \App\Models\Payment::firstOrCreate(
            ['tran_id' => $piId],
            [
                'booking_id'  => $booking->id,
                'customer_id' => is_string($pi->customer) ? $pi->customer : ($pi->customer->id ?? null),
                'description' => json_encode(['pi_id' => $piId]),
                'status'      => 'complete',
                'type'        => 'stripe',
                'amount'      => \App\Helper\StripeHelper::toMajorUnits((int)($pi->amount ?? 0), (string)($pi->currency ?? 'usd')),
            ]
        );

        $this->attemptPmsRecovery($booking);
    }

    /**
     * payment_intent.payment_failed — card declined or authentication failed.
     */
    private function handlePaymentIntentFailed(Stripe\Event $event): void
    {
        $pi        = $event->data->object;
        $piId      = $pi->id ?? null;
        $metadata  = $pi->metadata ?? null;
        $bookingId = $metadata->booking_id ?? null;
        $errMsg    = $pi->last_payment_error->message ?? 'Payment failed';

        Log::channel('lodgify')->warning('StripeWebhook: payment_intent.payment_failed', [
            'booking_id' => $bookingId,
            'pi_id'      => $piId,
            'error'      => $errMsg,
        ]);

        // Update booking status so admin can see it
        if ($bookingId) {
            $booking = BookingRequest::find($bookingId);
            if ($booking && $booking->booking_status === 'pending-payment') {
                $booking->update(['booking_status' => 'payment-failed']);
            }
        }
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * charge.succeeded — Stripe confirmed the charge asynchronously.
     *
     * Primary use: if a booking is stuck in `payment-captured-pms-failed`
     * and we receive this event, it confirms the charge is real and we can
     * attempt PMS booking creation again.
     */
    private function handleChargeSucceeded(Stripe\Event $event): void
    {
        $charge = $event->data->object;
        $chargeId = $charge->id ?? null;

        // Find the matching Payment record by Stripe tran_id
        $payment = \App\Models\Payment::where('tran_id', $chargeId)->first();
        if (!$payment) {
            // Not a booking charge we know about (e.g. deposit pre-auth)
            return;
        }

        $booking = BookingRequest::find($payment->booking_id);
        if (!$booking) return;

        // If the PMS booking creation had failed, this webhook confirms the payment
        // is real — trigger the recovery flow
        if ($booking->booking_status === 'payment-captured-pms-failed') {
            Log::channel('lodgify')->info('StripeWebhook: charge.succeeded for pms-failed booking — triggering recovery', [
                'booking_id' => $booking->id,
                'charge_id'  => $chargeId,
            ]);
            $this->attemptPmsRecovery($booking);
        }
    }

    /**
     * charge.failed — a charge attempt failed (e.g. async decline after initial success).
     * Alert admin so they can follow up with the guest.
     */
    private function handleChargeFailed(Stripe\Event $event): void
    {
        $charge     = $event->data->object;
        $chargeId   = $charge->id ?? null;
        $failureMsg = $charge->failure_message ?? 'Unknown reason';

        $payment = \App\Models\Payment::where('tran_id', $chargeId)->first();
        $booking = $payment ? BookingRequest::find($payment->booking_id) : null;

        Log::channel('lodgify')->error('StripeWebhook: charge.failed', [
            'charge_id'   => $chargeId,
            'booking_id'  => $booking?->id,
            'failure_msg' => $failureMsg,
        ]);

        try {
            $adminEmail = ModelHelper::getDataFromSetting('payment_receiving_mail');
            $subject    = "Stripe Charge Failed — Booking #{$booking?->id}";
            $html = "<p><strong>A Stripe charge failed.</strong></p>"
                  . "<p>Charge ID: <code>{$chargeId}</code></p>"
                  . "<p>Booking ID: " . ($booking?->id ?? 'not found') . "</p>"
                  . "<p>Guest: " . ($booking?->email ?? 'unknown') . "</p>"
                  . "<p>Failure reason: {$failureMsg}</p>"
                  . "<p>Please check the Stripe dashboard and follow up with the guest.</p>";
            MailHelper::emailSenderByController($html, $adminEmail, $subject);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('StripeWebhook: could not send charge.failed alert', ['error' => $e->getMessage()]);
        }
    }

    /**
     * charge.dispute.created — a guest has opened a chargeback/dispute.
     * Alert admin immediately — disputes require a response within 7–21 days.
     */
    private function handleDisputeCreated(Stripe\Event $event): void
    {
        $dispute  = $event->data->object;
        $chargeId = $dispute->charge ?? null;
        $currency = strtoupper($dispute->currency ?? 'USD');
        $amount   = number_format(\App\Helper\StripeHelper::toMajorUnits((int)($dispute->amount ?? 0), $currency), 2);
        $reason   = $dispute->reason ?? 'unknown';

        $payment = \App\Models\Payment::where('tran_id', $chargeId)->first();
        $booking = $payment ? BookingRequest::find($payment->booking_id) : null;

        Log::channel('lodgify')->error('StripeWebhook: DISPUTE CREATED', [
            'charge_id'  => $chargeId,
            'booking_id' => $booking?->id,
            'amount'     => "{$amount} {$currency}",
            'reason'     => $reason,
        ]);

        try {
            $adminEmail = ModelHelper::getDataFromSetting('payment_receiving_mail');
            $subject    = "⚠️ CHARGEBACK OPENED — Booking #{$booking?->id} — {$amount} {$currency}";
            $html = "<p><strong style='color:red'>A guest has opened a chargeback dispute.</strong></p>"
                  . "<p><strong>You must respond in Stripe within the dispute deadline.</strong></p>"
                  . "<table>"
                  . "<tr><td>Charge ID</td><td><code>{$chargeId}</code></td></tr>"
                  . "<tr><td>Booking ID</td><td>" . ($booking?->id ?? 'not found') . "</td></tr>"
                  . "<tr><td>Guest</td><td>" . ($booking?->name ?? 'unknown') . " — " . ($booking?->email ?? '') . "</td></tr>"
                  . "<tr><td>Amount</td><td>{$amount} {$currency}</td></tr>"
                  . "<tr><td>Reason</td><td>{$reason}</td></tr>"
                  . "</table>"
                  . "<p><a href='https://dashboard.stripe.com/disputes'>Open Stripe Disputes</a></p>";
            MailHelper::emailSenderByController($html, $adminEmail, $subject);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('StripeWebhook: could not send dispute alert', ['error' => $e->getMessage()]);
        }
    }

    /**
     * charge.refunded — fired on every partial or full refund of a Stripe charge.
     *
     * Case 1: Security deposit charge that was CAPTURED then refunded → update deposit status.
     *         If the deposit was only authorized (never captured), charge.refunded does NOT
     *         fire — a void fires payment_intent.canceled. This handler will therefore never
     *         see a release-only (no-money-movement) deposit event.
     *         Guard: if security_deposit_status = 'authorized' but we somehow receive
     *         charge.refunded, skip the PMS refund — no money moved.
     *
     * Case 2: Regular payment charge → record each Stripe refund (re_xxx) individually
     *         in payment_refunds via PmsRefundSync, which handles:
     *           - Per-refund row (one row per re_xxx, UNIQUE constraint prevents doubles)
     *           - Optimistic CAS lock (pending→syncing) blocks concurrent processing
     *           - Adapter-level idempotency as third defence
     *           - Retry tracking + admin alert on exhaustion
     */
    private function handleChargeRefunded(Stripe\Event $event): void
    {
        $charge   = $event->data->object;
        $chargeId = $charge->id ?? null;
        if (!$chargeId) return;

        // ── Case 1: security deposit ──────────────────────────────────────────
        $depositBooking = BookingRequest::where('stripe_deposit_charge_id', $chargeId)->first();
        if ($depositBooking) {
            // Guard: 'authorized' means the hold was never captured — charge.refunded here
            // would mean a void, not a real refund. Don't record a PMS refund for it.
            if ($depositBooking->security_deposit_status === 'authorized') {
                Log::channel('lodgify')->warning('StripeWebhook: charge.refunded on deposit that was never captured — treating as void, no PMS refund', [
                    'booking_id' => $depositBooking->id,
                    'charge_id'  => $chargeId,
                ]);
                $depositBooking->update(['security_deposit_status' => 'released']);
                return; // Not a payment-level refund; stop here
            }

            if ($depositBooking->security_deposit_status !== 'released') {
                $depositBooking->update(['security_deposit_status' => 'released']);
                Log::channel('lodgify')->info('StripeWebhook: captured deposit marked released via charge.refunded', [
                    'booking_id' => $depositBooking->id,
                    'charge_id'  => $chargeId,
                ]);
            }
            // Deposit charges are tracked separately; do not also notify as a payment refund.
            return;
        }

        // ── Case 2: regular payment charge ───────────────────────────────────
        $paymentRecord = Payment::where('tran_id', $chargeId)->first();
        if (!$paymentRecord) return;

        $booking = BookingRequest::find($paymentRecord->booking_id);
        if (!$booking) return;

        // Process EACH refund individually. Stripe lists refunds newest-first on the charge.
        // For partial refunds this means we may receive multiple charge.refunded events,
        // each carrying the full refunds list. We process only the newest refund (data[0])
        // on each event; prior ones are already in payment_refunds and will be skipped by
        // PmsRefundSync's UNIQUE constraint + status check.
        $latestRefund   = $charge->refunds->data[0] ?? null;
        $stripeRefundId = $latestRefund?->id ?? null;
        $refundAmount   = $latestRefund ? ($latestRefund->amount / 100) : (($charge->amount_refunded ?? 0) / 100);

        if (!$stripeRefundId || $refundAmount <= 0) {
            Log::channel('lodgify')->warning('StripeWebhook: charge.refunded — no refund ID/amount', ['charge_id' => $chargeId]);
            return;
        }

        Log::channel('lodgify')->info('StripeWebhook: charge.refunded — syncing via PmsRefundSync', [
            'booking_id'    => $booking->id,
            'stripe_refund' => $stripeRefundId,
            'amount'        => $refundAmount,
        ]);

        app(PmsRefundSync::class)->syncRefund(
            $paymentRecord,
            $booking,
            $stripeRefundId,
            $refundAmount,
            $chargeId
        );
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Attempt to create the PMS booking for a booking stuck in payment-captured-pms-failed.
     * Called both from the Stripe webhook and from the RecoverPmsFailedBookings command.
     */
    private function attemptPmsRecovery(BookingRequest $booking): void
    {
        try {
            $service = app(PmsBookingService::class);

            if ($service->alreadyCreated($booking)) {
                // Already recovered by a previous attempt
                $booking->update(['booking_status' => 'booking-confirmed']);
                return;
            }

            $result = $service->create($booking->fresh());

            if ($result['ok']) {
                // Save any installments from the recovered booking
                $this->sendRecoveryConfirmationEmail($booking->fresh());
                Log::channel('lodgify')->info('StripeWebhook: PMS recovery succeeded', [
                    'booking_id'     => $booking->id,
                    'pms_booking_id' => $result['pms_booking_id'],
                ]);
            } else {
                Log::channel('lodgify')->error('StripeWebhook: PMS recovery failed', [
                    'booking_id' => $booking->id,
                    'error'      => $result['error'],
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('StripeWebhook: PMS recovery exception', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function sendRecoveryConfirmationEmail(BookingRequest $booking): void
    {
        try {
            $property = PmsRoom::find($booking->property_id);
            if (!$property) return;

            $payment  = \App\Models\Payment::where('booking_id', $booking->id)->latest()->first();
            app(\App\Services\Email\BookingLifecycleMailer::class)
                ->sendConfirmationAndReceipt($booking, $property, $payment);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('StripeWebhook: recovery confirmation email failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
