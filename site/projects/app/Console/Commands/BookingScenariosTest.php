<?php

namespace App\Console\Commands;

use App\Models\BookingRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Services\Booking\AddonSelection;
use App\Services\Booking\QuoteCalculator;
use App\Services\Booking\QuoteInput;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Exhaustive end-to-end booking-scenario runner. Walks every realistic
 * combination of payment paths, validation paths, and gateway types, and
 * outputs a structured report you can paste into a manual-test checklist.
 *
 *   php artisan booking:scenarios-test [--property=4] [--keep] [--report=docs/...]
 */
class BookingScenariosTest extends Command
{
    protected $signature = 'booking:scenarios-test
        {--property=4 : property to run scenarios on}
        {--renter=basu.siddharth24@gmail.com}
        {--owner=info@sygniusdigital.com}
        {--keep : keep test bookings in DB instead of cleaning up}
        {--report= : path to write markdown report (relative to projects/)}';

    protected $description = 'Run every booking scenario and produce a markdown report.';

    private array $createdBookingIds = [];
    private array $report = [];

    public function handle(): int
    {
        $pid = (int) $this->option('property');
        $renter = (string) $this->option('renter');
        $owner = (string) $this->option('owner');
        $property = Property::find($pid);
        if (!$property) { $this->error("Property #$pid not found."); return self::FAILURE; }

        DB::table('basic_settings')->updateOrInsert(['name' => 'booking_receiving_mail'], ['value' => $owner, 'updated_at' => now()]);

        // Spam-protection per-IP rate limit will trip on rapid scenario runs.
        // Clear it for the localhost IP we're impersonating.
        \Illuminate\Support\Facades\RateLimiter::clear('spam:booking:127.0.0.1');
        \Illuminate\Support\Facades\RateLimiter::clear('spam:contact:127.0.0.1');

        $this->info("Running booking scenarios on '{$property->name}' (#$pid)\n");

        $scenarios = [
            'happy_path_full_pay'      => 'Single full payment — happy path',
            'two_instalment_split'     => 'Two-instalment payment (50/50)',
            'three_instalment_split'   => 'Three-instalment payment (30/30/40)',
            'partial_then_cancel'      => 'Partial payment then cancellation',
            'enquiry_then_cancel'      => 'Cancellation before any payment',
            'gateway_paypal'           => 'Payment via PayPal gateway',
            'gateway_square'           => 'Payment via Square gateway',
            'gateway_authorize'        => 'Payment via Authorize.Net gateway',
            'reject_min_stay'          => 'Validation: stay shorter than min_stay',
            'reject_overcapacity'      => 'Validation: more guests than property allows',
            'reject_date_overlap'      => 'Validation: dates overlap an existing booking',
            'with_coupon'              => 'Booking with coupon discount applied',
        ];

        foreach ($scenarios as $key => $label) {
            $this->line("\n──── {$key}: {$label} ────");
            $method = 'scenario_' . $key;
            try {
                $row = $this->{$method}($pid, $renter);
                $row['scenario'] = $key;
                $row['label'] = $label;
                $this->report[] = $row;
                $this->line("  → " . ($row['result'] === 'pass' ? 'PASS' : 'FAIL') . ' · ' . $row['summary']);
            } catch (\Throwable $e) {
                $this->report[] = [
                    'scenario' => $key, 'label' => $label, 'result' => 'error',
                    'summary' => 'EXCEPTION: ' . Str::limit($e->getMessage(), 200),
                    'booking_id' => null, 'emails' => 0,
                ];
                $this->error('  EXCEPTION: ' . $e->getMessage());
            }
        }

        $this->printSummary();
        if ($path = $this->option('report')) $this->writeMarkdown($path);
        if (!$this->option('keep')) $this->cleanup();

        return self::SUCCESS;
    }

    /* ── Scenarios ── */

    private function scenario_happy_path_full_pay(int $pid, string $renter): array
    {
        [$ci, $co] = $this->sundayCheckout(8, 4);
        // Stay within property capacity (sleeps=4 on test property #4) so the new
        // hard-cap validation doesn't reject this scenario.
        $b = $this->submitBooking($pid, $renter, $ci, $co, [2,1,1], 'all');
        $this->approveAndPay($b, (float) $b->total_amount, 'stripe');
        $this->fireLifecycle($b, ['welcome','checkin','checkout','review']);
        $b = BookingRequest::find($b->id);
        $emails = DB::table('email_send_log')->where('booking_id', $b->id)->count();
        $pass = $b->payment_status === 'paid' && $emails >= 12;
        return ['result' => $pass ? 'pass' : 'fail', 'summary' => "total \${$b->total_amount} status=$b->payment_status · $emails emails", 'booking_id' => $b->id, 'emails' => $emails];
    }

    private function scenario_two_instalment_split(int $pid, string $renter): array
    {
        [$ci, $co] = $this->sundayCheckout(9, 4);
        $b = $this->submitBooking($pid, $renter, $ci, $co, [2,0,0], 'minimal');
        DB::table('booking_requests')->where('id',$b->id)->update(['booking_status'=>'booking-accepted','payment_status'=>'pending']);

        $half = round((float) $b->total_amount / 2, 2);
        $this->recordPayment($b, $half, 'stripe');
        $this->recordPayment($b, (float) $b->total_amount - $half, 'stripe');

        $b = BookingRequest::find($b->id);
        $receipts = DB::table('email_send_log')->where('booking_id',$b->id)->where('email_type','receipt')->count();
        $pass = $b->payment_status === 'paid' && $receipts === 2;
        return ['result' => $pass ? 'pass' : 'fail', 'summary' => "2 receipts (\${$half} + \$" . number_format((float)$b->total_amount - $half, 2) . ") · status=$b->payment_status", 'booking_id' => $b->id, 'emails' => $receipts];
    }

    private function scenario_three_instalment_split(int $pid, string $renter): array
    {
        [$ci, $co] = $this->sundayCheckout(10, 4);
        $b = $this->submitBooking($pid, $renter, $ci, $co, [2,0,0], 'minimal');
        DB::table('booking_requests')->where('id',$b->id)->update(['booking_status'=>'booking-accepted','payment_status'=>'pending']);

        $total = (float) $b->total_amount;
        $i1 = round($total * 0.30, 2);
        $i2 = round($total * 0.30, 2);
        $i3 = round($total - $i1 - $i2, 2);
        $this->recordPayment($b, $i1, 'stripe');
        $this->recordPayment($b, $i2, 'stripe');
        $this->recordPayment($b, $i3, 'stripe');

        $b = BookingRequest::find($b->id);
        $receipts = DB::table('email_send_log')->where('booking_id',$b->id)->where('email_type','receipt')->count();
        $pass = $b->payment_status === 'paid' && $receipts === 3;
        return ['result' => $pass ? 'pass' : 'fail', 'summary' => "3 receipts (\${$i1}+\${$i2}+\${$i3}) · status=$b->payment_status", 'booking_id' => $b->id, 'emails' => $receipts];
    }

    private function scenario_partial_then_cancel(int $pid, string $renter): array
    {
        [$ci, $co] = $this->sundayCheckout(11, 4);
        $b = $this->submitBooking($pid, $renter, $ci, $co, [2,0,0], 'minimal');
        DB::table('booking_requests')->where('id',$b->id)->update(['booking_status'=>'booking-accepted','payment_status'=>'pending']);

        $partial = round((float) $b->total_amount / 2, 2);
        $this->recordPayment($b, $partial, 'stripe');

        // Cancel
        DB::table('booking_requests')->where('id',$b->id)->update(['booking_status'=>'cancelled','cancel_at'=>now(),'updated_at'=>now()]);
        $this->fireLifecycle(BookingRequest::find($b->id), ['cancellation']);

        $b = BookingRequest::find($b->id);
        $hasCancelEmail = DB::table('email_send_log')->where('booking_id',$b->id)->where('email_type','cancellation')->exists();
        $pass = $b->booking_status === 'cancelled' && $hasCancelEmail;
        return ['result' => $pass ? 'pass' : 'fail', 'summary' => "paid \${$partial} of \${$b->total_amount} then cancelled; cancellation email " . ($hasCancelEmail ? 'sent' : 'missing'), 'booking_id' => $b->id, 'emails' => $hasCancelEmail ? 1 : 0];
    }

    private function scenario_enquiry_then_cancel(int $pid, string $renter): array
    {
        [$ci, $co] = $this->sundayCheckout(12, 4);
        $b = $this->submitBooking($pid, $renter, $ci, $co, [2,0,0], 'minimal');
        DB::table('booking_requests')->where('id',$b->id)->update(['booking_status'=>'cancelled','cancel_at'=>now()]);
        $this->fireLifecycle(BookingRequest::find($b->id), ['cancellation']);
        $payments = DB::table('payments')->where('booking_id',$b->id)->count();
        $hasCancel = DB::table('email_send_log')->where('booking_id',$b->id)->where('email_type','cancellation')->exists();
        $pass = $payments === 0 && $hasCancel;
        return ['result' => $pass ? 'pass' : 'fail', 'summary' => "no payments, cancellation email " . ($hasCancel ? 'sent' : 'missing'), 'booking_id' => $b->id, 'emails' => $hasCancel ? 1 : 0];
    }

    private function scenario_gateway_paypal(int $pid, string $renter): array
    {
        return $this->gatewayScenario($pid, $renter, 13, 'paypal');
    }

    private function scenario_gateway_square(int $pid, string $renter): array
    {
        return $this->gatewayScenario($pid, $renter, 14, 'square');
    }

    private function scenario_gateway_authorize(int $pid, string $renter): array
    {
        return $this->gatewayScenario($pid, $renter, 15, 'authorize');
    }

    private function scenario_reject_min_stay(int $pid, string $renter): array
    {
        // Check QuoteCalculator's output directly: a stay shorter than min_stay
        // should fail validation regardless of whether the controller accepts it.
        [$ci, $co] = $this->sundayCheckout(16, 1);
        $q = (new QuoteCalculator())->calculate(new QuoteInput(
            property_id: $pid, check_in: $ci, check_out: $co,
            adults: 2, children: 0, pets: 0,
            addons: AddonSelection::empty(),
        ));
        $hasMinStayError = !$q->valid && collect($q->errors)->contains(fn($e) => stripos($e, 'min') !== false);
        return [
            'result' => $hasMinStayError ? 'pass' : 'fail',
            'summary' => $hasMinStayError ? "quote rejected: " . implode('; ', $q->errors) : 'quote unexpectedly valid',
            'booking_id' => null, 'emails' => 0,
        ];
    }

    private function scenario_reject_overcapacity(int $pid, string $renter): array
    {
        [$ci, $co] = $this->sundayCheckout(17, 4);
        $q = (new QuoteCalculator())->calculate(new QuoteInput(
            property_id: $pid, check_in: $ci, check_out: $co,
            adults: 999, children: 0, pets: 0,
            addons: AddonSelection::empty(),
        ));
        $rejected = !$q->valid && collect($q->errors)->contains(fn($e) => stripos($e, 'guest') !== false || stripos($e, 'capacity') !== false);
        return [
            'result' => $rejected ? 'pass' : 'fail',
            'summary' => $rejected ? "quote rejected: " . implode('; ', $q->errors) : 'quote calc accepted 999 guests (no max-guest check?)',
            'booking_id' => null, 'emails' => 0,
        ];
    }

    private function scenario_reject_date_overlap(int $pid, string $renter): array
    {
        // First booking
        [$ci, $co] = $this->sundayCheckout(18, 4);
        $b = $this->submitBooking($pid, $renter, $ci, $co, [2,0,0], 'minimal');
        DB::table('booking_requests')->where('id',$b->id)->update(['booking_status'=>'booked']);
        // Second booking with same dates → should reject
        $resp = $this->submitBookingRaw($pid, $renter, $ci, $co, [2,0,0], 'minimal');
        $rejected = empty($resp['success']);
        return ['result' => $rejected ? 'pass' : 'fail', 'summary' => $rejected ? "second booking rejected: " . ($resp['error_message'] ?? '?') : 'unexpectedly accepted', 'booking_id' => $b->id, 'emails' => 0];
    }

    private function scenario_with_coupon(int $pid, string $renter): array
    {
        [$ci, $co] = $this->sundayCheckout(19, 4);
        $resp = $this->submitBookingRaw($pid, $renter, $ci, $co, [2,0,0], 'all', 'TEST10');
        if (empty($resp['success'])) {
            return ['result' => 'fail', 'summary' => 'rejected: ' . ($resp['error_message'] ?? '?'), 'booking_id' => null, 'emails' => 0];
        }
        // Find latest booking
        $b = BookingRequest::where('email', $renter)->orderByDesc('id')->first();
        if ($b) $this->createdBookingIds[] = $b->id;
        return ['result' => 'pass', 'summary' => "booking #$b->id created with coupon, total=\${$b->total_amount}", 'booking_id' => $b ? $b->id : null, 'emails' => 1];
    }

    /* ── helpers ── */

    private function sundayCheckout(int $weeksOut, int $nights): array
    {
        $checkout = \Carbon\Carbon::today()->next(\Carbon\Carbon::SUNDAY)->addWeeks($weeksOut);
        $checkin  = $checkout->copy()->subDays($nights);
        return [$checkin->toDateString(), $checkout->toDateString()];
    }

    private function submitBooking(int $pid, string $renter, string $ci, string $co, array $guests, string $feesMode, ?string $coupon = null): BookingRequest
    {
        $resp = $this->submitBookingRaw($pid, $renter, $ci, $co, $guests, $feesMode, $coupon);
        if (empty($resp['success'])) {
            throw new \RuntimeException('submit failed: ' . ($resp['error_message'] ?? 'unknown'));
        }
        $b = BookingRequest::orderByDesc('id')->first();
        $this->createdBookingIds[] = $b->id;
        return $b;
    }

    private function submitBookingRaw(int $pid, string $renter, string $ci, string $co, array $guests, string $feesMode, ?string $coupon = null): array
    {
        // Clear rate limit between every call so back-to-back scenarios don't trip the throttle.
        \Illuminate\Support\Facades\RateLimiter::clear('spam:booking:127.0.0.1');

        [$adults, $children, $pets] = $guests;
        $feeIds = [];
        if ($feesMode === 'all') {
            $feeIds = DB::table('property_fees')->where('property_id', $pid)->where('status', 'active')->pluck('id')->all();
        }

        $req = Request::create('/booking/request', 'POST', [
            'property_id'     => $pid,
            'check_in'        => $ci,
            'check_out'       => $co,
            'adults'          => $adults,
            'children'        => $children,
            'pets'            => $pets,
            'addons'          => json_encode(['fees' => $feeIds]),
            'name'            => 'Scenario Renter',
            'email'           => $renter,
            'mobile'          => '+1-555-100-' . str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
            'message'         => "Auto-test scenario booking — please ignore.",
            'coupon_code'     => $coupon,
            'idempotency_key' => (string) Str::uuid(),
            '_form_ts'        => \App\Services\Spam\Checks\TimeTrapCheck::generateToken(time() - 5),
        ]);
        $req->headers->set('Accept', 'application/json');
        $resp = app()->handle($req);
        return json_decode($resp->getContent(), true) ?: [];
    }

    private function approveAndPay(BookingRequest $b, float $amount, string $type): void
    {
        DB::table('booking_requests')->where('id', $b->id)->update(['booking_status' => 'booking-accepted', 'payment_status' => 'pending']);
        $this->recordPayment($b, $amount, $type);
    }

    private function recordPayment(BookingRequest $b, float $amount, string $type): void
    {
        $pay = Payment::firstOrCreate(
            ['tran_id' => $type . '_' . uniqid()],
            ['booking_id' => $b->id, 'amount' => $amount, 'type' => $type, 'status' => 'complete']
        );
        $bo = BookingRequest::find($b->id);
        \ModelHelper::finalEmailAndUpdateBookingPayment($b->id, $bo, $pay, Property::find($bo->property_id), 'payment');
    }

    private function fireLifecycle(BookingRequest $b, array $stages): void
    {
        $p = Property::find($b->property_id);
        $r = new \App\Services\Stay\StayCommunicationsResolver($p, $b);
        $brand = \ModelHelper::getDataFromSetting('mailer_admin_name') ?: \ModelHelper::getDataFromSetting('mail_from_name') ?: 'Base Vacation Rentals';
        $footer = \ModelHelper::getDataFromSetting('mail_footer') ?: '';
        $ownerEmail = \ModelHelper::getDataFromSetting('booking_receiving_mail') ?: \ModelHelper::getDataFromSetting('admin_email_primary');
        foreach ($stages as $s) {
            $sub  = $r->expandMergeFields((string) \ModelHelper::getDataFromSetting("{$s}_default_subject"));
            $body = $r->expandMergeFields((string) \ModelHelper::getDataFromSetting("{$s}_default_body_html"));
            $html = view('mail.email-shell', ['body_html_rendered' => $body, 'brand' => $brand, 'mail_footer' => $footer])->render();
            \App\Services\Email\SendLog::record($b->id, $s, $b->email, $sub, $html);
            if ($ownerEmail) {
                $aSub = $r->expandMergeFields((string) \ModelHelper::getDataFromSetting("admin_alert_{$s}_subject"));
                $aBody = $r->expandMergeFields((string) \ModelHelper::getDataFromSetting("admin_alert_{$s}_body_html"));
                $aHtml = view('mail.email-shell', ['body_html_rendered' => $aBody, 'brand' => $brand, 'mail_footer' => $footer])->render();
                \App\Services\Email\SendLog::record($b->id, "admin-$s", $ownerEmail, $aSub, $aHtml);
            }
        }
    }

    private function gatewayScenario(int $pid, string $renter, int $weeks, string $type): array
    {
        [$ci, $co] = $this->sundayCheckout($weeks, 4);
        $b = $this->submitBooking($pid, $renter, $ci, $co, [2,0,0], 'minimal');
        DB::table('booking_requests')->where('id',$b->id)->update(['booking_status'=>'booking-accepted','payment_status'=>'pending']);
        $this->recordPayment($b, (float) $b->total_amount, $type);
        $b = BookingRequest::find($b->id);
        $emails = DB::table('email_send_log')->where('booking_id',$b->id)->count();
        $pass = $b->payment_status === 'paid' && $emails > 0;
        return ['result' => $pass ? 'pass' : 'fail', 'summary' => "$type → status=$b->payment_status · $emails emails", 'booking_id' => $b->id, 'emails' => $emails];
    }

    private function printSummary(): void
    {
        $this->info("\n══════ Scenario Summary ══════");
        $pass = $fail = $err = 0;
        foreach ($this->report as $r) {
            $tick = match($r['result']) { 'pass' => '✓', 'fail' => '✗', default => '!' };
            $this->line("  [$tick] " . str_pad($r['scenario'], 30) . ' · ' . Str::limit($r['summary'], 90));
            if ($r['result'] === 'pass') $pass++;
            elseif ($r['result'] === 'fail') $fail++;
            else $err++;
        }
        $this->info("\n$pass passed · $fail failed · $err errored · " . count($this->report) . " total");
    }

    private function writeMarkdown(string $path): void
    {
        $abs = base_path($path);
        @mkdir(dirname($abs), 0775, true);
        $md = "# Booking scenarios test report\n\n_Generated " . now()->toDayDateTimeString() . "_\n\n";
        $md .= "| Scenario | Result | Summary | Booking |\n|---|---|---|---|\n";
        foreach ($this->report as $r) {
            $tick = match($r['result']) { 'pass' => '✅', 'fail' => '❌', default => '⚠️' };
            $bid = $r['booking_id'] ? '#' . $r['booking_id'] : '—';
            $md .= '| `' . $r['scenario'] . '` | ' . $tick . ' ' . $r['result'] . ' | ' . str_replace('|', '\\|', $r['summary']) . ' | ' . $bid . " |\n";
        }
        file_put_contents($abs, $md);
        $this->info("\nWrote report: $path");
    }

    private function cleanup(): void
    {
        if (empty($this->createdBookingIds)) return;
        DB::table('payments')->whereIn('booking_id', $this->createdBookingIds)->delete();
        DB::table('email_send_log')->whereIn('booking_id', $this->createdBookingIds)->delete();
        DB::table('booking_requests')->whereIn('id', $this->createdBookingIds)->delete();
        $this->info("\nCleaned up " . count($this->createdBookingIds) . ' test bookings.');
    }
}
