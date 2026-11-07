<?php

namespace Tests\Feature\RentalAgreement;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RentalAgreementDownloadTest extends TestCase
{
    private int $propertyId;
    private int $bookingId;
    private int $userId;
    private string $pdfPath;
    private string $signedAt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name' => 'RA-Download-Test ' . uniqid(),
            'seo_url' => 'ra-dl-test-' . uniqid(),
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 200,
            'no_of_guest' => 4,
            'type' => 'condo',
            'status' => 'true',
        ]);

        $this->signedAt = '2026-05-02 12:00:00';
        $this->pdfPath = 'agreements/test_booking_dl_' . uniqid() . '.pdf';

        $this->bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => $this->propertyId,
            'checkin' => '2026-06-01',
            'checkout' => '2026-06-04',
            'name' => 'DL Guest',
            'email' => 'dl-guest@example.com',
            'rental_aggrement_status' => 'true',
            'rental_agreement_pdf_path' => $this->pdfPath,
            'rental_agreement_signed_at' => $this->signedAt,
        ]);

        // Place a real PDF (with %PDF- magic bytes) on the local disk
        Storage::disk('local')->put($this->pdfPath, "%PDF-1.4\nfake test pdf body\n%%EOF\n");

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_ra_dl_user_',
            'email'      => '_ra_dl_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->delete($this->pdfPath);
        DB::table('booking_requests')->where('id', $this->bookingId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    private function expectedHmac(): string
    {
        return hash_hmac('sha256', $this->bookingId . '|' . $this->signedAt, config('app.key'));
    }

    public function test_admin_can_download_signed_pdf(): void
    {
        $user = User::findOrFail($this->userId);
        $resp = $this->actingAs($user)->get('/admin/booking-enquiries/' . $this->bookingId . '/agreement.pdf');

        $resp->assertOk();
        $this->assertStringContainsString('pdf', strtolower($resp->headers->get('Content-Type')));
        $this->assertSame('%PDF-', substr($resp->streamedContent(), 0, 5));
    }

    public function test_admin_route_404s_when_no_pdf_path(): void
    {
        DB::table('booking_requests')->where('id', $this->bookingId)->update(['rental_agreement_pdf_path' => null]);
        $user = User::findOrFail($this->userId);

        $resp = $this->actingAs($user)->get('/admin/booking-enquiries/' . $this->bookingId . '/agreement.pdf');
        $resp->assertNotFound();
    }

    public function test_guest_download_succeeds_with_valid_hmac(): void
    {
        $resp = $this->get('/booking/' . $this->bookingId . '/agreement?sig=' . $this->expectedHmac());
        $resp->assertOk();
        $this->assertSame('%PDF-', substr($resp->streamedContent(), 0, 5));
    }

    public function test_guest_download_403s_with_invalid_hmac(): void
    {
        $resp = $this->get('/booking/' . $this->bookingId . '/agreement?sig=garbage');
        $resp->assertForbidden();
    }

    public function test_guest_download_403s_with_no_hmac(): void
    {
        $resp = $this->get('/booking/' . $this->bookingId . '/agreement');
        $resp->assertForbidden();
    }
}
