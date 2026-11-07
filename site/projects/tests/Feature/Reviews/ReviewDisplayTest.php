<?php

namespace Tests\Feature\Reviews;

use App\Models\Property;
use App\Support\PropertyUrl;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReviewDisplayTest extends TestCase
{
    private int $propertyId;
    private array $testimonialIds = [];
    private string $seoUrl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seoUrl = 'rdt-test-' . uniqid();
        $this->propertyId = DB::table('properties')->insertGetId([
            'name' => 'RDT-Test ' . uniqid(),
            'seo_url' => $this->seoUrl,
            'instant_booking_button' => 'Book Now',
            'standard_rate' => 200,
            'no_of_guest' => 4,
            'type' => 'condo',
            'status' => 'true',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->testimonialIds) {
            DB::table('testimonials')->whereIn('id', $this->testimonialIds)->delete();
        }
        DB::table('properties')->where('id', $this->propertyId)->delete();
        parent::tearDown();
    }

    private function insertTestimonial(array $overrides): int
    {
        $id = DB::table('testimonials')->insertGetId(array_merge([
            'property_id' => $this->propertyId,
            'name'        => 'TestRev-' . uniqid(),
            'message'     => 'A pleasant stay was had by all of the family.',
            'status'      => 'true',
            'is_home'     => 'false',
            'rating'      => 5,
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $overrides));
        $this->testimonialIds[] = $id;
        return $id;
    }

    public function test_property_detail_renders_star_average_and_count(): void
    {
        $this->insertTestimonial(['rating' => 5]);
        $this->insertTestimonial(['rating' => 4]);
        $this->insertTestimonial(['rating' => 3]);

        $resp = $this->get(PropertyUrl::path(Property::findOrFail($this->propertyId)));
        $resp->assertOk();
        // Average (5+4+3)/3 = 4 → "4 / 5"
        $resp->assertSee('4 / 5', false);
        $resp->assertSee('3 reviews', false);
    }

    public function test_property_detail_omits_unapproved_reviews(): void
    {
        $approvedId = $this->insertTestimonial(['rating' => 5, 'status' => 'true', 'name' => 'ApprovedReviewer']);
        $this->insertTestimonial(['rating' => 4, 'status' => 'pending-link-sent', 'name' => 'PendingReviewer']);
        $this->insertTestimonial(['rating' => 3, 'status' => 'false', 'name' => 'AwaitingModerationReviewer']);

        $resp = $this->get(PropertyUrl::path(Property::findOrFail($this->propertyId)));
        $resp->assertOk();
        $resp->assertSee('ApprovedReviewer', false);
        $resp->assertDontSee('PendingReviewer', false);
        $resp->assertDontSee('AwaitingModerationReviewer', false);
    }

    public function test_homepage_shows_only_featured_approved_reviews(): void
    {
        $this->insertTestimonial(['status' => 'true', 'is_home' => 'true', 'name' => 'FeaturedReviewer-' . uniqid()]);
        $this->insertTestimonial(['status' => 'true', 'is_home' => 'false', 'name' => 'ApprovedNotFeaturedReviewer-' . uniqid()]);
        $this->insertTestimonial(['status' => 'false', 'is_home' => 'true', 'name' => 'PendingFeaturedReviewer-' . uniqid()]);

        $resp = $this->get('/');
        $resp->assertOk();
        $resp->assertSee('FeaturedReviewer', false);
        $resp->assertDontSee('ApprovedNotFeaturedReviewer', false);
        $resp->assertDontSee('PendingFeaturedReviewer', false);
    }
}
