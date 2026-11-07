<?php

namespace Tests\Feature\RentalAgreement;

use App\Models\BookingRequest;
use App\Services\Booking\RentalAgreementContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RentalAgreementOverrideTest extends TestCase
{
    private int $propertyId;
    private int $bookingId;
    private array $writtenPaths = [];
    private ?string $savedGlobalTemplate;
    private ?string $savedGlobalRules;

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedGlobalTemplate = DB::table('basic_settings')->where('name', 'rental_agreement_template_html')->value('value');
        $this->savedGlobalRules    = DB::table('basic_settings')->where('name', 'rental_agreement_house_rules_html')->value('value');

        // Set distinctive global values so we can tell them from the property override
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'rental_agreement_template_html'],
            ['value' => 'GLOBAL TEMPLATE for {{guest_name}}', 'updated_at' => now()]
        );
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'rental_agreement_house_rules_html'],
            ['value' => 'GLOBAL RULES', 'updated_at' => now()]
        );

        $this->propertyId = DB::table('properties')->insertGetId([
            'name' => 'RA-Override-Test ' . uniqid(),
            'seo_url' => 'ra-override-' . uniqid(),
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 200,
            'no_of_guest' => 4,
            'type' => 'condo',
            'status' => 'true',
        ]);

        $this->bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => $this->propertyId,
            'checkin' => '2026-06-01',
            'checkout' => '2026-06-04',
            'name' => 'Override Guest',
            'email' => 'override@example.com',
            'rental_aggrement_status' => 'false',
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('booking_requests')->where('id', $this->bookingId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();

        if ($this->savedGlobalTemplate !== null) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => 'rental_agreement_template_html'],
                ['value' => $this->savedGlobalTemplate, 'updated_at' => now()]
            );
        }
        if ($this->savedGlobalRules !== null) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => 'rental_agreement_house_rules_html'],
                ['value' => $this->savedGlobalRules, 'updated_at' => now()]
            );
        }
        parent::tearDown();
    }

    private function renderHtml(): string
    {
        $booking = BookingRequest::find($this->bookingId);
        $property = \App\Models\Property::find($this->propertyId);
        $templateHtml = $property->rental_agreement_template_html
            ?: \ModelHelper::getDataFromSetting('rental_agreement_template_html');
        $rulesHtml = $property->rental_agreement_house_rules_html
            ?: \ModelHelper::getDataFromSetting('rental_agreement_house_rules_html');
        $signing = [
            'signature_data_url'   => 'data:image/png;base64,iVBORw0KGgo=',
            'signed_at_iso'        => '2026-05-02T00:00:00Z',
            'signed_ip'            => '0.0.0.0',
            'signed_user_agent'    => 'test',
            'signed_consent_text'  => 'test',
            'template_html'        => $templateHtml,
            'house_rules_html'     => $rulesHtml,
        ];
        $ctx = RentalAgreementContext::fromBooking($booking, $property, $signing);
        return view('pdf.rental-agreement', ['ctx' => $ctx])->render();
    }

    public function test_property_override_beats_global_in_template_resolution(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'rental_agreement_template_html' => 'PROP OVERRIDE TPL: {{guest_name}}',
        ]);

        $html = $this->renderHtml();
        $this->assertStringContainsString('PROP OVERRIDE TPL', $html);
        $this->assertStringContainsString('Override Guest', $html); // merge token still applied
        $this->assertStringNotContainsString('GLOBAL TEMPLATE', $html);
    }

    public function test_property_override_beats_global_in_house_rules_resolution(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'rental_agreement_house_rules_html' => 'PROP OVERRIDE RULES',
        ]);

        $html = $this->renderHtml();
        $this->assertStringContainsString('PROP OVERRIDE RULES', $html);
        $this->assertStringNotContainsString('GLOBAL RULES', $html);
    }

    public function test_null_property_overrides_inherit_global(): void
    {
        // Both override columns are NULL by default (we never set them in setUp)
        $html = $this->renderHtml();
        $this->assertStringContainsString('GLOBAL TEMPLATE', $html);
        $this->assertStringContainsString('GLOBAL RULES', $html);
    }
}
