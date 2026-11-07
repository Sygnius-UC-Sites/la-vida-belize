<?php

namespace Tests\Feature\Reviews;

use App\Services\Spam\Checks\TimeTrapCheck;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReviewSubmissionFlowTest extends TestCase
{
    private int $propertyId;
    private int $testimonialId;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name' => 'RSF-Test ' . uniqid(),
            'seo_url' => 'rsf-test-' . uniqid(),
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 200,
            'no_of_guest' => 4,
            'type' => 'condo',
            'status' => 'true',
        ]);

        $this->token = bin2hex(random_bytes(24));
        $this->testimonialId = DB::table('testimonials')->insertGetId([
            'property_id'      => $this->propertyId,
            'submission_token' => $this->token,
            'status'           => 'pending-link-sent',
            'name'             => 'Review Guest',
            'email'            => 'rsf@example.com',
            'stay_date'        => '2026-05-25',
            'is_home'          => 'false',
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('testimonials')->where('id', $this->testimonialId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        parent::tearDown();
    }

    public function test_get_review_form_with_valid_token_renders(): void
    {
        $resp = $this->get('/review/' . $this->token);
        $resp->assertOk();
        $resp->assertSee('Review your stay', false);
        $resp->assertSee('star-rating', false);
        $resp->assertSee('Review Guest', false);
    }

    public function test_get_review_form_with_invalid_token_404s(): void
    {
        $resp = $this->get('/review/no-such-token-anywhere-zzz');
        $resp->assertNotFound();
    }

    public function test_get_review_form_with_already_approved_token_shows_thanks(): void
    {
        DB::table('testimonials')->where('id', $this->testimonialId)->update(['status' => 'true']);

        $resp = $this->get('/review/' . $this->token);
        $resp->assertOk();
        $resp->assertSee('already been received', false);
    }

    public function test_post_review_with_valid_data_updates_testimonial(): void
    {
        $resp = $this->post('/review/' . $this->token, [
            'rating'  => 5,
            'message' => 'Great stay great great stay great',
            // Spam-protection time-trap: 5s-old signed timestamp passes the > 2s floor.
            '_form_ts' => TimeTrapCheck::generateToken(time() - 5),
        ]);
        $resp->assertOk(); // returns the thanks view directly

        $row = DB::table('testimonials')->where('id', $this->testimonialId)->first();
        $this->assertSame(5, (int) $row->rating);
        $this->assertSame('5', (string) $row->score);
        $this->assertSame('false', $row->status);
        $this->assertSame('Great stay great great stay great', $row->message);
        $this->assertNotNull($row->submitted_at);
        $this->assertNotEmpty($row->submitted_ip);
    }

    public function test_post_review_with_invalid_rating_rejects(): void
    {
        $resp = $this->from('/review/' . $this->token)->post('/review/' . $this->token, [
            'rating'  => 6,
            'message' => 'Long enough message about the stay here',
            '_form_ts' => TimeTrapCheck::generateToken(time() - 5),
        ]);
        $resp->assertRedirect('/review/' . $this->token);
        $resp->assertSessionHasErrors('rating');

        $row = DB::table('testimonials')->where('id', $this->testimonialId)->first();
        $this->assertNull($row->rating);
        $this->assertSame('pending-link-sent', $row->status);
    }

    public function test_post_review_with_short_message_rejects(): void
    {
        $resp = $this->from('/review/' . $this->token)->post('/review/' . $this->token, [
            'rating'  => 4,
            'message' => 'short',
            '_form_ts' => TimeTrapCheck::generateToken(time() - 5),
        ]);
        $resp->assertRedirect('/review/' . $this->token);
        $resp->assertSessionHasErrors('message');

        $row = DB::table('testimonials')->where('id', $this->testimonialId)->first();
        $this->assertNull($row->rating);
        $this->assertSame('pending-link-sent', $row->status);
    }
}
