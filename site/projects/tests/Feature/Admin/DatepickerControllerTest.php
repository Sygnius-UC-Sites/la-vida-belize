<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatepickerControllerTest extends TestCase
{
    private int $testPropertyId;
    private int $testUserId;

    protected function setUp(): void
    {
        parent::setUp();

        // Insert a minimal test property.
        // `name`, `seo_url`, and `instant_booking_button` are NOT NULL without DB defaults.
        $this->testPropertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_datepicker_property_',
            'seo_url'                => '_test-datepicker-' . uniqid(),
            'instant_booking_button' => '',
            'min_stay'               => 2,
            'status'                 => 'true',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        // Insert a minimal test user
        $this->testUserId = DB::table('users')->insertGetId([
            'name'       => '_test_datepicker_user_',
            'email'      => '_test_datepicker_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('properties')->where('id', $this->testPropertyId)->delete();
        DB::table('users')->where('id', $this->testUserId)->delete();
        parent::tearDown();
    }

    public function test_returns_blocked_dates_for_a_property(): void
    {
        $user = User::findOrFail($this->testUserId);

        $response = $this->actingAs($user)
            ->getJson('/admin/api/datepicker/blocked-dates?property_id=' . $this->testPropertyId);

        $response->assertOk()
            ->assertJsonStructure([
                'blocked',
                'checkin',
                'checkout',
                'min_stay',
                'max_stay',
            ])
            ->assertJson([
                'min_stay' => 2,
            ]);
    }

    public function test_requires_auth(): void
    {
        $response = $this->getJson('/admin/api/datepicker/blocked-dates?property_id=' . $this->testPropertyId);
        $response->assertStatus(401);
    }

    public function test_validates_property_id(): void
    {
        $user = User::findOrFail($this->testUserId);
        $response = $this->actingAs($user)
            ->getJson('/admin/api/datepicker/blocked-dates');
        $response->assertStatus(422);
    }

    public function test_returns_empty_arrays_for_property_with_no_blocks(): void
    {
        $user = User::findOrFail($this->testUserId);

        $response = $this->actingAs($user)
            ->getJson('/admin/api/datepicker/blocked-dates?property_id=' . $this->testPropertyId);

        $response->assertOk()
            ->assertJson([
                'blocked' => [],
                'checkin' => [],
                'checkout' => [],
            ]);
    }
}
