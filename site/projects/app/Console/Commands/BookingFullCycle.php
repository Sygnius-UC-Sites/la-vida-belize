<?php

namespace App\Console\Commands;

use App\Models\BookingRequest;
use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Run a full booking lifecycle on a fresh booking and produce a markdown
 * report of every email + state transition. Use it to spot-check that
 * everything is wired up correctly without clicking through the admin.
 *
 *   php artisan booking:full-cycle --property=1 --renter=basu.siddharth24@gmail.com --owner=info@sygniusdigital.com
 */
class BookingFullCycle extends Command
{
    protected $signature = 'booking:full-cycle
        {--property=1 : property id}
        {--renter=basu.siddharth24@gmail.com : renter email}
        {--owner=info@sygniusdigital.com : owner email}';

    protected $description = 'Walk a booking through every lifecycle stage, capturing each email + state change as a checklist.';

    public function handle(): int
    {
        $propertyId = (int) $this->option('property');
        $renter     = (string) $this->option('renter');
        $owner      = (string) $this->option('owner');

        $property = Property::find($propertyId);
        if (!$property) { $this->error("Property #$propertyId not found."); return self::FAILURE; }

        DB::table('basic_settings')->updateOrInsert(['name' => 'booking_receiving_mail'], ['value' => $owner, 'updated_at' => now()]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'admin_email_primary'],    ['value' => $owner, 'updated_at' => now()]);

        $this->h1("Booking lifecycle test — " . $property->name . " (#$property->id)");
        $this->kv('Renter', $renter);
        $this->kv('Owner',  $owner);
        $this->kv('Date',   now()->toDayDateTimeString());

        // ─── 1. Submit enquiry ─────────────────────────────────────────────
        $this->h2('1. Renter submits booking enquiry');
        $checkin  = now()->addDays(45)->toDateString();
        $checkout = now()->addDays(48)->toDateString();
        $idem     = (string) Str::uuid();

        $req = Request::create('/booking/request', 'POST', [
            'property_id'     => $propertyId,
            'check_in'        => $checkin,
            'check_out'       => $checkout,
            'adults'          => 2,
            'children'        => 1,
            'pets'            => 0,
            'addons'          => '{}',
            'name'            => 'Siddharth Basu',
            'email'           => $renter,
            'mobile'          => '+1-555-123-4567',
            'message'         => 'Looking forward to staying — first time in the area, any local recommendations welcome.',
            'idempotency_key' => $idem,
            '_form_ts'        => \App\Services\Spam\Checks\TimeTrapCheck::generateToken(time() - 5),
        ]);
        $req->headers->set('Accept', 'application/json');
        $resp = app()->handle($req);
        $payload = json_decode($resp->getContent(), true) ?: [];

        if (empty($payload['success'])) {
            $this->error('  Enquiry submission failed: ' . ($payload['error_message'] ?? 'unknown'));
            return self::FAILURE;
        }
        $b = BookingRequest::where('request_id', $idem)->first();
        $this->kv('Booking id',     '#' . $b->id);
        $this->kv('Booking status', $b->booking_status);
        $this->kv('Payment status', $b->payment_status);
        $this->kv('Total nights',   $b->total_night);
        $this->kv('Total amount',   '$' . number_format((float) $b->total_amount, 2));
        $this->emails($b->id, '  Emails fired:');

        // ─── 2. Admin approves ────────────────────────────────────────────
        $this->h2('2. Admin approves the enquiry');
        DB::table('booking_requests')->where('id', $b->id)->update([
            'booking_status' => 'booking-accepted',
            'updated_at'     => now(),
        ]);
        $b = BookingRequest::find($b->id);
        $this->kv('Booking status', $b->booking_status);
        $this->kv('Admin action',   '/admin/booking-enquiries → row #' . $b->id . ' → "Accept" button');

        // ─── 3. Awaiting payment ──────────────────────────────────────────
        $this->h2('3. Payment link issued — awaiting renter payment');
        // payment_status is an enum: paid|partially|pending|declined|failed|check-booking.
        // "pending" = approved by admin, awaiting payment from renter.
        DB::table('booking_requests')->where('id', $b->id)->update([
            'payment_status' => 'pending',
            'updated_at'     => now(),
        ]);
        $b = BookingRequest::find($b->id);
        $this->kv('Payment status', $b->payment_status);
        $this->fireLifecycleEmail($b, 'awaiting-payment');
        $this->emails($b->id, '  Emails fired:');

        // ─── 4. Payment confirmed ─────────────────────────────────────────
        $this->h2('4. Renter pays — booking confirmed');
        DB::table('booking_requests')->where('id', $b->id)->update([
            'booking_status' => 'booked',
            'payment_status' => 'paid',
            'updated_at'     => now(),
        ]);
        $b = BookingRequest::find($b->id);
        $this->kv('Booking status', $b->booking_status);
        $this->kv('Payment status', $b->payment_status);
        $this->fireLifecycleEmail($b, 'confirmed');
        $this->emails($b->id, '  Emails fired:');

        // ─── 5. Welcome package ───────────────────────────────────────────
        $this->h2('5. Welcome package — sent ahead of arrival');
        $this->fireLifecycleEmail($b, 'welcome');
        $this->emails($b->id, '  Emails fired:');

        // ─── 6. Check-in info ─────────────────────────────────────────────
        $this->h2('6. Check-in details — sent day before arrival');
        $this->fireLifecycleEmail($b, 'checkin');
        $this->emails($b->id, '  Emails fired:');

        // ─── 7. Check-out reminder ────────────────────────────────────────
        $this->h2('7. Check-out reminder — morning of departure');
        $this->fireLifecycleEmail($b, 'checkout');
        $this->emails($b->id, '  Emails fired:');

        // ─── 8. Review request ────────────────────────────────────────────
        $this->h2('8. Review request — 2 days after stay');
        $this->fireLifecycleEmail($b, 'review');
        $this->emails($b->id, '  Emails fired:');

        // ─── Summary ──────────────────────────────────────────────────────
        $this->h1('Summary');
        $logs = DB::table('email_send_log')->where('booking_id', $b->id)->orderBy('id')->get(['email_type','recipient','subject','success','error']);
        $this->info("Total emails captured: {$logs->count()}");
        $sentCount = $logs->where('success', 1)->count();
        $failCount = $logs->count() - $sentCount;
        $this->info("Successful sends: $sentCount");
        if ($failCount > 0) $this->warn("Failed sends:     $failCount  (see error column — usually because mailer creds aren't configured locally)");

        $this->h1('Per-stage checklist');
        $stages = ['enquiry', 'awaiting-payment', 'confirmed', 'welcome', 'checkin', 'checkout', 'review'];
        foreach ($stages as $s) {
            $row = $logs->firstWhere('email_type', $s);
            $tick = $row ? ($row->success ? '✓' : '⚠') : '✗';
            $line = $row ? Str::limit((string) $row->subject, 70) : 'NOT FIRED';
            $this->line("  [$tick] " . str_pad($s, 18) . " — $line");
        }

        $this->h1('To inspect rendered HTML');
        $this->line("  /admin/booking-enquiries → click row #$b->id → modal exposes 👁️ Preview buttons");
        $this->line("  Each preview pulls the saved email from the email_send_log.rendered_html column.");

        return self::SUCCESS;
    }

    private function fireLifecycleEmail(BookingRequest $b, string $type): void
    {
        try {
            $resolver = new \App\Services\Stay\StayCommunicationsResolver(\App\Models\Property::find($b->property_id), $b);
            $brand    = \ModelHelper::getDataFromSetting('mailer_admin_name')
                     ?: \ModelHelper::getDataFromSetting('mail_from_name')
                     ?: 'Notification';
            $footer   = \ModelHelper::getDataFromSetting('mail_footer') ?: '';

            $subjectKey = match ($type) {
                'awaiting-payment' => 'awaiting_payment_default_subject',
                'confirmed'        => 'confirmed_default_subject',
                'welcome'          => 'welcome_default_subject',
                'checkin'          => 'checkin_default_subject',
                'checkout'         => 'checkout_default_subject',
                'review'           => 'review_default_subject',
                default            => "{$type}_default_subject",
            };
            $bodyKey = str_replace('_subject', '_body_html', $subjectKey);

            $subject = $resolver->expandMergeFields((string) (\ModelHelper::getDataFromSetting($subjectKey) ?: ucfirst($type)));
            $body    = $resolver->expandMergeFields((string) (\ModelHelper::getDataFromSetting($bodyKey) ?: '<p>This is a {{type}} email.</p>'));

            $html = view('mail.email-shell', [
                'body_html_rendered' => $body,
                'brand'              => $brand,
                'mail_footer'        => $footer,
            ])->render();

            \MailHelper::emailSenderByController($html, $b->email, $subject);
            \App\Services\Email\SendLog::record((int) $b->id, $type, (string) $b->email, $subject, $html);

            // Owner alert
            $adminSubjectKey = "admin_alert_{$type}_subject";
            $adminBodyKey    = "admin_alert_{$type}_body_html";
            $adminSubject    = $resolver->expandMergeFields((string) (\ModelHelper::getDataFromSetting($adminSubjectKey) ?: 'Admin alert: ' . $type));
            $adminBody       = $resolver->expandMergeFields((string) (\ModelHelper::getDataFromSetting($adminBodyKey) ?: '<p>Booking #{{booking_id}} — ' . $type . ' email sent.</p>'));
            $adminHtml = view('mail.email-shell', ['body_html_rendered' => $adminBody, 'brand' => $brand, 'mail_footer' => $footer])->render();
            $to = \ModelHelper::getDataFromSetting('booking_receiving_mail') ?: \ModelHelper::getDataFromSetting('admin_email_primary');
            if ($to) {
                \MailHelper::emailSenderByController($adminHtml, $to, $adminSubject);
                // Owner alerts get logged with type prefixed `admin-` so they're separable.
                \App\Services\Email\SendLog::record((int) $b->id, "admin-$type", (string) $to, $adminSubject, $adminHtml);
            }
        } catch (\Throwable $e) {
            $this->warn("    Failed to fire $type email: " . $e->getMessage());
        }
    }

    private function emails(int $bookingId, string $label): void
    {
        $logs = DB::table('email_send_log')->where('booking_id', $bookingId)->orderByDesc('id')->limit(4)->get(['email_type','recipient','subject','success']);
        $this->line($label);
        foreach ($logs as $l) {
            $tick = $l->success ? '✓' : '⚠';
            $this->line("    [$tick] " . str_pad($l->email_type, 18) . " → $l->recipient");
            $this->line("       " . Str::limit((string) $l->subject, 90));
        }
    }

    private function h1(string $text): void { $this->line(''); $this->line(str_repeat('═', strlen($text))); $this->info($text); $this->line(str_repeat('═', strlen($text))); }
    private function h2(string $text): void { $this->line(''); $this->info("── $text"); }
    private function kv(string $k, string $v): void { $this->line(str_pad("  $k:", 22) . $v); }
}
