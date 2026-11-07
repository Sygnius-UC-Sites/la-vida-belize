<?php

namespace Tests\Feature\RentalAgreement;

use App\Models\BookingRequest;
use App\Services\Booking\RentalAgreementContext;
use App\Services\Booking\RentalAgreementGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RentalAgreementGeneratorTest extends TestCase
{
    private int $propertyId;
    private int $bookingId;
    private array $writtenPaths = [];
    private ?string $savedGlobalTemplate = null;
    private ?string $savedGlobalRules = null;
    // 1x1 transparent PNG
    private const SIG_PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4//8/AwAI/AL+XsmExgAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        $this->savedGlobalTemplate = DB::table('basic_settings')->where('name', 'rental_agreement_template_html')->value('value');
        $this->savedGlobalRules    = DB::table('basic_settings')->where('name', 'rental_agreement_house_rules_html')->value('value');

        $this->propertyId = DB::table('properties')->insertGetId([
            'name' => 'RA-Test Property ' . uniqid(),
            'seo_url' => 'ra-test-' . uniqid(),
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 200,
            'no_of_guest' => 4,
            'type' => 'condo',
            'status' => 'true',
            'rental_agreement_required' => 1,
        ]);

        $this->bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => $this->propertyId,
            'checkin' => '2026-06-01',
            'checkout' => '2026-06-04',
            'name' => 'Test Guest',
            'email' => 'guest@example.com',
            'mobile' => '+506-1234-5678',
            'adults' => 2,
            'child' => 0,
            'total_pets' => 0,
            'total_night' => 3,
            'total_amount' => 1234.56,
            'rental_aggrement_status' => 'false',
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->writtenPaths as $p) {
            Storage::disk('local')->delete($p);
        }
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

    private function generate(?BookingRequest $b = null, array $overrides = []): string
    {
        $signing = array_merge([
            'signature_data_url'   => self::SIG_PNG,
            'signed_at_iso'        => '2026-05-02T12:00:00Z',
            'signed_ip'            => '10.20.30.40',
            'signed_user_agent'    => 'UA-FIXTURE',
            'signed_consent_text'  => 'I AGREE-XYZ',
        ], $overrides);
        $booking = $b ?: BookingRequest::find($this->bookingId);
        $path = (new RentalAgreementGenerator())->generate($booking, $signing);
        $this->writtenPaths[] = $path;
        return $path;
    }

    private function pdfBytes(string $path): string
    {
        return Storage::disk('local')->get($path);
    }

    private function renderHtml(?BookingRequest $b = null, array $overrides = []): string
    {
        // Render the same Blade the generator uses, so we can assert on text content.
        $booking = $b ?: BookingRequest::find($this->bookingId);
        $property = \App\Models\Property::find($booking->property_id);
        $templateHtml = $property->rental_agreement_template_html
            ?: \ModelHelper::getDataFromSetting('rental_agreement_template_html');
        $rulesHtml = $property->rental_agreement_house_rules_html
            ?: \ModelHelper::getDataFromSetting('rental_agreement_house_rules_html');
        $signing = array_merge([
            'signature_data_url'   => self::SIG_PNG,
            'signed_at_iso'        => '2026-05-02T12:00:00Z',
            'signed_ip'            => '10.20.30.40',
            'signed_user_agent'    => 'UA-FIXTURE',
            'signed_consent_text'  => 'I AGREE-XYZ',
            'template_html'        => $templateHtml,
            'house_rules_html'     => $rulesHtml,
        ], $overrides);
        $ctx = RentalAgreementContext::fromBooking($booking, $property, $signing);
        return view('pdf.rental-agreement', ['ctx' => $ctx])->render();
    }

    public function test_generates_pdf_using_property_override_when_present(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'rental_agreement_template_html' => 'OWNER OVERRIDE: {{guest_name}}',
        ]);

        $html = $this->renderHtml();
        $this->assertStringContainsString('OWNER OVERRIDE', $html);
        $this->assertStringContainsString('Test Guest', $html);

        $path = $this->generate();
        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_falls_back_to_global_template_when_property_override_null(): void
    {
        $html = $this->renderHtml();
        $this->assertStringContainsString('1. Parties', $html);
    }

    public function test_throws_when_no_template_configured(): void
    {
        DB::table('basic_settings')->where('name', 'rental_agreement_template_html')->delete();

        $this->expectException(\RuntimeException::class);
        $this->generate();
    }

    public function test_pdf_contains_merge_field_substitutions(): void
    {
        $html = $this->renderHtml();
        $this->assertStringContainsString('Test Guest', $html);
        $this->assertStringContainsString('1,234.56', $html);
        $this->assertStringContainsString('2026-06-01', $html);
    }

    public function test_pdf_contains_audit_block(): void
    {
        $html = $this->renderHtml();
        $this->assertStringContainsString('10.20.30.40', $html);
        $this->assertStringContainsString('UA-FIXTURE', $html);
        $this->assertStringContainsString('I AGREE-XYZ', $html);
    }

    public function test_pdf_binary_starts_with_PDF_magic_bytes(): void
    {
        $path = $this->generate();
        $bytes = $this->pdfBytes($path);
        $this->assertSame('%PDF-', substr($bytes, 0, 5));
        $this->assertGreaterThan(2000, strlen($bytes));
    }

    public function test_house_rules_html_is_rendered_raw_not_escaped(): void
    {
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'rental_agreement_house_rules_html'],
            ['value' => '<strong>NO PARTIES</strong>', 'updated_at' => now()]
        );

        $html = $this->renderHtml();
        $this->assertStringContainsString('<strong>NO PARTIES</strong>', $html);
        $this->assertStringNotContainsString('&lt;strong&gt;NO PARTIES', $html);
    }

    public function test_guest_data_is_html_escaped_in_merge_fields(): void
    {
        DB::table('booking_requests')->where('id', $this->bookingId)->update([
            'name' => '<script>alert(1)</script>',
        ]);

        $html = $this->renderHtml();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }
}
