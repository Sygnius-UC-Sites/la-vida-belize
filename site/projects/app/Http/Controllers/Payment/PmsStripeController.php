<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BookingRequest;
use App\Models\Pms\PmsRoom;
use App\Services\PmsBookingService;
use App\Models\Payment;
use App\Models\ScheduledPayment;
use App\Helper\StripeHelper;
use App\Helper\QuoteNormalizer;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe;
use ModelHelper;

/**
 * StripeController — PaymentIntents-based payment flow.
 *
 * FLOW:
 *   1. GET  /booking/payment/{id}?token={payment_token}
 *        → validate token (IDOR guard)
 *        → preflight check (availability, min stay)
 *        → refresh quote if expired
 *        → create/retrieve PaymentIntent
 *        → render payment form with clientSecret
 *
 *   2. Client calls stripe.confirmCardPayment(clientSecret)
 *        → Stripe handles 3DS automatically
 *        → on success → JS redirects to /booking/payment/{id}/return?token=...
 *        → on 3DS redirect → Stripe bounces back to return_url
 *
 *   3. GET  /booking/payment/{id}/return?token={token}&payment_intent={pi_xxx}
 *        → validate token
 *        → retrieve PI from Stripe — verify status=succeeded
 *        → record Payment
 *        → pre-authorize security deposit (PI with capture_method=manual)
 *        → call PmsBookingService
 *        → save installments
 *        → record payment in Guesty
 *        → send confirmation emails
 *
 * Security properties:
 *   - One PaymentIntent per booking (idempotent — never charges twice)
 *   - payment_token in URL prevents IDOR (guessing /booking/payment/123)
 *   - Availability re-checked before the payment form is shown (not after charging)
 *   - Quote re-fetched after 1 hour; guest sees updated price before paying
 *   - 3DS/SCA handled automatically by Stripe.js
 */
class PmsStripeController extends Controller
{
    // ── GET /booking/payment/{id} ─────────────────────────────────────────────

    public function index(Request $request, $id)
    {
        $booking = BookingRequest::find($id);
        if (!$booking) abort(404);

        $property = PmsRoom::find($booking->property_id);
        if (!$property) abort(404);

        // Already confirmed — skip straight to success
        if (in_array($booking->booking_status, ['booking-confirmed', 'payment-captured-pms-failed'])) {
            return redirect('pms/payment/success/' . $booking->id)
                ->with('success', 'Your booking is already confirmed.');
        }

        // ── IDOR protection: generate token on first visit, then enforce it ───
        if (empty($booking->payment_token)) {
            $booking->update(['payment_token' => Str::random(40)]);
            $booking->refresh();
        }

        $token = $request->query('token');
        if ($token !== $booking->payment_token) {
            // Old-format URL (no token) or wrong token — redirect with correct token.
            // This handles old email links gracefully.
            return redirect()->to(
                route('pms.stripe_payment', $booking->id) . '?token=' . $booking->payment_token
            );
        }

        // ── Preflight: availability check BEFORE showing payment form ─────────
        $preflight = $this->preflight($booking, $property);
        if (!$preflight['ok']) {
            return redirect()->back()->with('danger', $preflight['error']);
        }

        // ── Quote expiry: re-fetch from PMS if quote is older than 1 hour ─────
        // Fail-closed: if the PMS is unreachable, block the payment form entirely.
        // Never allow checkout to proceed on a price we cannot confirm is still valid.
        try {
            $booking = $this->refreshQuoteIfExpired($booking, $property);
        } catch (\Throwable $e) {
            return redirect()->back()->with('danger',
                'We could not confirm the current price with the property management system. '
                . 'Please go back and try again. If the problem persists, contact support.'
            );
        }

        $stripe_publishable_key = ModelHelper::getDataFromSetting('stripe_publish_key');
        $data          = new \stdClass();
        $data->name    = $data->meta_title = $data->meta_keywords = $data->meta_description = 'Stripe Payment Booking';

        // ── Price-change gate: require guest acceptance — NO PaymentIntent yet ──
        // If the PMS returned a different total during refresh, we stored it in
        // price_changed_to. Block payment AND skip PaymentIntent creation until the
        // guest explicitly accepts. Creating (or updating) a PI here would silently
        // charge the new amount without consent (fix #5).
        if (!empty($booking->price_changed_to)) {
            $priceChanged = [
                'old' => (float)$booking->total_amount,
                'new' => (float)$booking->price_changed_to,
            ];
            // clientSecret and returnUrl are intentionally null — JS is guarded below.
            $clientSecret = null;
            $returnUrl    = null;
            if (request()->query('pms_json')) {
            return response()->json([
                'ok'              => true,
                'client_secret'   => $clientSecret ?? null,
                'publishable_key' => $stripe_publishable_key ?? \ModelHelper::getDataFromSetting('stripe_publish_key'),
                'return_url'      => $returnUrl ?? url("pms/booking/payment/{$booking->id}/return?token={$booking->payment_token}"),
                'amount_due'      => $amountDue ?? ($booking->deposit_due_amount ?: $booking->total_amount),
                'currency'        => $booking->currency ?: 'USD',
            ]);
        }
        return view('front.booking.payment.pms-stripe', compact(
                'booking', 'data', 'property', 'priceChanged',
                'stripe_publishable_key', 'clientSecret', 'returnUrl'
            ));
        }

        // ── Create or retrieve Stripe PaymentIntent ───────────────────────────
        Stripe\Stripe::setApiKey(ModelHelper::getDataFromSetting('stripe_secret_key'));

        try {
            $paymentIntent = $this->getOrCreatePaymentIntent($booking, $property);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('StripeController: could not create PaymentIntent', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
            return redirect()->back()->with('danger', 'Unable to initialize payment. Please try again.');
        }

        $clientSecret  = $paymentIntent->client_secret;
        $returnUrl     = url("pms/booking/payment/{$booking->id}/return?token={$booking->payment_token}");
        $priceChanged  = null;

        if (request()->query('pms_json')) {
            return response()->json([
                'ok'              => true,
                'client_secret'   => $clientSecret ?? null,
                'publishable_key' => $stripe_publishable_key ?? \ModelHelper::getDataFromSetting('stripe_publish_key'),
                'return_url'      => $returnUrl ?? url("pms/booking/payment/{$booking->id}/return?token={$booking->payment_token}"),
                'amount_due'      => $amountDue ?? ($booking->deposit_due_amount ?: $booking->total_amount),
                'currency'        => $booking->currency ?: 'USD',
            ]);
        }
        return view('front.booking.payment.pms-stripe', compact(
            'booking', 'data', 'property', 'priceChanged',
            'stripe_publishable_key', 'clientSecret', 'returnUrl'
        ));
    }

    // ── POST /booking/payment/{id}/accept-price ──────────────────────────────
    // Guest has seen the price-change banner and accepted the new total.
    // This handler re-fetches the PMS quote, updates the FULL booking snapshot
    // (total_amount, line items, taxes, payment schedule, deposit_due_amount),
    // clears price_changed_to, then redirects back to the payment form.
    //
    // Uses POST + CSRF (not GET ?accept_price=1) to prevent link pre-fetching,
    // bookmark replays, and search-engine crawls from silently accepting prices.

    public function acceptPrice(Request $request, $id)
    {
        $booking  = BookingRequest::find($id);
        if (!$booking) abort(404);

        $property = PmsRoom::find($booking->property_id);
        if (!$property) abort(404);

        // IDOR guard
        if ($request->input('token') !== $booking->payment_token) {
            abort(403, 'Invalid payment token.');
        }

        // $displayedPrice is the total the guest was shown on the banner.
        // We must compare the fresh quote against THIS value, not the old booking total.
        $displayedPrice = (float)($booking->price_changed_to ?? 0);
        if ($displayedPrice <= 0) {
            // No pending price change — redirect normally
            return redirect()->to(route('pms.stripe_payment', $id) . '?token=' . $booking->payment_token);
        }

        try {
            $pmsManager = app(\App\Modules\VacationRental\Pms\PmsManager::class);
            $adapter    = $pmsManager->driver($property->pms_source);

            $input = [
                'property_id'       => $property->pms_property_id,
                'room_id'           => $property->pms_room_id,
                'checkin'           => $booking->checkin,
                'checkout'          => $booking->checkout,
                'adults'            => (int)($booking->adults ?? $booking->total_guests ?? 1),
                'children'          => (int)($booking->child ?? 0),
                'infants'           => (int)($booking->infants ?? 0),
                'pets'              => (int)($booking->total_pets ?? 0),
                'promo_code'        => $booking->discount_coupon ?? '',
                'optional_services' => \App\Helper\FeeValidator::confirmed(
                    json_decode($booking->optional_services ?? '{}', true) ?: [],
                    $property
                ),
            ];

            $raw   = $adapter->getRawQuote($input);
            $quote = \App\Helper\QuoteNormalizer::normalize($raw, $property->pms_source, $input);

            if (!($quote['ok'] ?? false)) {
                return redirect()->to(route('pms.stripe_payment', $id) . '?token=' . $booking->payment_token)
                    ->with('danger', 'Could not confirm the new price. Please try again.');
            }

            $lineItems = $quote['line_items'] ?? [];
            $nights    = $quote['nights'] ?? (int)$booking->total_night;
            $currency  = $quote['currency'] ?? $booking->currency ?? 'USD';
            $freshTotal = (float)$quote['total'];

            // ── Fix #6 + #4: compare in integer minor units — zero tolerance ──
            // The guest accepted $displayedPrice. Any difference at all (even 1 cent)
            // means consent was for a different amount. Integer comparison prevents
            // floating-point rounding from masking a real price change.
            if (\App\Helper\StripeHelper::toStripeAmount($freshTotal, $currency)
                !== \App\Helper\StripeHelper::toStripeAmount($displayedPrice, $currency)) {
                Log::channel('lodgify')->warning('StripeController::acceptPrice: price changed again — requiring re-acceptance', [
                    'booking_id'      => $booking->id,
                    'displayed_price' => $displayedPrice,
                    'fresh_total'     => $freshTotal,
                    'diff'            => round($freshTotal - $displayedPrice, 4),
                    'currency'        => $currency,
                ]);
                $booking->update([
                    'price_changed_to' => $freshTotal,
                    'quote_data_all'   => json_encode($raw),
                    'quote_expires_at' => now()->addHour(),
                ]);
                return redirect()->to(route('pms.stripe_payment', $id) . '?token=' . $booking->payment_token)
                    ->with('warning', 'The price has changed again. Please review and accept the updated total.');
            }

            // ── Full snapshot update ─────────────────────────────────────────
            // Update every quote-derived field so the stored booking is consistent
            // with what the guest accepted. Updating only total_amount would leave
            // line items, taxes, payment schedule, and deposit_due_amount stale.
            $grossAmt = 0.0;
            foreach ($lineItems as $li) {
                if (($li['type'] ?? '') === 'accommodation') $grossAmt += (float)$li['amount'];
            }
            if ($grossAmt == 0) $grossAmt = $freshTotal;

            $taxTotal = 0.0;
            foreach ($lineItems as $li) {
                if (($li['type'] ?? '') === 'tax') $taxTotal += (float)$li['amount'];
            }

            // Excludes 'tax' and 'deposit' — tax is shown once via the dedicated
            // tax row (and tax column); deposit is shown separately. Keeping tax
            // here made it render 2-3x in emails/admin (see saveBookingData note).
            $beforeFees = [];
            foreach ($lineItems as $li) {
                $liType = $li['type'] ?? '';
                if ($liType === 'deposit' || $liType === 'tax') continue;
                $beforeFees[] = ['name' => $li['label'], 'value' => (float)$li['amount'], 'amount' => (float)$li['amount'], 'type' => $liType];
            }

            // ── Fix #8 + #3: rebuild schedule; sum is_current rows; deposit_percent fallback ──
            $scheduledPayments = $quote['scheduled_payments'] ?? [];
            $amountData        = [];
            foreach ($scheduledPayments as $sp) {
                if (in_array(strtolower($sp['type'] ?? ''), ['payment', 'deposit', 'balance'])) {
                    $amountData[] = [
                        'amount'     => (float)$sp['amount'],
                        'date_due'   => $sp['date_due'] ?? $sp['due_date'] ?? $sp['date'] ?? '',
                        'label'      => $sp['label']    ?? '',
                        'is_current' => $sp['is_current'] ?? false,
                    ];
                }
            }

            // Fix #3: deposit_percent fallback when PMS returns no schedule
            if (empty($amountData) && !empty($property->deposit_percent)) {
                $pct        = min(100, max(1, (int)$property->deposit_percent));
                $depositAmt = round($freshTotal * $pct / 100, 2);
                $balanceAmt = round($freshTotal - $depositAmt, 2);
                $checkinStr = $booking->checkin ?? '';
                $bDueDays   = $property->balance_due_days ?? null;
                $balanceDue = ($checkinStr && $bDueDays)
                    ? date('Y-m-d', strtotime("{$checkinStr} -{$bDueDays} days"))
                    : $checkinStr;
                $amountData = [
                    ['amount' => $depositAmt, 'date_due' => date('Y-m-d'), 'label' => "Deposit ({$pct}%)", 'is_current' => true],
                    ['amount' => $balanceAmt, 'date_due' => $balanceDue,   'label' => 'Balance',            'is_current' => false],
                ];
            }

            // deposit_due_amount: prefer is_current rows, then date_due <= today, then first row.
            $today    = now()->toDateString();
            $dueNow   = array_filter($amountData, fn($r) => ($r['is_current'] ?? false) || (empty($r['date_due']) || $r['date_due'] <= $today));
            $depositDue = !empty($dueNow)
                ? (float)array_sum(array_column($dueNow, 'amount'))
                : (!empty($amountData) ? (float)$amountData[0]['amount'] : $freshTotal);

            $booking->update([
                'total_amount'      => $freshTotal,
                'gross_amount'      => $grossAmt,
                'tax'               => $taxTotal,
                'sub_amount'        => $freshTotal - $taxTotal,
                'total_night'       => $nights,
                'currency'          => $currency,
                'before_total_fees' => json_encode($beforeFees),
                'amount_data'       => json_encode($amountData),
                'deposit_due_amount'=> $depositDue,
                'quote_data_all'    => json_encode($raw),
                'quote_expires_at'  => now()->addHour(),
                'price_changed_to'  => null, // clear the gate
            ]);

            Log::channel('lodgify')->info('StripeController::acceptPrice: guest accepted price change', [
                'booking_id'      => $booking->id,
                'displayed_price' => $displayedPrice,
                'accepted_total'  => $freshTotal,
                'deposit_due'     => $depositDue,
                'currency'        => $currency,
            ]);

        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('StripeController::acceptPrice: re-quote failed', [
                'booking_id' => $id,
                'error'      => $e->getMessage(),
            ]);
            return redirect()->to(route('pms.stripe_payment', $id) . '?token=' . $booking->payment_token)
                ->with('danger', 'Could not confirm the updated price. Please try again.');
        }

        return redirect()->to(route('pms.stripe_payment', $id) . '?token=' . $booking->payment_token)
            ->with('success', 'Price accepted. Please complete your payment.');
    }

    // ── POST /booking/payment/{id} — legacy redirect ─────────────────────────
    // Old Charges-API flow posted to this URL. Redirect to the new GET flow.

    public function indexPostRedirect(Request $request, $id)
    {
        $booking = BookingRequest::find($id);
        if (!$booking) abort(404);
        // Ensure payment_token exists, then redirect to the correct GET URL
        if (empty($booking->payment_token)) {
            $booking->update(['payment_token' => Str::random(40)]);
        }
        return redirect()->to(route('pms.stripe_payment', $id) . '?token=' . $booking->payment_token);
    }

    // ── GET /booking/payment/{id}/return ─────────────────────────────────────

    public function confirm(Request $request, $id)
    {
        $booking = BookingRequest::find($id);
        if (!$booking) abort(404);

        $property = PmsRoom::find($booking->property_id);
        if (!$property) abort(404);

        // ── IDOR guard ────────────────────────────────────────────────────────
        if ($request->query('token') !== $booking->payment_token) {
            abort(403, 'Invalid payment token.');
        }

        // ── Retrieve PaymentIntent and verify it succeeded ────────────────────
        Stripe\Stripe::setApiKey(ModelHelper::getDataFromSetting('stripe_secret_key'));

        $piId = $request->query('payment_intent') ?? $booking->stripe_payment_intent_id;
        if (!$piId) {
            return redirect()->to(route('pms.stripe_payment', $booking->id) . '?token=' . $booking->payment_token)
                ->with('danger', 'Payment reference missing. Please try again.');
        }

        try {
            $paymentIntent = Stripe\PaymentIntent::retrieve([
                'id'     => $piId,
                'expand' => ['payment_method', 'customer'],
            ]);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('StripeController::confirm: could not retrieve PI', [
                'booking_id' => $id, 'pi_id' => $piId, 'error' => $e->getMessage(),
            ]);
            return redirect()->to(route('pms.stripe_payment', $booking->id) . '?token=' . $booking->payment_token)
                ->with('danger', 'Could not verify payment status. Please contact support.');
        }

        if ($paymentIntent->status !== 'succeeded') {
            $errMsg = $paymentIntent->last_payment_error->message ?? 'Payment was not completed.';
            Log::channel('lodgify')->warning('StripeController::confirm: PI not succeeded', [
                'booking_id' => $id, 'pi_status' => $paymentIntent->status,
            ]);
            return redirect()->to(route('pms.stripe_payment', $booking->id) . '?token=' . $booking->payment_token)
                ->with('danger', $errMsg . ' Please try again.');
        }

        // ── Idempotency: already created in PMS → just go to success ─────────
        $bookingService = app(PmsBookingService::class);
        if ($bookingService->alreadyCreated($booking)) {
            return redirect('pms/payment/success/' . $booking->id)
                ->with('success', 'Your reservation is already confirmed.');
        }

        // ── Final availability + quote recheck BEFORE recording the payment ───
        // The Stripe charge already succeeded on the client side. If availability
        // has changed since the payment form was shown, refund the PaymentIntent
        // immediately and return an error — do NOT record a payment for blocked dates.
        $finalPreflight = $this->preflight($booking->fresh(), $property);
        if (!$finalPreflight['ok']) {
            // Refund the captured PaymentIntent automatically
            try {
                Stripe\Refund::create(['payment_intent' => $piId]);
                Log::channel('lodgify')->warning('StripeController::confirm: availability changed after charge — refund issued', [
                    'booking_id' => $id,
                    'pi_id'      => $piId,
                    'reason'     => $finalPreflight['error'] ?? 'unavailable',
                ]);
            } catch (\Throwable $refundEx) {
                Log::channel('lodgify')->error('StripeController::confirm: availability check failed AND refund failed', [
                    'booking_id'   => $id,
                    'pi_id'        => $piId,
                    'refund_error' => $refundEx->getMessage(),
                ]);
            }
            $booking->update(['booking_status' => 'booking-cancel']);
            return redirect()->to(route('pms.stripe_payment', $booking->id) . '?token=' . $booking->payment_token)
                ->with('danger', ($finalPreflight['error'] ?? 'Selected dates are no longer available.') . ' Your payment has been refunded.');
        }

        // ── Record payment ────────────────────────────────────────────────────
        $customerId = is_string($paymentIntent->customer)
            ? $paymentIntent->customer
            : ($paymentIntent->customer->id ?? null);

        $pmId = is_string($paymentIntent->payment_method)
            ? $paymentIntent->payment_method
            : ($paymentIntent->payment_method->id ?? null);

        $payment = Payment::firstOrCreate(
            ['tran_id' => $paymentIntent->id],
            [
                'booking_id'          => $booking->id,
                'receipt_url'         => null,  // PI charges have receipt on charge object; fetch if needed
                'customer_id'         => $customerId,
                'balance_transaction' => null,
                'description'         => json_encode(['pi_id' => $paymentIntent->id, 'amount' => $paymentIntent->amount, 'currency' => $paymentIntent->currency]),
                'status'              => 'complete',
                'type'                => 'stripe',
                'amount'              => \App\Helper\StripeHelper::toMajorUnits($paymentIntent->amount, $paymentIntent->currency),
                'pms_sync_status'     => 'pending',
            ]
        );

        // ── Pre-authorize security deposit (PaymentIntent, manual capture) ────
        $this->preAuthorizeDeposit($booking->fresh(), $customerId, $pmId);

        // ── Create PMS reservation ────────────────────────────────────────────
        $pmsResult = $bookingService->create($booking->fresh());

        if ($pmsResult['ok']) {
            // ── Guard: PMS returned ok but no reservation ID ──────────────────
            // A success response without a booking ID means the PMS accepted the
            // request but never assigned an identifier — we cannot confirm the
            // guest's reservation.  Flag for manual review.
            $pmsBookingIdCheck = $pmsResult['pms_booking_id'] ?? null;
            if (empty($pmsBookingIdCheck)) {
                $booking->update([
                    'booking_status' => 'reconciliation-needed',
                    'pms_sync_error' => json_encode([
                        'reason'     => 'PMS returned success (ok=true) but no reservation ID was assigned',
                        'flagged_at' => now()->toIso8601String(),
                    ]),
                ]);
                Log::channel('lodgify')->critical(
                    'StripeController: PMS ok=true but pms_booking_id empty — reconciliation-needed',
                    ['booking_id' => $id, 'pms' => $property->pms_source]
                );
                return redirect('pms/payment/success/' . $booking->id)
                    ->with('success', 'Payment received. Your reservation requires manual review — our team will contact you within 24 hours. Reference: ' . ($booking->request_id ?? (string)$id));
            }

            // Save installments from PMS payment schedule
            $this->saveInstallments($booking, $pmId, $customerId, $pmsResult);

            // ── Record initial Stripe charge in PMS ledger ────────────────────
            // Runs AFTER successful PMS reservation creation. Best-effort: failure here
            // NEVER reverses or repeats the Stripe charge. The $payment row (payments table)
            // carries pms_sync_status so each charge is tracked individually; a retry job
            // can reprocess failed rows without risk of double-recording (adapters use
            // idempotency keys scoped to the Stripe PI/charge ID).
            $pms           = $property->pms_source ?? '';
            $pmsBookingId  = $pmsResult['pms_booking_id'] ?? null;
            $chargedAmount = \App\Helper\StripeHelper::toMajorUnits($paymentIntent->amount, $paymentIntent->currency);
            $currency      = $booking->currency ?: 'USD';
            $piId          = $paymentIntent->id;

            // Idempotency + concurrency guard: atomic CAS pending → syncing.
            // If the webhook handler or a retry job already set the status to 'syncing'
            // or 'synced', this update returns 0 rows and we skip entirely.
            // This prevents the webhook and the return-URL path from both recording the
            // same payment in the PMS simultaneously.
            $locked = DB::table('payments')
                ->where('id', $payment->id)
                ->where(function ($q) {
                    $q->where('pms_sync_status', 'pending')->orWhereNull('pms_sync_status');
                })
                ->update(['pms_sync_status' => 'syncing']);

            if (!$locked) {
                $payment->refresh();
                Log::channel('lodgify')->info('StripeController: payment sync lock not acquired — skipping', [
                    'payment_id' => $payment->id,
                    'current'    => $payment->pms_sync_status,
                ]);
            } elseif ($pmsBookingId) {
                $adapter = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver($pms);
                $result  = $adapter->recordPayment($pmsBookingId, $chargedAmount, $currency, $piId);

                if ($result['ok'] ?? false) {
                    // ── Store PMS payment ID on this specific payment record ────
                    $pmsPaymentId = $result['payment_id']       // Guesty
                                  ?? $result['charge_id']        // Hostaway
                                  ?? $result['transaction_uid']  // Hostfully
                                  ?? null;

                    $payment->update([
                        'pms_payment_id'  => $pmsPaymentId,
                        'pms_sync_status' => 'synced',
                        'pms_sync_error'  => null,
                    ]);

                    // Hostfully order UID is booking-level (needed for future installments and refunds)
                    if (!empty($result['order_uid'])) {
                        $booking->update(['pms_order_uid' => $result['order_uid']]);
                    }

                    // ── Balance verification ──────────────────────────────────
                    $this->verifyPmsBalance($pms, $pmsBookingId, $result, $booking->id, $chargedAmount);

                } elseif ($result['not_supported'] ?? false) {
                    // Lodgify: no confirmed external-payment endpoint — not_supported is the
                    // accurate status; not_required would incorrectly imply sync is complete.
                    $payment->update([
                        'pms_sync_status' => 'not_supported',
                        'pms_sync_error'  => 'Lodgify: no confirmed external-payment endpoint. '
                                            . 'Stripe transaction stored locally. Manual reconciliation required.',
                    ]);
                    Log::channel('lodgify')->info('StripeController: Lodgify payment sync not supported — manual reconciliation required', [
                        'payment_id' => $payment->id,
                    ]);
                } else {
                    $errMsg = $result['error'] ?? 'recordPayment returned ok=false';
                    $payment->update([
                        'pms_sync_status' => 'failed',
                        'pms_sync_error'  => $errMsg,
                    ]);
                    Log::channel('lodgify')->warning("StripeController: recordPayment failed for {$pms}", [
                        'payment_id'     => $payment->id,
                        'pms_booking_id' => $pmsBookingId,
                        'error'          => $errMsg,
                    ]);
                }

                // Hostaway: also sync generated charges (authoritative amounts/dates)
                if ($pms === 'hostaway') {
                    $this->syncHostawayCharges($booking->fresh(), $pmsBookingId, $property);
                }
            }

            $this->sendBookingConfirmationEmails($booking->fresh(), $property, $payment);

            return redirect('pms/payment/success/' . $booking->id)
                ->with('success', 'Payment received and reservation confirmed!');
        }

        // PMS failed — determine the appropriate status.
        //
        // PmsBookingService may have already handled the failure (verification
        // mismatch → cancel + refund → booking-cancelled, or recovery failed →
        // reconciliation-needed).  In those cases the correct status is already
        // persisted; overwriting it with payment-captured-pms-failed would queue a
        // redundant recovery job on a booking that is already cancelled or in manual
        // review.
        $finalStatus = $this->classifyPmsFailure($booking->fresh(), $pmsResult);
        if ($finalStatus === 'payment-captured-pms-failed') {
            $booking->update(['booking_status' => 'payment-captured-pms-failed']);
        }

        Log::channel('lodgify')->error('StripeController::confirm: payment ok but PMS failed', [
            'booking_id'     => $id,
            'pms'            => $property->pms_source,
            'booking_status' => $finalStatus,
            'error'          => $pmsResult['error'] ?? 'unknown',
        ]);

        if ($this->shouldRefundNonRecoverablePmsFailure($pmsResult)) {
            try {
                $refund = Stripe\Refund::create(['payment_intent' => $piId]);
                $booking->update([
                    'booking_status' => 'booking-cancel',
                    'payment_status' => 'refunded',
                    'stripe_refund_id' => $refund->id ?? null,
                    'pms_sync_error' => $pmsResult['error'] ?? 'PMS rejected reservation',
                ]);
                $payment->update([
                    'status' => 'refunded',
                    'pms_sync_status' => 'not_required',
                    'pms_sync_error' => $pmsResult['error'] ?? null,
                ]);

                Log::channel('lodgify')->warning('StripeController::confirm: non-recoverable PMS failure refunded', [
                    'booking_id' => $id,
                    'pi_id'      => $piId,
                    'refund_id'  => $refund->id ?? null,
                    'error'      => $pmsResult['error'] ?? null,
                ]);

                return redirect('pms/payment/success/' . $booking->id)
                    ->with('warning', 'Your payment has been refunded because the PMS rejected this reservation: '
                        . ($pmsResult['error'] ?? 'reservation could not be created'));
            } catch (\Throwable $refundEx) {
                Log::channel('lodgify')->error('StripeController::confirm: non-recoverable PMS failure refund failed', [
                    'booking_id'   => $id,
                    'pi_id'        => $piId,
                    'refund_error' => $refundEx->getMessage(),
                ]);
            }
        }

        $msg = $this->guestMessageForPmsFailure($finalStatus, $booking->fresh(), $id);
        return redirect('pms/payment/success/' . $booking->id)
            ->with($msg['type'], $msg['message']);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Determine the booking_status to persist after a PmsBookingService failure.
     *
     * Rules:
     *   booking-cancelled      — PmsBookingService cancelled the PMS reservation
     *                            AND issued a Stripe refund successfully.  Do not
     *                            overwrite; no recovery job needed.
     *   reconciliation-needed  — recovery step (cancel or refund) failed after a
     *                            verification mismatch, or the guest's payment
     *                            requires manual review for another reason.  Do
     *                            not overwrite; a human must reconcile.
     *   payment-captured-pms-failed — genuine unhandled PMS error (network timeout,
     *                                 API 5xx, etc.). Queue for automated recovery.
     *
     * Extracted as a protected method so controller-level tests can exercise it
     * without going through the full HTTP/Stripe flow.
     */
    /**
     * Return the flash message type and text the guest should see after a PMS failure.
     *
     * Three distinct cases require different messaging:
     *   booking-cancelled       — PmsBookingService successfully cancelled the PMS
     *                             reservation AND refunded the guest. Tell them they were
     *                             refunded; do NOT say "confirmed shortly".
     *   reconciliation-needed   — at least one recovery step (cancel or refund) failed
     *                             and a human must intervene. Include the reference ID
     *                             so the guest can follow up.
     *   payment-captured-pms-failed — genuine unhandled PMS error; automated recovery
     *                             will retry. Standard "shortly" message is appropriate.
     *
     * Extracted as a protected method so controller-level tests can verify each message
     * without going through the full HTTP/Stripe flow.
     */
    protected function guestMessageForPmsFailure(string $status, BookingRequest $booking, $bookingId): array
    {
        return match ($status) {
            'booking-cancelled' => [
                'type'    => 'warning',
                'message' => 'Your payment has been refunded. Your booking could not be confirmed due to a price discrepancy. Please contact us if you need assistance.',
            ],
            'reconciliation-needed' => [
                'type'    => 'warning',
                'message' => 'Payment received. We could not complete your reservation automatically — our team will contact you within 24 hours for a resolution. Reference: '
                    . ($booking->request_id ?? (string)$bookingId),
            ],
            default => [
                'type'    => 'success',
                'message' => 'Payment received. Your reservation will be confirmed shortly — you will receive a confirmation email.',
            ],
        };
    }

    protected function classifyPmsFailure(BookingRequest $booking, array $pmsResult): string
    {
        $alreadyHandled = ['booking-cancelled', 'reconciliation-needed'];
        if (in_array($booking->booking_status, $alreadyHandled, true)) {
            return $booking->booking_status;
        }
        return 'payment-captured-pms-failed';
    }

    protected function shouldRefundNonRecoverablePmsFailure(array $pmsResult): bool
    {
        $error = strtolower((string) ($pmsResult['error'] ?? ''));

        foreach ([
            'minimum stay',
            'no longer available',
            'not available',
            'already booked',
            'date is no longer available',
        ] as $needle) {
            if (str_contains($error, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Availability + min-stay preflight check — runs BEFORE showing the payment form.
     * Returns ['ok' => true] or ['ok' => false, 'error' => '...'].
     */
    private function preflight(BookingRequest $booking, PmsRoom $property): array
    {
        try {
            $pmsManager = app(\App\Modules\VacationRental\Pms\PmsManager::class);
            $adapter    = $pmsManager->driver($property->pms_source);

            $nights = (new \DateTime($booking->checkin))->diff(new \DateTime($booking->checkout))->days;

            $raw  = $adapter->getAvailability([
                'property_id' => $property->pms_property_id,
                'room_id'     => $property->pms_room_id,
                'checkin'     => $booking->checkin,
                'checkout'    => $booking->checkout,
                'adults'      => 1,
            ]);

            $days = $raw['data']['result'] ?? $raw['data']['data'] ?? $raw['data'] ?? [];

            foreach ((array)$days as $day) {
                $date = $day['date'] ?? '';
                if ($date < $booking->checkin || $date >= $booking->checkout) continue;

                $avail  = $day['available'] ?? $day['isAvailable'] ?? true;
                $status = strtolower($day['status'] ?? 'available');
                if (!$avail || $status === 'unavailable' || $status === 'booked') {
                    return ['ok' => false, 'error' => "Sorry, {$date} is no longer available. Please select different dates."];
                }
            }

            // Min-stay check
            $maxMin  = max(
                (int)($property->custom_min_stay ?? 0),
                array_reduce((array)$days, fn($carry, $d) => max($carry, (int)($d['minimumStay'] ?? $d['minNights'] ?? 0)), 0)
            );
            if ($nights > 0 && $maxMin > 0 && $nights < $maxMin) {
                return ['ok' => false, 'error' => "A minimum stay of {$maxMin} nights is required. Your stay is {$nights} nights."];
            }

            return ['ok' => true];

        } catch (\Throwable $e) {
            Log::channel('lodgify')->warning('StripeController::preflight: API error — blocking booking (fail-closed)', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
            // Fail closed: unknown availability must never be treated as available.
            // PmsBookingService will perform its own recheck when the API recovers.
            return ['ok' => false, 'error' => 'Could not confirm availability right now. Please try again in a moment.'];
        }
    }

    /**
     * If the quote is older than 1 hour, re-fetch from PMS and update the booking.
     * Logs a notice if the price changed more than $0.50.
     */
    private function refreshQuoteIfExpired(BookingRequest $booking, PmsRoom $property): BookingRequest
    {
        $expired = empty($booking->quote_expires_at) || now()->isAfter($booking->quote_expires_at);
        if (!$expired) return $booking;

        try {
            $pmsManager = app(\App\Modules\VacationRental\Pms\PmsManager::class);
            $adapter    = $pmsManager->driver($property->pms_source);

            $input = [
                'property_id'       => $property->pms_property_id,
                'room_id'           => $property->pms_room_id,
                'checkin'           => $booking->checkin,
                'checkout'          => $booking->checkout,
                'adults'            => (int)($booking->adults ?? $booking->total_guests ?? 1),
                'children'          => (int)($booking->child ?? 0),
                'infants'           => (int)($booking->infants ?? 0),
                'pets'              => (int)($booking->total_pets ?? 0),
                'promo_code'        => $booking->discount_coupon ?? '',
                // Fix #3: validate stored IDs before re-quoting — never send raw browser
                // fee IDs to the PMS; null canonical → fail-closed (all stripped).
                'optional_services' => \App\Helper\FeeValidator::confirmed(
                    json_decode($booking->optional_services ?? '{}', true) ?: [],
                    $property
                ),
            ];

            $raw   = $adapter->getRawQuote($input);
            $quote = QuoteNormalizer::normalize($raw, $property->pms_source, $input);

            if ($quote['ok']) {
                // Block approximate quotes (Hostaway calendar fallback — taxes missing).
                // Do not allow payment to proceed on a price that excludes taxes.
                if (!empty($quote['approximate'])) {
                    Log::channel('lodgify')->warning('StripeController: approximate quote returned — blocking payment form', [
                        'booking_id' => $booking->id,
                        'note'       => $quote['approximate_note'] ?? 'taxes not included',
                    ]);
                    // Return the booking unchanged — the caller will find no quote_expires_at
                    // and the index() method will re-attempt. We throw so the catch extends expiry.
                    throw new \RuntimeException('Approximate quote: ' . ($quote['approximate_note'] ?? 'taxes unavailable'));
                }

                $oldTotal  = (float)$booking->total_amount;
                $newTotal  = (float)$quote['total'];
                $currency  = $quote['currency'] ?? $booking->currency ?? 'USD';

                // ── Price changed: require guest acceptance, DO NOT silently update ─
                // Fix #4: compare in integer minor units — zero tolerance.
                // Any change (even 1 cent) requires guest re-acceptance.
                if (StripeHelper::toStripeAmount($newTotal, $currency)
                    !== StripeHelper::toStripeAmount($oldTotal, $currency)) {
                    Log::channel('lodgify')->warning('StripeController: quote total changed — requiring guest acceptance', [
                        'booking_id' => $booking->id,
                        'old_total'  => $oldTotal,
                        'new_total'  => $newTotal,
                        'diff'       => round($newTotal - $oldTotal, 2),
                    ]);
                    // Store pending total and flag for the payment page
                    $booking->update([
                        'quote_expires_at'    => now()->addHour(),
                        'quote_data_all'      => json_encode($raw),
                        'price_changed_to'    => $newTotal,    // guest must accept this
                    ]);
                    $booking->refresh();
                    return $booking; // return without updating total_amount
                }

                // Price unchanged — safe to update.
                // Fix #8: also rebuild deposit_due_amount so the PI amount stays correct.
                $scheduledPayments = $quote['scheduled_payments'] ?? [];
                $amountData        = [];
                foreach ($scheduledPayments as $sp) {
                    if (in_array(strtolower($sp['type'] ?? ''), ['payment', 'deposit', 'balance'])) {
                    $amountData[] = [
                        'amount'     => (float)$sp['amount'],
                        'date_due'   => $sp['date_due'] ?? $sp['due_date'] ?? $sp['date'] ?? '',
                        'label'      => $sp['label']    ?? '',
                        'is_current' => $sp['is_current'] ?? false,
                    ];
                    }
                }
                $today      = now()->toDateString();
                $dueNow     = array_filter($amountData, fn($r) =>
                    ($r['is_current'] ?? false) || (empty($r['date_due']) || $r['date_due'] <= $today));
                $depositDue = !empty($dueNow)
                    ? (float)array_sum(array_column($dueNow, 'amount'))
                    : (!empty($amountData) ? (float)$amountData[0]['amount'] : $newTotal);

                $updateFields = [
                    'total_amount'     => $newTotal,
                    'quote_expires_at' => now()->addHour(),
                    'quote_data_all'   => json_encode($raw),
                    'price_changed_to' => null, // clear any previous pending change
                ];
                if (!empty($amountData)) {
                    $updateFields['amount_data']         = json_encode($amountData);
                    $updateFields['deposit_due_amount']  = $depositDue;
                }
                $booking->update($updateFields);
                $booking->refresh();
            }
        } catch (\Throwable $e) {
            $isApproximate = str_starts_with($e->getMessage(), 'Approximate quote:');

            // ── Fail-closed: block payment if re-quote fails ─────────────────
            // We must NEVER charge based on cached pricing that may no longer be valid.
            // Mark the booking so the payment page shows an error rather than proceeding.
            Log::channel('lodgify')->warning('StripeController: quote refresh failed — blocking payment (fail-closed)', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
            $booking->update([
                'quote_expires_at' => now()->subSecond(), // force expiry → re-check next load
                'quote_refresh_failed' => true,
            ]);
            $booking->refresh();
            throw $e; // propagate so index() can redirect with an error
        }

        return $booking;
    }

    /**
     * Create a Stripe PaymentIntent for this booking, or retrieve the existing one.
     * Idempotent: a second call for the same booking returns the same PI.
     * If the PI was cancelled, creates a new one.
     *
     * Also creates/retrieves a Stripe Customer so the card is saved for:
     *   - Security deposit pre-auth (same card, manual capture)
     *   - Future scheduled installment charges (off-session)
     */
    private function getOrCreatePaymentIntent(BookingRequest $booking, PmsRoom $property): Stripe\PaymentIntent
    {
        $currency = strtolower($booking->currency ?: 'usd');

        // Charge the deposit (first installment) when a payment schedule exists.
        // deposit_due_amount = first installment dollar value set by PageController.
        // total_amount       = full booking value.
        // total_payment      = legacy integer COUNT field (1/2/3) — never use as dollars.
        $depositDue  = (float)($booking->deposit_due_amount ?? 0);
        $totalAmount = (float)$booking->total_amount;
        $chargeAmount = ($depositDue > 0 && $depositDue < $totalAmount)
            ? $depositDue
            : $totalAmount;
        $amount = StripeHelper::toStripeAmount($chargeAmount, strtoupper($currency));

        // Retrieve existing PI if we have one
        if (!empty($booking->stripe_payment_intent_id)) {
            try {
                $pi = Stripe\PaymentIntent::retrieve($booking->stripe_payment_intent_id);

                // If the PI is still usable, update amount in case quote was refreshed
                if (!in_array($pi->status, ['canceled', 'succeeded'])) {
                    if ($pi->amount !== $amount) {
                        $pi = Stripe\PaymentIntent::update($pi->id, ['amount' => $amount]);
                    }
                    return $pi;
                }
            } catch (\Throwable $e) {
                // PI not found or invalid — fall through to create a new one
                Log::channel('lodgify')->warning('StripeController: could not retrieve existing PI, creating new', [
                    'booking_id' => $booking->id,
                    'pi_id'      => $booking->stripe_payment_intent_id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // Get or create Stripe Customer (so we can save the payment method)
        $customer = Stripe\Customer::create([
            'email'       => $booking->email,
            'name'        => $booking->name,
            'description' => "Booking #{$booking->id}",
            'metadata'    => ['booking_id' => $booking->id],
        ]);

        // Create new PaymentIntent
        $pi = Stripe\PaymentIntent::create([
            'amount'               => $amount,
            'currency'             => $currency,
            'customer'             => $customer->id,
            'payment_method_types' => ['card'],
            // setup_future_usage: saves PM to customer for off-session installments
            // and ensures SCA authentication is performed upfront
            'setup_future_usage'   => 'off_session',
            'description'          => "Booking #{$booking->id} — {$property->name}",
            'metadata'             => [
                'booking_id'  => $booking->id,
                'property'    => $property->name,
                'checkin'     => $booking->checkin,
                'checkout'    => $booking->checkout,
            ],
        ]);

        $booking->update(['stripe_payment_intent_id' => $pi->id]);

        return $pi;
    }

    private function preAuthorizeDeposit(BookingRequest $booking, ?string $customerId, ?string $pmId): void
    {
        try {
            $normalizedQuote = null;
            if (!empty($booking->quote_data_all)) {
                // Re-normalize from stored quote to get security deposit amount
                $room = PmsRoom::find($booking->property_id);
                if ($room) {
                    $raw   = json_decode($booking->quote_data_all, true);
                    $quote = QuoteNormalizer::normalize($raw, $room->pms_source, []);
                    if ($quote['ok']) $normalizedQuote = $quote;
                }
            }

            $depositAmount = (float)($normalizedQuote['security_deposit'] ?? 0);
            if ($depositAmount <= 0 || !$customerId || !$pmId) return;

            $currency = strtolower($normalizedQuote['currency'] ?? $booking->currency ?? 'usd');

            // Use PaymentIntent with capture_method=manual for the deposit hold.
            // capture_method=manual = authorize only; no money taken until admin calls ->capture().
            $depositPi = Stripe\PaymentIntent::create([
                'amount'         => StripeHelper::toStripeAmount($depositAmount, strtoupper($currency)),
                'currency'       => $currency,
                'customer'       => $customerId,
                'payment_method' => $pmId,
                'capture_method' => 'manual',
                'off_session'    => true,
                'confirm'        => true,
                'description'    => "Security deposit pre-auth — Booking #{$booking->id} ({$booking->name})",
                'metadata'       => ['booking_id' => $booking->id, 'type' => 'security_deposit'],
            ]);

            $booking->update([
                'stripe_deposit_charge_id' => $depositPi->id,  // stores pi_xxx (not ch_xxx)
                'security_deposit_amount'  => $depositAmount,
                'security_deposit_currency'=> strtoupper($currency),
                'security_deposit_status'  => 'authorized',
            ]);

            Log::channel('lodgify')->info('StripeController: security deposit pre-authorized (PI)', [
                'booking_id' => $booking->id,
                'pi_id'      => $depositPi->id,
                'amount'     => $depositAmount,
            ]);

        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('StripeController: deposit pre-auth failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
            $booking->update(['security_deposit_status' => 'failed']);
        }
    }

    private function saveInstallments(BookingRequest $booking, ?string $pmId, ?string $customerId, array $pmsResult): void
    {
        try {
            $normalizedQuote = $pmsResult['normalized_quote'] ?? null;
            if (!$normalizedQuote) return;

            $schedule = $normalizedQuote['scheduled_payments'] ?? [];
            if (empty($schedule)) {
                $schedule = json_decode((string) ($booking->amount_data ?? '[]'), true) ?: [];
            }
            if (empty($schedule)) return;

            $today        = now()->toDateString();
            $pmsSource    = $pmsResult['pms_source'] ?? '';
            $pmsBookingId = $pmsResult['pms_booking_id'] ?? null;
            $currency     = strtoupper($normalizedQuote['currency'] ?? $booking->currency ?? 'USD');

            if (empty($pmsSource)) {
                $room = PmsRoom::find($booking->property_id);
                $pmsSource = $room->pms_source ?? '';
            }

            foreach ($schedule as $installment) {
                $dueDate = $installment['date_due'] ?? $installment['due_date'] ?? $installment['date'] ?? null;
                $amount  = (float)($installment['amount'] ?? 0);
                $type    = strtolower($installment['type'] ?? 'payment');

                if ($type !== 'payment') continue;
                // Lodgify formats due dates as "Jul 13 2026" (and "On agreement"
                // for the due-now installment). The strict Y-m-d check silently
                // dropped every future installment — normalize real dates first;
                // non-dates ("On agreement") still skip (already charged today).
                if ($dueDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
                    $ts      = strtotime($dueDate);
                    $dueDate = $ts ? date('Y-m-d', $ts) : null;
                }
                if (!$dueDate) continue;
                if ($dueDate <= $today) continue;
                if ($amount <= 0) continue;

                ScheduledPayment::create([
                    'booking_request_id'      => $booking->id,
                    'pms_source'              => $pmsSource,
                    'pms_booking_id'          => $pmsBookingId,
                    'stripe_customer_id'      => $customerId,
                    'stripe_payment_method_id'=> $pmId,      // NEW: store PM for PI-based off-session charges
                    'amount'                  => $amount,
                    'currency'               => $currency,
                    'due_date'               => $dueDate,
                    'status'                 => 'pending',
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('StripeController: failed to save installments', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    private function sendBookingConfirmationEmails(BookingRequest $booking, PmsRoom $property, Payment $payment): void
    {
        try {
            app(\App\Services\Email\BookingLifecycleMailer::class)
                ->sendConfirmationAndReceipt($booking, $property, $payment);
        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('StripeController: confirmation emails failed', [
                'booking_id' => $booking->id,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch the authoritative Hostaway charge schedule and update booking amount_data.
     *
     * After a Hostaway reservation is created, /v1/guestPayments/charges returns the
     * actual calculated installments (amounts + dates). These replace the estimate built
     * from auto-payment rules so guests and scheduled jobs have accurate figures.
     */
    private function syncHostawayCharges(BookingRequest $booking, string $reservationId, \App\Models\Pms\PmsRoom $property): void
    {
        try {
            $adapter = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver('hostaway');
            $result  = $adapter->getReservationCharges($reservationId);

            if (!$result['ok'] || empty($result['charges'])) {
                Log::channel('lodgify')->info('StripeController: no Hostaway charges returned', [
                    'booking_id'     => $booking->id,
                    'reservation_id' => $reservationId,
                ]);
                return;
            }

            $charges = $result['charges'];
            $amountData = [];
            foreach ($charges as $i => $charge) {
                $amt  = (float)($charge['amount'] ?? 0);
                if ($amt <= 0) continue;
                $scheduledDate = $charge['scheduledDate'] ?? null;
                $triggerEvent  = strtolower($charge['triggerEvent'] ?? 'payment');
                $status        = strtolower($charge['status'] ?? 'pending');
                $count         = count($charges);

                $label = $count === 1 ? 'Full payment'
                    : ($i === 0 ? 'Deposit' : ($i === $count - 1 ? 'Balance' : 'Payment ' . ($i + 1) . ' of ' . $count));

                $amountData[] = [
                    'amount'          => $amt,
                    'date_due'        => $scheduledDate,
                    'label'           => $label,
                    'hostaway_status' => $status,
                ];
            }

            if (empty($amountData)) return;

            // First charge = what the guest just paid; update deposit_due_amount to match.
            // Subsequent charges = future installments for the scheduled job.
            $depositDue = (float)$amountData[0]['amount'];

            $booking->update([
                'amount_data'        => json_encode($amountData),
                'deposit_due_amount' => $depositDue,
            ]);

            Log::channel('lodgify')->info('StripeController: Hostaway charges synced', [
                'booking_id'     => $booking->id,
                'reservation_id' => $reservationId,
                'charges_count'  => count($amountData),
                'deposit_due'    => $depositDue,
            ]);

        } catch (\Throwable $e) {
            Log::channel('lodgify')->error('StripeController: Hostaway charge sync failed', [
                'booking_id'     => $booking->id,
                'reservation_id' => $reservationId,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    /**
     * After recording a payment, re-fetch the PMS booking and log the remaining balance.
     * This is a verification step — it does not fail the booking if the balance looks wrong,
     * but it does log a warning so discrepancies are visible in the log.
     *
     * Hostaway: GET /v1/reservations/{id} → remainingBalance
     *   Compares against expected = booking_total - sum_of_all_successful_local_payments.
     *   remainingBalance > 0 is NORMAL for split payments; only warn when it deviates
     *   from the expected amount by more than a rounding tolerance.
     * Hostfully: GET /api/v3.3/transactions?orderUid={uid} → confirm new transaction present.
     * Guesty: skipped (requires money.payments field query — reconcile via scheduled webhook).
     *
     * @param float $justChargedAmount Amount charged in the current payment (major units)
     */
    private function verifyPmsBalance(string $pms, string $pmsBookingId, array $recordResult, int $bookingId, float $justChargedAmount = 0.0): void
    {
        try {
            $adapter = app(\App\Modules\VacationRental\Pms\PmsManager::class)->driver($pms);

            if ($pms === 'hostaway') {
                $res     = $adapter->getReservation($pmsBookingId);
                $balance = $res['data']['result']['remainingBalance']
                          ?? $res['data']['remainingBalance']
                          ?? null;

                if ($balance !== null) {
                    // Expected remaining = booking total − net payments (payments − refunds).
                    // Uses 5-cent tolerance to absorb FX rounding differences.
                    $booking = \App\Models\BookingRequest::find($bookingId);
                    if ($booking) {
                        $totalPaid = \App\Models\Payment::where('booking_id', $bookingId)
                                        ->where('status', 'complete')
                                        ->sum('amount');

                        // Sum refunds that are confirmed synced to the PMS.
                        // Pending/failed refund rows are not yet reflected in Hostaway's
                        // remainingBalance, so include only 'synced' refunds.
                        $paymentIds   = \App\Models\Payment::where('booking_id', $bookingId)->pluck('id');
                        $totalRefunded = \App\Models\PaymentRefund::whereIn('payment_id', $paymentIds)
                                            ->where('pms_sync_status', 'synced')
                                            ->sum('amount');

                        $expected  = round((float)$booking->total_amount - (float)$totalPaid + (float)$totalRefunded, 2);
                    } else {
                        $expected = null;
                    }
                    // Currency-aware tolerance.
                    // Zero-decimal currencies (JPY, KRW, …) are stored as whole units —
                    // $0.05 would mean 5 yen, which is too wide. Use 1 unit for those.
                    // Standard currencies (USD, EUR, …) allow 5 cents for FX rounding.
                    $zeroDecimal = ['JPY','KRW','VND','BIF','CLP','GNF','ISK','MGA','PYG','RWF','UGX','XAF','XOF','XPF'];
                    $bookingCcy  = strtoupper($booking?->currency ?? 'USD');
                    $tolerance   = in_array($bookingCcy, $zeroDecimal) ? 1.0 : 0.05;

                    Log::channel('lodgify')->info('verifyPmsBalance: Hostaway remainingBalance', [
                        'booking_id'       => $bookingId,
                        'reservation_id'   => $pmsBookingId,
                        'remaining_balance'=> $balance,
                        'expected'         => $expected,
                    ]);

                    if ($expected !== null && abs((float)$balance - $expected) > $tolerance) {
                        Log::channel('lodgify')->warning('verifyPmsBalance: Hostaway remainingBalance deviates from expected', [
                            'booking_id' => $bookingId,
                            'remaining'  => $balance,
                            'expected'   => $expected,
                            'diff'       => round((float)$balance - $expected, 4),
                        ]);
                    }
                }

            } elseif ($pms === 'hostfully') {
                $orderUid = $recordResult['order_uid'] ?? null;
                $txnUid   = $recordResult['transaction_uid'] ?? null;
                if (!$orderUid || !$txnUid) return;

                $txns     = $adapter->request('GET', "api/v3.3/transactions?orderUid={$orderUid}");
                $list     = $txns['data']['transactions'] ?? $txns['data'] ?? [];
                $found    = collect((array)$list)->contains(fn($t) => ($t['uid'] ?? '') === $txnUid);

                if (!$found) {
                    Log::channel('lodgify')->warning('verifyPmsBalance: Hostfully transaction not found after POST', [
                        'booking_id'  => $bookingId,
                        'order_uid'   => $orderUid,
                        'txn_uid'     => $txnUid,
                    ]);
                } else {
                    Log::channel('lodgify')->info('verifyPmsBalance: Hostfully transaction confirmed', [
                        'booking_id' => $bookingId,
                        'txn_uid'    => $txnUid,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Non-fatal — balance verification is informational only
            Log::channel('lodgify')->warning('verifyPmsBalance: exception (non-fatal)', [
                'booking_id' => $bookingId,
                'pms'        => $pms,
                'error'      => $e->getMessage(),
            ]);
        }
    }
}
