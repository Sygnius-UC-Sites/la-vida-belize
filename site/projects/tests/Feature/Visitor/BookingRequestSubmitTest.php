<?php

namespace Tests\Feature\Visitor;

use App\Services\Spam\Checks\TimeTrapCheck;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingRequestSubmitTest extends TestCase
{
    use WithoutMiddleware;

    public function test_submission_creates_booking_request_with_pending_approval(): void
    {
        $idempotencyKey = (string) \Illuminate\Support\Str::uuid();
        $payload = [
            'property_id' => 1,
            'check_in' => \Carbon\Carbon::today()->addDays(60)->toDateString(),
            'check_out' => \Carbon\Carbon::today()->addDays(63)->toDateString(),
            'adults' => 2,
            'name' => 'Visitor Flow Test',
            'email' => 'visitor-flow-test@example.com',
            'mobile' => '555-1234',
            'message' => 'Looking forward to staying!',
            'idempotency_key' => $idempotencyKey,
            'addons' => '{}',
            // Spam-protection time-trap signed token (5s old → passes the > 2s floor).
            '_form_ts' => TimeTrapCheck::generateToken(time() - 5),
        ];

        $response = $this->postJson('/booking/request', $payload);
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $booking = DB::table('booking_requests')->where('email', 'visitor-flow-test@example.com')->first();
        $this->assertNotNull($booking);
        $this->assertSame('pending-approval', $booking->booking_status);
        $this->assertSame('check-booking', $booking->payment_status);
        $this->assertSame($idempotencyKey, $booking->request_id);

        // Cleanup
        DB::table('booking_requests')->where('id', $booking->id)->delete();
    }

    public function test_duplicate_submission_with_same_idempotency_key_is_deduped(): void
    {
        $idempotencyKey = (string) \Illuminate\Support\Str::uuid();
        $payload = [
            'property_id' => 1,
            'check_in' => \Carbon\Carbon::today()->addDays(70)->toDateString(),
            'check_out' => \Carbon\Carbon::today()->addDays(73)->toDateString(),
            'adults' => 2,
            'name' => 'Dedupe Test',
            'email' => 'dedupe-test@example.com',
            'idempotency_key' => $idempotencyKey,
            'addons' => '{}',
            // Spam-protection time-trap signed token (5s old → passes the > 2s floor).
            '_form_ts' => TimeTrapCheck::generateToken(time() - 5),
        ];

        $r1 = $this->postJson('/booking/request', $payload);
        $r2 = $this->postJson('/booking/request', $payload);  // same key

        $r1->assertJsonPath('success', true);
        $r2->assertJsonPath('success', true);

        $count = DB::table('booking_requests')->where('email', 'dedupe-test@example.com')->count();
        $this->assertSame(1, $count, 'Same idempotency_key should yield exactly one row');

        DB::table('booking_requests')->where('email', 'dedupe-test@example.com')->delete();
    }
}
