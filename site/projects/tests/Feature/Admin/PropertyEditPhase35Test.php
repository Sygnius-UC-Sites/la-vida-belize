<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyEditPhase35Test extends TestCase
{
    private int $propertyId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_phase35_property_',
            'seo_url'                => '_test-phase35-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'true',
            'is_home'                => 'false',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_test_phase35_user_',
            'email'      => '_test_phase35_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_status_and_is_home_dropdowns_render_friendly_labels(): void
    {
        $response = $this->actingAs(User::findOrFail($this->userId))
            ->get('/admin/properties/' . $this->propertyId . '/edit');
        $response->assertOk();

        $body = $response->getContent();

        // Status dropdown: "Active" / "Inactive" labels (not "true" / "false")
        $this->assertStringContainsString('>Active</option>', $body);
        $this->assertStringContainsString('>Inactive</option>', $body);

        // is_home dropdown: "Yes" / "No" labels
        $this->assertStringContainsString('>Yes</option>', $body);
        $this->assertStringContainsString('>No</option>', $body);

        // Literal "true" / "false" should not appear as user-visible <option> labels
        $this->assertStringNotContainsString('>true</option>', $body);
        $this->assertStringNotContainsString('>false</option>', $body);
    }
}
