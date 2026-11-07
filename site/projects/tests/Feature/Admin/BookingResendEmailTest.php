<?php

namespace Tests\Feature\Admin;

use App\Models\BookingRequest;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase 13G — manual email re-trigger endpoint at
 * POST /admin/booking-enquiries/{id}/resend/{type}
 */
class BookingResendEmailTest extends TestCase
{
    private int $userId;
    private int $bookingId;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->userId = DB::table('users')->insertGetId([
            'name' => '_p13g_user_', 'email' => '_p13g_' . uniqid() . '@test.local',
            'password' => bcrypt('x'), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->bookingId = DB::table('booking_requests')->insertGetId([
            'property_id' => 1,  // existing test property
            'name'        => 'P13G Resend',
            'email'       => 'p13g-resend@example.com',
            'checkin'     => '2026-09-15',
            'checkout'    => '2026-09-18',
            'total_night' => 3,
            'total_amount'=> 600,
            'booking_status' => 'booked',
            'rental_aggrement_status' => 'false',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('booking_requests')->where('id', $this->bookingId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_phase13g_welcome_resend_endpoint_returns_ok_and_flips_flag(): void
    {
        $resp = $this->actingAs(User::find($this->userId))
            ->withoutMiddleware()
            ->post('/admin/booking-enquiries/' . $this->bookingId . '/resend/welcome');

        $resp->assertStatus(200);
        $resp->assertJson(['ok' => true]);

        $row = DB::table('booking_requests')->where('id', $this->bookingId)->first();
        $this->assertSame('true', $row->welcome_email);
        $this->assertNotNull($row->welcome_email_sent_at);
        $this->assertNotNull($row->welcome_email_attempted_at);
        $this->assertNull($row->welcome_email_error);
    }

    public function test_phase13g_unknown_email_type_is_rejected(): void
    {
        $resp = $this->actingAs(User::find($this->userId))
            ->withoutMiddleware()
            ->post('/admin/booking-enquiries/' . $this->bookingId . '/resend/totally-bogus');

        // Route's where() constraint causes a 404 for unknown types.
        $resp->assertStatus(404);
    }

    public function test_phase13m_email_preview_shows_not_sent_when_no_snapshot(): void
    {
        $resp = $this->actingAs(User::find($this->userId))
            ->get('/admin/booking-enquiries/' . $this->bookingId . '/email-preview/confirmed');
        $resp->assertStatus(200);
        $h = $resp->getContent();
        // No snapshot exists for this fresh booking → admin sees the "not sent yet" page.
        $this->assertStringContainsString('No', $h);
        $this->assertStringContainsString('email has been sent', $h);
        $this->assertStringContainsString('#' . $this->bookingId, $h);
    }

    public function test_phase13m_email_preview_shows_stored_snapshot_when_present(): void
    {
        // Manually insert a snapshot row to simulate a prior send.
        DB::table('email_send_log')->insert([
            'booking_id'    => $this->bookingId,
            'email_type'    => 'confirmed',
            'recipient'     => 'p13m-test@example.com',
            'subject'       => 'P13M Stored Subject',
            'rendered_html' => '<p>P13M-MARKER-this-is-the-stored-body</p>',
            'sent_at'       => now(),
            'success'       => 1,
            'error'         => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $resp = $this->actingAs(User::find($this->userId))
            ->get('/admin/booking-enquiries/' . $this->bookingId . '/email-preview/confirmed');
        $resp->assertStatus(200);
        $h = $resp->getContent();
        // Banner + the stored body
        $this->assertStringContainsString('P13M-MARKER-this-is-the-stored-body', $h);
        $this->assertStringContainsString('P13M Stored Subject', $h);
        $this->assertStringContainsString('p13m-test@example.com', $h);
        $this->assertStringContainsString('Sent successfully', $h);

        DB::table('email_send_log')->where('booking_id', $this->bookingId)->delete();
    }

    public function test_phase13l_email_preview_rejects_unknown_type(): void
    {
        $resp = $this->actingAs(User::find($this->userId))
            ->get('/admin/booking-enquiries/' . $this->bookingId . '/email-preview/totally-bogus');
        $resp->assertStatus(404);
    }

    public function test_phase13g_index_page_renders_lifecycle_legend(): void
    {
        $resp = $this->actingAs(User::find($this->userId))->get('/admin/booking-enquiries');
        $resp->assertStatus(200);
        $resp->assertSee('Lifecycle column — what the dots mean', false);
        $resp->assertSee('be-resend-btn', false);
        $resp->assertSee('Add manual booking', false);
        $resp->assertSee('body.be-page .modal { z-index: 3000 !important; pointer-events: auto !important; }', false);
        $resp->assertSee('body.be-page .modal-dialog,', false);
        $resp->assertSee('body.be-page .modal-content { pointer-events: auto !important; }', false);
        $resp->assertSee('width: min(1100px, calc(100vw - 32px)) !important;', false);
        $resp->assertSee('max-height: calc(100vh - 96px);', false);
        $resp->assertSee('background: #fff !important;', false);
        $resp->assertSee('body.be-page .modal-header { display: flex !important;', false);
        $resp->assertSee('body.be-page .modal-backdrop { z-index: 2990 !important; pointer-events: none !important; }', false);
        $resp->assertSee('body.be-page .swal2-container,', false);
        $resp->assertSee('z-index: 4200 !important;', false);
        $resp->assertSee('be-email-row', false);
        $resp->assertSee('grid-template-columns: 36px minmax(0, 1fr) auto minmax(126px, auto);', false);
    }
}
