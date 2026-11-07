<?php

namespace Tests\Feature\Admin;

use App\Models\PropertyFee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyEditPhase33Test extends TestCase
{
    private int $propertyId;
    private int $userId;
    private array $feeIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_phase33_property_',
            'seo_url'                => '_test-phase33-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'true',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_test_phase33_user_',
            'email'      => '_test_phase33_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Three fees, sort_order 0/1/2 — exercised by inline-edit, status-toggle, sort-up tests.
        foreach ([
            ['Fee A', 20.00, 'one_time', 0],
            ['Fee B', 50.00, 'one_time', 1],
            ['Fee C', 75.00, 'per_day',  2],
        ] as [$name, $rate, $billing, $sortOrder]) {
            $this->feeIds[] = DB::table('property_fees')->insertGetId([
                'property_id'      => $this->propertyId,
                'name'             => $name,
                'rate'             => $rate,
                'billing_type'     => $billing,
                'taxable'          => 1,
                'applies_discount' => 0,
                'sort_order'       => $sortOrder,
                'status'           => 'active',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
    }

    protected function tearDown(): void
    {
        DB::table('property_fees')->where('property_id', $this->propertyId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    private function basePayload(): array
    {
        $seoUrl = DB::table('properties')->where('id', $this->propertyId)->value('seo_url');
        return ['name' => '_test_phase33_property_', 'seo_url' => $seoUrl];
    }

    public function test_property_save_succeeds_without_dropped_fields(): void
    {
        // Payload deliberately omits api_pms, api_id, notes, rental_aggrement_attachment, instant_booking_button.
        $response = $this->actingAs(User::findOrFail($this->userId))
            ->put('/admin/properties/' . $this->propertyId, $this->basePayload());

        $response->assertRedirect();
        $response->assertSessionMissing('errors');
        $this->assertNotNull(DB::table('properties')->where('id', $this->propertyId)->first());
    }

    public function test_welcome_package_attachment_field_round_trips(): void
    {
        // The actual file-upload pipeline is exercised in Phase 2B tests; here we verify
        // the field persists when posted as a string (no file). PropertyController's
        // hasFile() guard skips the upload branch, $request->all() carries the string
        // through fillable assignment.
        $payload = $this->basePayload() + ['welcome_package_attachment' => '/uploads/properties/_test.pdf'];

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->put('/admin/properties/' . $this->propertyId, $payload);
        $response->assertRedirect();

        $value = DB::table('properties')->where('id', $this->propertyId)->value('welcome_package_attachment');
        $this->assertSame('/uploads/properties/_test.pdf', $value);
    }

    public function test_addon_dropdowns_no_longer_render(): void
    {
        $response = $this->actingAs(User::findOrFail($this->userId))
            ->get('/admin/properties/' . $this->propertyId . '/edit');
        $response->assertOk();

        foreach ([
            'tax_applies_to_hottub_addon',
            'tax_applies_to_firewood_addon',
            'tax_applies_to_rv_hookup_addon',
            'tax_applies_to_extra_visitor_addon',
            'discount_applies_to_hottub_addon',
            'discount_applies_to_firewood_addon',
            'discount_applies_to_rv_hookup_addon',
            'discount_applies_to_extra_visitor_addon',
        ] as $stale) {
            $response->assertDontSee($stale);
        }
    }

    public function test_additional_fee_inline_edit_persists_rate_change(): void
    {
        $feeId = $this->feeIds[0];
        $payload = [
            'name'             => 'Fee A',
            'rate'             => '99.99',
            'billing_type'     => 'one_time',
            'taxable'          => '1',
            'applies_discount' => '1',
            'sort_order'       => '0',
            'status'           => 'active',
        ];

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->put('/admin/properties/' . $this->propertyId . '/fees/' . $feeId, $payload);
        $response->assertRedirect();

        $row = PropertyFee::find($feeId);
        $this->assertEquals(99.99, (float)$row->rate);
        $this->assertTrue((bool)$row->applies_discount);
    }

    public function test_additional_fee_status_toggle_active_to_inactive(): void
    {
        $feeId = $this->feeIds[1];
        $payload = [
            'name'         => 'Fee B',
            'rate'         => '50.00',
            'billing_type' => 'one_time',
            'sort_order'   => '1',
            'status'       => 'inactive',
            // No taxable / applies_discount keys → controller's $request->boolean() resolves them to false
        ];

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->put('/admin/properties/' . $this->propertyId . '/fees/' . $feeId, $payload);
        $response->assertRedirect();

        $row = PropertyFee::find($feeId);
        $this->assertSame('inactive', $row->status);
        $this->assertFalse((bool)$row->taxable);
        $this->assertFalse((bool)$row->applies_discount);
    }

    public function test_additional_fee_sort_up_swaps_with_neighbour(): void
    {
        // Fee B is the middle one (sort_order=1). Move it up — should swap with Fee A (sort_order=0).
        $middleId = $this->feeIds[1];
        $firstId  = $this->feeIds[0];

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/fees/' . $middleId . '/sort', ['direction' => 'up']);
        $response->assertRedirect();

        $this->assertSame(0, (int)PropertyFee::find($middleId)->sort_order);
        $this->assertSame(1, (int)PropertyFee::find($firstId)->sort_order);
    }
}
