<?php

namespace Tests\Feature\Stay;

use App\Models\BookingRequest;
use App\Models\Property;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 13D — Editable email templates with merge tokens.
 * Verifies the resolver expands the new tokens and that callers can supply
 * extra per-send tokens (magic_link, installment_*) that win on collision.
 */
class EditableEmailTemplatesTest extends TestCase
{
    private int $propertyId;
    private int $bookingId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name'    => 'P13D Test ' . uniqid(),
            'seo_url' => 'p13d-test-' . uniqid(),
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 200,
            'no_of_guest'   => 4,
            'type'    => 'condo',
            'status'  => 'true',
            'address' => '123 Test Beach Rd',
        ]);

        $this->bookingId = DB::table('booking_requests')->insertGetId([
            'property_id'    => $this->propertyId,
            'checkin'        => '2026-08-01',
            'checkout'       => '2026-08-05',
            'name'           => 'Phase 13D Guest',
            'email'          => 'p13d@example.com',
            'total_night'    => 4,
            'total_amount'   => 850,
            'cancel_at'      => '2026-07-15 10:00:00',
            'rental_aggrement_status' => 'false',
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('booking_requests')->where('id', $this->bookingId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        parent::tearDown();
    }

    private function resolver(): StayCommunicationsResolver
    {
        return new StayCommunicationsResolver(
            Property::find($this->propertyId),
            BookingRequest::find($this->bookingId),
        );
    }

    public function test_phase13d_resolver_expands_booking_id_total_amount_cancel_date(): void
    {
        $expanded = $this->resolver()->expandMergeFields(
            'Booking #{{booking_id}} — total {{total_amount}} cancelled on {{cancel_date}} for {{guest_name}}'
        );

        $this->assertStringContainsString('#' . $this->bookingId, $expanded);
        $this->assertStringContainsString('850.00', $expanded);
        $this->assertStringContainsString('July 15, 2026', $expanded);
        $this->assertStringContainsString('Phase 13D Guest', $expanded);
    }

    public function test_phase13d_resolver_expands_brand_site_email_site_phone_from_settings(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'mailer_admin_name'], ['value' => 'P13D Brand', 'updated_at' => now()]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'email'], ['value' => 'support@p13d.test', 'updated_at' => now()]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'mobile'], ['value' => '+1 555 13D', 'updated_at' => now()]);

        $expanded = $this->resolver()->expandMergeFields(
            'Reach {{brand}} at {{site_email}} or {{site_phone}}'
        );

        $this->assertStringContainsString('P13D Brand', $expanded);
        $this->assertStringContainsString('support@p13d.test', $expanded);
        $this->assertStringContainsString('+1 555 13D', $expanded);

        // Cleanup
        DB::table('basic_settings')->whereIn('name', ['mailer_admin_name','email','mobile'])->update(['value' => null]);
    }

    public function test_phase13d_extra_tokens_supplied_by_caller_win_on_collision(): void
    {
        // Caller supplies magic_link + installment context. These don't exist in the
        // resolver's standard token map and must come through.
        $expanded = $this->resolver()->expandMergeFields(
            'Pay installment {{installment_number}} of {{installment_amount}} due {{installment_due_date}} → {{magic_link}}',
            [
                '{{magic_link}}'           => 'https://example.test/pay/abc',
                '{{installment_number}}'   => '2',
                '{{installment_amount}}'   => '$425.00',
                '{{installment_due_date}}' => 'August 1, 2026',
            ]
        );

        $this->assertStringContainsString('https://example.test/pay/abc', $expanded);
        $this->assertStringContainsString('installment 2 of $425.00', $expanded);
        $this->assertStringContainsString('August 1, 2026', $expanded);
    }

    public function test_phase13d_extra_tokens_override_standard_when_keys_overlap(): void
    {
        // Overriding {{guest_name}} via extras must win.
        $expanded = $this->resolver()->expandMergeFields(
            'Hello {{guest_name}}',
            ['{{guest_name}}' => 'Override Caller']
        );

        $this->assertStringContainsString('Hello Override Caller', $expanded);
        $this->assertStringNotContainsString('Phase 13D Guest', $expanded);
    }

    public function test_phase13d_guest_emails_page_renders_all_5_tabs(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => '_p13d_user_', 'email' => '_p13d_' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $resp = $this->actingAs(\App\Models\User::find($userId))->get('/admin/settings/stay-communications');
        $resp->assertStatus(200);
        // All 5 tab IDs render
        foreach (['ge-timing', 'ge-lifecycle', 'ge-prestay', 'ge-installments', 'ge-poststay'] as $tab) {
            $resp->assertSee($tab, false);
        }
        // Critical inputs from each tab
        $resp->assertSee('name="enquiry_default_subject"', false);
        $resp->assertSee('name="awaiting_payment_default_body_html"', false);
        $resp->assertSee('name="confirmed_default_subject"', false);
        $resp->assertSee('name="receipt_default_subject"', false);
        $resp->assertSee('name="receipt_default_body_html"', false);
        $resp->assertSee('name="installment2_default_body_html"', false);
        $resp->assertSee('name="installment3_default_subject"', false);
        $resp->assertSee('name="review_default_body_html"', false);
        $resp->assertSee('name="cancellation_default_subject"', false);

        DB::table('users')->where('id', $userId)->delete();
    }
}
