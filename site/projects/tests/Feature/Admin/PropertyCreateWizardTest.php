<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyCreateWizardTest extends TestCase
{
    private int $userId;
    private array $createdPropertyIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_test_property_wizard_user_',
            'email'      => '_test_property_wizard_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->createdPropertyIds as $id) {
            DB::table('property_galleries')->where('property_id', $id)->delete();
            DB::table('property_amenities')->whereIn('property_amenity_id', function ($q) use ($id) {
                $q->select('id')->from('property_amenity_groups')->where('property_id', $id);
            })->delete();
            DB::table('property_amenity_groups')->where('property_id', $id)->delete();
            DB::table('property_rates')->where('property_id', $id)->delete();
            DB::table('properties_rates_group')->where('property_id', $id)->delete();
            DB::table('property_fees')->where('property_id', $id)->delete();
            DB::table('properties')->where('id', $id)->delete();
        }

        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    private function actor(): User
    {
        return User::findOrFail($this->userId);
    }

    public function test_create_page_presents_guided_listing_setup(): void
    {
        $response = $this->actingAs($this->actor())->get('/admin/properties/create');
        $response->assertOk();

        $response->assertSee('Start a new listing');
        $response->assertSee('Step 1 of 9');
        $response->assertSee('Create listing and continue setup');
        $response->assertSee('Rates');
        $response->assertSee('Publish');
        $response->assertSee('Create the listing before uploading gallery photos');
        $response->assertSee('The full gallery manager needs a saved listing', false);
        $response->assertSee('id="quick-create-location-modal"', false);
        $response->assertSee('forceCloseModal', false);
    }

    public function test_store_redirects_new_listing_into_setup_studio(): void
    {
        $slug = '_test-property-wizard-' . uniqid();
        \LiveCart::shouldReceive('getFileIcalFileData')->once()->andReturnTrue();

        $response = $this->actingAs($this->actor())->post('/admin/properties', [
            'name'    => '_test_property_wizard_listing_',
            'heading' => 'Guided listing',
            'seo_url' => $slug,
            'status'  => 'false',
            'sleeps'  => '4',
            'bedroom' => '2',
        ]);

        $id = (int) DB::table('properties')->where('seo_url', $slug)->value('id');
        $this->assertGreaterThan(0, $id);
        $this->createdPropertyIds[] = $id;

        $response->assertRedirect(route('properties.edit', $id) . '#photos');
        $response->assertSessionHas('success', 'Listing created. Continue the setup checklist before publishing.');
    }

    public function test_edit_page_renders_setup_checklist_links(): void
    {
        $id = DB::table('properties')->insertGetId([
            'name'                   => '_test_property_wizard_edit_',
            'seo_url'                => '_test-property-wizard-edit-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'false',
            'sleeps'                 => 4,
            'bedroom'                => 2,
            'standard_rate'          => 250,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
        $this->createdPropertyIds[] = $id;

        $response = $this->actingAs($this->actor())->get('/admin/properties/' . $id . '/edit');
        $response->assertOk();

        $response->assertSee('Listing setup');
        $response->assertSee('steps ready');
        $response->assertSee('data-pe-nav="calendar"', false);
        $response->assertSee('Rates &amp; calendar', false);
        $response->assertSee('Fees &amp; deposits', false);
    }

    public function test_property_forms_expose_type_division_control(): void
    {
        $response = $this->actingAs($this->actor())->get('/admin/properties/create');
        $response->assertOk();

        $response->assertSee('Property type / division', false);
        $response->assertSee('name="type"', false);
        $response->assertSee('No division / general stay', false);
        $response->assertSee('Condo', false);
        $response->assertSee('House', false);
    }

    public function test_properties_index_uses_canonical_public_links(): void
    {
        $slug = '_test-property-link-' . uniqid();
        $id = DB::table('properties')->insertGetId([
            'name'                   => '_test_property_public_link_',
            'heading'                => 'Public link regression',
            'seo_url'                => $slug,
            'instant_booking_button' => '',
            'status'                 => 'true',
            'type'                   => 'property',
            'sleeps'                 => 4,
            'bedroom'                => 2,
            'standard_rate'          => 250,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
        $this->createdPropertyIds[] = $id;

        $response = $this->actingAs($this->actor())->get('/admin/properties');
        $response->assertOk();

        $response->assertSee('/stay/' . $slug, false);
        $response->assertDontSee('href="' . url('/' . $slug) . '"', false);
    }
}
