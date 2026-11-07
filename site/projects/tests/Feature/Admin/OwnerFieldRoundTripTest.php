<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OwnerFieldRoundTripTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = DB::table('users')->insertGetId([
            'name'       => 'Owner Field Tester',
            'email'      => 'owner-field-' . uniqid() . '@example.test',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function actor(): User
    {
        return User::findOrFail($this->userId);
    }

    public function test_owner_can_update_cms_page_fields(): void
    {
        $id = DB::table('cms')->insertGetId([
            'name'       => 'Roundtrip CMS',
            'seo_url'    => 'roundtrip-cms-' . uniqid(),
            'templete'   => 'common',
            'publish'    => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $slug = 'roundtrip-cms-updated-' . uniqid();
        $response = $this->actingAs($this->actor())->put('/admin/cms/' . $id, [
            'name'             => 'Updated CMS Page',
            'seo_url'          => $slug,
            'templete'         => 'common',
            'publish'          => 'published',
            'meta_title'       => 'Updated CMS Meta',
            'meta_description' => 'Updated CMS description',
            'shortDescription' => 'Short copy',
            'mediumDescription' => 'Medium copy',
            'longDescription'  => '<p>Long copy</p>',
            'hero_heading'     => 'Updated Hero Heading',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('errors');
        $this->assertDatabaseHas('cms', [
            'id'               => $id,
            'name'             => 'Updated CMS Page',
            'seo_url'          => $slug,
            'meta_title'       => 'Updated CMS Meta',
            'meta_description' => 'Updated CMS description',
            'shortDescription' => 'Short copy',
            'mediumDescription' => 'Medium copy',
            'longDescription'  => '<p>Long copy</p>',
            'hero_heading'     => 'Updated Hero Heading',
        ]);
    }

    public function test_owner_can_edit_home_template_fields_even_when_slug_is_not_home(): void
    {
        $id = DB::table('cms')->insertGetId([
            'name'                 => 'Clone Home CMS',
            'seo_url'              => 'clone-home-' . uniqid(),
            'templete'             => 'home',
            'publish'              => 'published',
            'hero_eyebrow'         => 'Original eyebrow',
            'hero_heading'         => 'Original heading',
            'hero_sub'             => 'Original subcopy',
            'hero_carousel_images' => json_encode(['/uploads/cms/original.jpg']),
            'ui_labels'            => json_encode([['key' => 'btn_check_availability', 'value' => 'Check Availability']]),
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        $edit = $this->actingAs($this->actor())->get('/admin/cms/' . $id . '/edit');
        $edit->assertOk();
        $edit->assertSee('Hero Section', false);
        $edit->assertSee('name="hero_eyebrow"', false);
        $edit->assertSee('name="hero_heading"', false);
        $edit->assertSee('name="hero_sub"', false);
        $edit->assertSee('hero_carousel_images', false);
        $edit->assertSee('Upload image', false);
        $edit->assertSee('Badge image', false);
        $edit->assertSee('ui_labels', false);

        $payload = [
            'name'                 => 'Updated Clone Home CMS',
            'seo_url'              => 'clone-home-updated-' . uniqid(),
            'templete'             => 'home',
            'publish'              => 'published',
            'hero_eyebrow'         => 'Updated eyebrow',
            'hero_heading'         => 'Updated heading',
            'hero_sub'             => 'Updated subcopy',
            'hero_carousel_images' => json_encode(['/uploads/cms/updated.jpg']),
            'ui_labels'            => json_encode([['key' => 'btn_check_availability', 'value' => 'Search Dates']]),
        ];

        $response = $this->actingAs($this->actor())->put('/admin/cms/' . $id, $payload);
        $response->assertRedirect();
        $response->assertSessionMissing('errors');

        $this->assertDatabaseHas('cms', [
            'id'                   => $id,
            'name'                 => 'Updated Clone Home CMS',
            'seo_url'              => $payload['seo_url'],
            'templete'             => 'home',
            'hero_eyebrow'         => 'Updated eyebrow',
            'hero_heading'         => 'Updated heading',
            'hero_sub'             => 'Updated subcopy',
            'hero_carousel_images' => json_encode(['/uploads/cms/updated.jpg']),
            'ui_labels'            => json_encode([['key' => 'btn_check_availability', 'value' => 'Search Dates']]),
        ]);
    }

    public function test_owner_can_update_location_and_attraction_taxonomy_fields(): void
    {
        $locationId = DB::table('locations')->insertGetId([
            'name'       => 'Roundtrip Location',
            'seo_url'    => 'roundtrip-location-' . uniqid(),
            'templete'   => 'common',
            'publish'    => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $categoryId = DB::table('attraction_categories')->insertGetId([
            'name'       => 'Roundtrip Category',
            'seo_url'    => 'roundtrip-category-' . uniqid(),
            'templete'   => 'common',
            'publish'    => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $locationSlug = 'roundtrip-location-updated-' . uniqid();
        $categorySlug = 'roundtrip-category-updated-' . uniqid();

        $locationResponse = $this->actingAs($this->actor())->put('/admin/locations/' . $locationId, [
            'name'             => 'Updated Location',
            'seo_url'          => $locationSlug,
            'templete'         => 'common',
            'publish'          => 'published',
            'description'      => 'Updated location body',
            'meta_title'       => 'Updated location meta',
            'meta_description' => 'Updated location description',
        ]);
        $categoryResponse = $this->actingAs($this->actor())->put('/admin/attraction-categories/' . $categoryId, [
            'name'             => 'Updated Category',
            'seo_url'          => $categorySlug,
            'templete'         => 'common',
            'publish'          => 'published',
            'description'      => 'Updated category body',
            'meta_title'       => 'Updated category meta',
            'meta_description' => 'Updated category description',
        ]);

        $locationResponse->assertRedirect();
        $categoryResponse->assertRedirect();
        $locationResponse->assertSessionMissing('errors');
        $categoryResponse->assertSessionMissing('errors');
        $this->assertDatabaseHas('locations', [
            'id'               => $locationId,
            'name'             => 'Updated Location',
            'seo_url'          => $locationSlug,
            'description'      => 'Updated location body',
            'meta_title'       => 'Updated location meta',
            'meta_description' => 'Updated location description',
        ]);
        $this->assertDatabaseHas('attraction_categories', [
            'id'               => $categoryId,
            'name'             => 'Updated Category',
            'seo_url'          => $categorySlug,
            'description'      => 'Updated category body',
            'meta_title'       => 'Updated category meta',
            'meta_description' => 'Updated category description',
        ]);
    }

    public function test_owner_can_update_attraction_fields(): void
    {
        $locationId = DB::table('locations')->value('id');
        $categoryId = DB::table('attraction_categories')->value('id');
        $id = DB::table('attractions')->insertGetId([
            'name'        => 'Roundtrip Attraction',
            'seo_url'     => 'roundtrip-attraction-' . uniqid(),
            'templete'    => '',
            'publish'     => 'published',
            'location_id' => $locationId,
            'category_id' => $categoryId,
            'ordering'    => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $slug = 'roundtrip-attraction-updated-' . uniqid();
        $response = $this->actingAs($this->actor())->put('/admin/attractions/' . $id, [
            'name'             => 'Updated Attraction',
            'seo_url'          => $slug,
            'templete'         => '',
            'publish'          => 'published',
            'location_id'      => $locationId,
            'category_id'      => $categoryId,
            'ordering'         => 7,
            'type'             => 'Dining',
            'mobile'           => '+1 555 0100',
            'address'          => '101 Admin Test Ave',
            'map_url'          => 'https://maps.example.test/attraction',
            'description'      => 'Updated attraction body',
            'meta_title'       => 'Updated attraction meta',
            'meta_description' => 'Updated attraction description',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('errors');
        $this->assertDatabaseHas('attractions', [
            'id'               => $id,
            'name'             => 'Updated Attraction',
            'seo_url'          => $slug,
            'ordering'         => 7,
            'type'             => 'Dining',
            'mobile'           => '+1 555 0100',
            'address'          => '101 Admin Test Ave',
            'map_url'          => 'https://maps.example.test/attraction',
            'description'      => 'Updated attraction body',
            'meta_title'       => 'Updated attraction meta',
            'meta_description' => 'Updated attraction description',
        ]);
    }

    public function test_owner_can_save_property_stay_communication_fields(): void
    {
        $id = DB::table('properties')->insertGetId([
            'name'                   => 'Stay Comms Property',
            'seo_url'                => 'stay-comms-property-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'false',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $response = $this->actingAs($this->actor())->put('/admin/properties/' . $id, [
            'name'                         => 'Stay Comms Property Updated',
            'seo_url'                      => 'stay-comms-property-updated-' . uniqid(),
            'checkin_arrival_window'       => '4:00 PM - 8:00 PM',
            'checkin_door_code'            => '1234',
            'checkin_wifi_name'            => 'Guest WiFi',
            'checkin_wifi_password'        => 'secret-pass',
            'checkin_parking_instructions' => 'Park in driveway.',
            'checkin_host_name'            => 'Owner Host',
            'checkin_host_phone'           => '+1 555 111 2222',
            'checkin_emergency_phone'      => '+1 555 999 0000',
            'detail_check_in_instructions' => '<p>Use the side entrance.</p>',
            'checkout_departure_time'      => '10:00 AM',
            'checkout_key_return'          => 'Leave keys on counter.',
            'checkout_cleaning_checklist'  => '<p>Start dishwasher.</p>',
            'checkout_notes'               => '<p>Thanks for staying.</p>',
            'welcome_email_send_day'       => '',
            'checkin_email_send_day'       => 2,
            'checkout_email_send_day'      => 1,
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('errors');
        $this->assertDatabaseHas('properties', [
            'id'                            => $id,
            'checkin_arrival_window'        => '4:00 PM - 8:00 PM',
            'checkin_door_code'             => '1234',
            'checkin_wifi_name'             => 'Guest WiFi',
            'checkin_wifi_password'         => 'secret-pass',
            'checkin_parking_instructions'  => 'Park in driveway.',
            'checkin_host_name'             => 'Owner Host',
            'checkin_host_phone'            => '+1 555 111 2222',
            'checkin_emergency_phone'       => '+1 555 999 0000',
            'detail_check_in_instructions'  => '<p>Use the side entrance.</p>',
            'checkout_departure_time'       => '10:00 AM',
            'checkout_key_return'           => 'Leave keys on counter.',
            'checkout_cleaning_checklist'   => '<p>Start dishwasher.</p>',
            'checkout_notes'                => '<p>Thanks for staying.</p>',
            'welcome_email_send_day'        => null,
            'checkin_email_send_day'        => 2,
            'checkout_email_send_day'       => 1,
        ]);
    }

    public function test_faq_category_blank_order_saves_as_zero(): void
    {
        $slug = 'faq-blank-order-' . uniqid();

        $response = $this->actingAs($this->actor())->post('/admin/faq-categories', [
            'name'     => 'FAQ Blank Order',
            'seo_url'  => $slug,
            'ordering' => '',
            'publish'  => 'published',
        ]);

        $response->assertRedirect(route('faq-categories.index'));
        $response->assertSessionMissing('errors');
        $this->assertDatabaseHas('faq_categories', [
            'seo_url'  => $slug,
            'ordering' => 0,
            'publish'  => 'published',
        ]);
    }

    public function test_reported_admin_taxonomy_pages_render_owner_facing_forms(): void
    {
        $this->actingAs($this->actor())
            ->get('/admin/attractions/create')
            ->assertOk()
            ->assertSee('Attraction details', false)
            ->assertSee('Quick add location', false)
            ->assertSee('Save attraction', false);

        $this->actingAs($this->actor())
            ->get('/admin/attraction-categories/create')
            ->assertOk()
            ->assertSee('Category details', false)
            ->assertSee('Card image', false)
            ->assertSee('Save category', false)
            ->assertDontSee('Templete', false);

        $this->actingAs($this->actor())
            ->get('/admin/faq-categories/create')
            ->assertOk()
            ->assertSee('FAQ category', false)
            ->assertSee('Blank saves as 0', false);

        $this->actingAs($this->actor())
            ->get('/admin/property-categories/create')
            ->assertOk()
            ->assertSee('Add property category', false)
            ->assertSee('owner-narrow-card', false);
    }

    public function test_owner_can_update_faq_testimonial_and_coupon_fields(): void
    {
        $faqId = DB::table('faqs')->insertGetId([
            'question'   => 'Roundtrip FAQ?',
            'answer'     => 'Original answer',
            'type'       => 'general',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $testimonialId = DB::table('testimonials')->insertGetId([
            'name'       => 'Roundtrip Guest',
            'message'    => 'Original message',
            'email'      => 'guest@example.test',
            'status'     => 'true',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $couponId = DB::table('coupons')->insertGetId([
            'name'       => 'Roundtrip Coupon',
            'code'       => 'ROUNDTRIP-' . strtoupper(substr(uniqid(), -6)),
            'type'       => 'amount',
            'amount'     => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $faqResponse = $this->actingAs($this->actor())->put('/admin/faqs/' . $faqId, [
            'question'    => 'Updated FAQ?',
            'answer'      => 'Updated answer',
            'type'        => 'property',
            'property_id' => 1,
        ]);
        $testimonialResponse = $this->actingAs($this->actor())->put('/admin/testimonials/' . $testimonialId, [
            'name'        => 'Updated Guest',
            'message'     => 'Updated testimonial message',
            'email'       => 'updated-guest@example.test',
            'mobile'      => '+1 555 0101',
            'profile'     => 'Airbnb',
            'score'       => '9.8',
            'rating'      => 5,
            'status'      => 'true',
            'is_home'     => 'true',
            'property_id' => 1,
        ]);
        $couponResponse = $this->actingAs($this->actor())->put('/admin/coupons/' . $couponId, [
            'name'                  => 'Updated Coupon',
            'description'           => 'Updated coupon description',
            'code'                  => 'ROUNDTRIP-UPD-' . strtoupper(substr(uniqid(), -6)),
            'type'                  => 'percentage',
            'amount'                => 15,
            'property_id'           => 1,
            'start_date'            => '2031-01-01',
            'end_date'              => '2031-12-31',
            'min_stay'              => 3,
            'max_uses'              => 20,
            'max_uses_per_customer' => 2,
            'min_order_amount'      => 500,
        ]);

        $faqResponse->assertRedirect();
        $testimonialResponse->assertRedirect();
        $couponResponse->assertRedirect();
        $faqResponse->assertSessionMissing('errors');
        $testimonialResponse->assertSessionMissing('errors');
        $couponResponse->assertSessionMissing('errors');

        $this->assertDatabaseHas('faqs', [
            'id'          => $faqId,
            'question'    => 'Updated FAQ?',
            'answer'      => 'Updated answer',
            'type'        => 'property',
            'property_id' => 1,
        ]);
        $this->assertDatabaseHas('testimonials', [
            'id'          => $testimonialId,
            'name'        => 'Updated Guest',
            'message'     => 'Updated testimonial message',
            'email'       => 'updated-guest@example.test',
            'mobile'      => '+1 555 0101',
            'profile'     => 'Airbnb',
            'score'       => '9.8',
            'rating'      => 5,
            'status'      => 'true',
            'is_home'     => 'true',
            'property_id' => 1,
        ]);
        $this->assertDatabaseHas('coupons', [
            'id'                    => $couponId,
            'name'                  => 'Updated Coupon',
            'description'           => 'Updated coupon description',
            'type'                  => 'percentage',
            'amount'                => 15,
            'property_id'           => 1,
            'start_date'            => '2031-01-01',
            'end_date'              => '2031-12-31',
            'min_stay'              => 3,
            'max_uses'              => 20,
            'max_uses_per_customer' => 2,
            'min_order_amount'      => 500,
        ]);
    }
}
