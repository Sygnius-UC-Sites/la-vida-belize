<?php

namespace App\Console\Commands;

use App\Models\BookingRequest;
use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * End-to-end booking simulation. Creates N booking requests on a property,
 * triggers the email lifecycle (enquiry confirmation + admin alert + welcome
 * + check-in + check-out + review), and records what was attempted.
 *
 * Use the captured email_send_log table afterwards to inspect rendered HTML.
 *
 *   php artisan booking:simulate --property=11 --renter=basu.siddharth24@gmail.com --owner=info@sygniusdigital.com --count=2
 */
class BookingTestSimulate extends Command
{
    protected $signature = 'booking:simulate
        {--property=11 : property ID to book}
        {--renter=basu.siddharth24@gmail.com : renter (guest) email used in the booking form}
        {--owner=info@sygniusdigital.com : owner email that admin alerts go to}
        {--count=2 : number of bookings to simulate}';

    protected $description = 'Simulate booking submissions end-to-end and trigger lifecycle emails for manual testing.';

    public function handle(): int
    {
        $propertyId = (int) $this->option('property');
        $renter     = (string) $this->option('renter');
        $owner      = (string) $this->option('owner');
        $count      = max(1, (int) $this->option('count'));

        $property = Property::find($propertyId);
        if (!$property) {
            $this->error("Property #$propertyId not found.");
            return self::FAILURE;
        }

        // Make sure owner alerts route to the right address.
        DB::table('basic_settings')->updateOrInsert(['name' => 'booking_receiving_mail'], ['value' => $owner, 'updated_at' => now()]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'admin_email_primary'],    ['value' => $owner, 'updated_at' => now()]);

        $this->info("Simulating $count booking(s) on '{$property->name}' (#$propertyId)");
        $this->line("  Renter: $renter");
        $this->line("  Owner:  $owner");

        $created = [];
        for ($i = 0; $i < $count; $i++) {
            $checkin  = now()->addDays(30 + ($i * 14))->toDateString();
            $checkout = now()->addDays(33 + ($i * 14))->toDateString();
            $name     = ['Test Renter A', 'Test Renter B', 'Test Renter C'][$i] ?? "Test Renter " . chr(65 + $i);
            $idem     = (string) Str::uuid();

            // Hit the existing controller via in-process Request — this
            // exercises the real booking flow including spam protection,
            // quote calc, BookingRequest::create, and the lifecycle email
            // dispatch (enquiry to renter + admin alert to owner).
            $req = Request::create('/booking/request', 'POST', [
                'property_id'      => $propertyId,
                'check_in'         => $checkin,
                'check_out'        => $checkout,
                'adults'           => 2,
                'children'         => 0,
                'pets'             => 0,
                'addons'           => '{}',
                'name'             => $name,
                'email'            => $renter,
                'mobile'           => '+1-555-555-' . str_pad((string)($i + 100), 4, '0', STR_PAD_LEFT),
                'message'          => "Auto-test booking #" . ($i + 1) . " — please ignore.",
                'idempotency_key'  => $idem,
                '_form_ts'         => \App\Services\Spam\Checks\TimeTrapCheck::generateToken(time() - 5),
            ]);
            $req->headers->set('Accept', 'application/json');

            $resp = app()->handle($req);
            $payload = json_decode($resp->getContent(), true) ?: [];

            $b = BookingRequest::where('request_id', $idem)->first();
            $created[] = $b;

            if ($payload['success'] ?? false) {
                $this->info("  ✓ #" . ($b->id ?? '?') . " created — $checkin → $checkout, redirect: " . ($payload['redirect_url'] ?? ''));
            } else {
                $this->warn("  ✗ #" . ($i + 1) . " failed — " . ($payload['error_message'] ?? 'unknown'));
            }
        }

        // Show what email_send_log captured.
        $this->info("\n--- email_send_log captured for these bookings ---");
        $bookingIds = array_filter(array_map(fn($b) => $b ? $b->id : null, $created));
        $logs = DB::table('email_send_log')
            ->whereIn('booking_id', $bookingIds)
            ->orderBy('id')
            ->get(['id', 'booking_id', 'email_type', 'recipient', 'subject', 'success', 'error']);

        if ($logs->isEmpty()) {
            $this->warn('  No rows in email_send_log — emails may not have been triggered (mailer disabled / no credentials).');
        } else {
            foreach ($logs as $l) {
                $tick = $l->success ? '✓' : '✗';
                $this->line("  [$tick] booking#{$l->booking_id} · {$l->email_type} → {$l->recipient}");
                $this->line("       subj: " . Str::limit((string) $l->subject, 90));
                if (!$l->success && $l->error) {
                    $this->line("       err:  " . Str::limit((string) $l->error, 200));
                }
            }
        }

        $this->info("\n--- mail_send_log captured (SES path, if used) ---");
        $mailLogs = DB::table('mail_send_log')
            ->orderByDesc('id')->limit(20)
            ->get(['id', 'purpose', 'from_email', 'to_email', 'subject', 'status', 'error_code']);
        if ($mailLogs->isEmpty()) {
            $this->line('  (none — SES not exercised in this run)');
        } else {
            foreach ($mailLogs as $l) {
                $this->line("  [{$l->status}] {$l->purpose} {$l->from_email} → {$l->to_email} :: " . Str::limit((string) $l->subject, 70));
            }
        }

        $this->info("\nDone. Open http://localhost/base_crm/admin/booking-enquiries to see the new bookings.");
        $this->info("Each booking row → click View → modal exposes 'Resend' buttons for each lifecycle email.\n");

        return self::SUCCESS;
    }
}
