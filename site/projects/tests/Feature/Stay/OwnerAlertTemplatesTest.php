<?php

namespace Tests\Feature\Stay;

use App\Models\BookingRequest;
use App\Models\Property;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 13E — Editable owner / admin alert templates with optional BCC toggles.
 */
class OwnerAlertTemplatesTest extends TestCase
{
    private int $propertyId;
    private int $bookingId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name'    => 'P13E Test ' . uniqid(),
            'seo_url' => 'p13e-test-' . uniqid(),
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 200,
            'no_of_guest'   => 4,
            'type'    => 'condo',
            'status'  => 'true',
        ]);

        $this->bookingId = DB::table('booking_requests')->insertGetId([
            'property_id'    => $this->propertyId,
            'checkin'        => '2026-09-01',
            'checkout'       => '2026-09-05',
            'name'           => 'Phase 13E Guest',
            'email'          => 'p13e@example.com',
            'total_night'    => 4,
            'total_amount'   => 1200,
            'rental_aggrement_status' => 'false',
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('booking_requests')->where('id', $this->bookingId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        // Restore BCC toggles to off so other tests aren't affected.
        DB::table('basic_settings')->whereIn('name', [
            'admin_bcc_welcome_enabled', 'admin_bcc_checkin_enabled', 'admin_bcc_checkout_enabled',
        ])->update(['value' => '0']);
        parent::tearDown();
    }

    public function test_phase13e_resolver_expands_admin_url_token(): void
    {
        $resolver = new StayCommunicationsResolver(
            Property::find($this->propertyId),
            BookingRequest::find($this->bookingId),
        );

        $expanded = $resolver->expandMergeFields('Open booking → {{admin_url}}');
        $this->assertStringContainsString('admin/booking-enquiries/' . $this->bookingId . '/edit', $expanded);
    }

    public function test_phase13e_email_templates_page_renders_6_tabs_with_owner_alerts(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => '_p13e_user_', 'email' => '_p13e_' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $resp = $this->actingAs(\App\Models\User::find($userId))->get('/admin/settings/stay-communications');
        $resp->assertStatus(200);
        // 6th tab visible
        $resp->assertSee('ge-owner', false);
        $resp->assertSee('Owner alerts', false);
        // Spot-check key fields from the Owner alerts tab
        $resp->assertSee('name="admin_alert_enquiry_subject"', false);
        $resp->assertSee('name="admin_alert_cancelled_body_html"', false);
        $resp->assertSee('name="admin_alert_receipt_subject"', false);
        $resp->assertSee('name="admin_alert_receipt_body_html"', false);
        $resp->assertSee('name="admin_alert_rental_agreement_subject"', false);
        $resp->assertSee('name="admin_bcc_welcome_enabled"', false);
        $resp->assertSee('name="admin_bcc_checkin_enabled"', false);
        $resp->assertSee('name="admin_bcc_checkout_enabled"', false);

        DB::table('users')->where('id', $userId)->delete();
    }

    public function test_phase13e_bcc_toggle_keys_are_in_managed_fields(): void
    {
        // The 3 BCC toggle keys must be in the controller's FIELDS const so they
        // round-trip through saves (otherwise the form would silently drop them).
        $reflection = new \ReflectionClass(\App\Http\Controllers\Admin\StayCommunicationsSettingsController::class);
        $fields = $reflection->getConstant('FIELDS') ?: $reflection->getProperty('FIELDS')?->getDefaultValue();
        // Fall back to property reflection if FIELDS is private const
        if (!$fields) {
            $prop = $reflection->getReflectionConstant('FIELDS');
            if ($prop) $fields = $prop->getValue();
        }

        $this->assertContains('admin_bcc_welcome_enabled', $fields);
        $this->assertContains('admin_bcc_checkin_enabled', $fields);
        $this->assertContains('admin_bcc_checkout_enabled', $fields);
        $this->assertContains('admin_alert_rental_agreement_subject', $fields);
        $this->assertContains('admin_alert_installment3_body_html', $fields);
    }

    public function test_phase13e_admin_alert_subjects_seeded_with_defaults(): void
    {
        // Migration-seeded defaults must be present (non-empty) for all 7 known + 3 BCC subject keys.
        $alertKeys = [
            'admin_alert_enquiry_subject',
            'admin_alert_awaiting_payment_subject',
            'admin_alert_confirmed_subject',
            'admin_alert_cancelled_subject',
            'admin_alert_installment2_subject',
            'admin_alert_installment3_subject',
            'admin_alert_rental_agreement_subject',
            'admin_alert_welcome_subject',
            'admin_alert_checkin_subject',
            'admin_alert_checkout_subject',
        ];
        foreach ($alertKeys as $k) {
            $value = DB::table('basic_settings')->where('name', $k)->value('value');
            $this->assertNotEmpty($value, "Expected seeded default for {$k}");
        }
    }
}
