<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyAdminPhase7Test extends TestCase
{
    private array $propertyIds = [];
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        for ($i = 0; $i < 4; $i++) {
            $this->propertyIds[] = DB::table('properties')->insertGetId([
                'name'                   => '_test_p7_property_' . $i . '_',
                'seo_url'                => '_test-p7-' . $i . '-' . uniqid(),
                'instant_booking_button' => '',
                'status'                 => 'false',
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);
        }

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_test_p7_user_',
            'email'      => '_test_p7_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        foreach ($this->propertyIds as $id) {
            DB::table('property_highlights')->where('property_id', $id)->delete();
            DB::table('property_sleep_cards')->where('property_id', $id)->delete();
            DB::table('property_amenity_highlights')->where('property_id', $id)->delete();
            DB::table('properties')->where('id', $id)->delete();
        }
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    private function actor(): User
    {
        return User::findOrFail($this->userId);
    }

    public function test_bulk_activate_sets_status_true_for_listed_ids(): void
    {
        $ids = array_slice($this->propertyIds, 0, 3);
        $resp = $this->actingAs($this->actor())
            ->postJson('/admin/properties/bulk-activate', ['ids' => $ids]);
        $resp->assertOk()->assertJson(['ok' => true, 'updated' => 3]);

        foreach ($ids as $id) {
            $this->assertSame('true', DB::table('properties')->where('id', $id)->value('status'));
        }
        // The 4th (untouched) is still 'false'.
        $this->assertSame('false', DB::table('properties')->where('id', $this->propertyIds[3])->value('status'));
    }

    public function test_bulk_deactivate_sets_status_false_for_listed_ids(): void
    {
        DB::table('properties')->whereIn('id', $this->propertyIds)->update(['status' => 'true']);
        $ids = array_slice($this->propertyIds, 0, 2);
        $resp = $this->actingAs($this->actor())
            ->postJson('/admin/properties/bulk-deactivate', ['ids' => $ids]);
        $resp->assertOk()->assertJson(['ok' => true, 'updated' => 2]);

        foreach ($ids as $id) {
            $this->assertSame('false', DB::table('properties')->where('id', $id)->value('status'));
        }
        $this->assertSame('true', DB::table('properties')->where('id', $this->propertyIds[2])->value('status'));
    }

    public function test_bulk_delete_removes_listed_properties_only(): void
    {
        $deleteIds  = array_slice($this->propertyIds, 0, 2);
        $surviveIds = array_slice($this->propertyIds, 2);

        $resp = $this->actingAs($this->actor())
            ->postJson('/admin/properties/bulk-delete', ['ids' => $deleteIds]);
        $resp->assertOk()->assertJson(['ok' => true, 'deleted' => 2]);

        foreach ($deleteIds as $id) {
            $this->assertSame(0, DB::table('properties')->where('id', $id)->count());
        }
        foreach ($surviveIds as $id) {
            $this->assertSame(1, DB::table('properties')->where('id', $id)->count());
        }

        // Drop now-deleted ids so tearDown doesn't double-delete.
        $this->propertyIds = $surviveIds;
    }

    public function test_bulk_endpoints_validate_ids_array(): void
    {
        $this->actingAs($this->actor())
            ->postJson('/admin/properties/bulk-activate', [])
            ->assertStatus(422);
        $this->actingAs($this->actor())
            ->postJson('/admin/properties/bulk-deactivate', ['ids' => []])
            ->assertStatus(422);
        $this->actingAs($this->actor())
            ->postJson('/admin/properties/bulk-delete', ['ids' => 'not-an-array'])
            ->assertStatus(422);
    }

    public function test_highlights_reorder_sets_sorting_by_array_position(): void
    {
        $pid = $this->propertyIds[0];
        $h1 = DB::table('property_highlights')->insertGetId([
            'property_id' => $pid, 'icon' => 'lock', 'title' => 'A', 'description' => 'a', 'sorting' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $h2 = DB::table('property_highlights')->insertGetId([
            'property_id' => $pid, 'icon' => 'lock', 'title' => 'B', 'description' => 'b', 'sorting' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $h3 = DB::table('property_highlights')->insertGetId([
            'property_id' => $pid, 'icon' => 'lock', 'title' => 'C', 'description' => 'c', 'sorting' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->actingAs($this->actor())
            ->postJson("/admin/properties/{$pid}/highlights/reorder", ['ids' => [$h3, $h1, $h2]]);
        $resp->assertOk()->assertJson(['ok' => true, 'reordered' => 3]);

        $this->assertSame(0, (int) DB::table('property_highlights')->where('id', $h3)->value('sorting'));
        $this->assertSame(1, (int) DB::table('property_highlights')->where('id', $h1)->value('sorting'));
        $this->assertSame(2, (int) DB::table('property_highlights')->where('id', $h2)->value('sorting'));
    }

    public function test_detail_page_repeater_delete_endpoints_delete_rows(): void
    {
        $pid = $this->propertyIds[0];
        $highlightId = DB::table('property_highlights')->insertGetId([
            'property_id' => $pid, 'icon' => 'lock', 'title' => 'A', 'description' => 'a', 'sorting' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $sleepCardId = DB::table('property_sleep_cards')->insertGetId([
            'property_id' => $pid, 'title' => 'Bed1', 'text' => 'one', 'sorting' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $amenityHighlightId = DB::table('property_amenity_highlights')->insertGetId([
            'property_id' => $pid, 'emoji' => 'wifi', 'label' => 'Fast WiFi', 'sorting' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->actor())
            ->postJson('/admin/delete-property-highlight', ['id' => $highlightId])
            ->assertOk()
            ->assertJson(['ok' => true, 'deleted' => 1]);
        $this->actingAs($this->actor())
            ->postJson('/admin/delete-property-sleep-card', ['id' => $sleepCardId])
            ->assertOk()
            ->assertJson(['ok' => true, 'deleted' => 1]);
        $this->actingAs($this->actor())
            ->postJson('/admin/delete-property-amenity-highlight', ['id' => $amenityHighlightId])
            ->assertOk()
            ->assertJson(['ok' => true, 'deleted' => 1]);

        $this->assertDatabaseMissing('property_highlights', ['id' => $highlightId]);
        $this->assertDatabaseMissing('property_sleep_cards', ['id' => $sleepCardId]);
        $this->assertDatabaseMissing('property_amenity_highlights', ['id' => $amenityHighlightId]);
    }

    public function test_sleep_cards_reorder_sets_sorting_by_array_position(): void
    {
        $pid = $this->propertyIds[0];
        $s1 = DB::table('property_sleep_cards')->insertGetId([
            'property_id' => $pid, 'title' => 'Bed1', 'text' => 'one', 'sorting' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $s2 = DB::table('property_sleep_cards')->insertGetId([
            'property_id' => $pid, 'title' => 'Bed2', 'text' => 'two', 'sorting' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->actingAs($this->actor())
            ->postJson("/admin/properties/{$pid}/sleep-cards/reorder", ['ids' => [$s2, $s1]]);
        $resp->assertOk()->assertJson(['ok' => true, 'reordered' => 2]);

        $this->assertSame(0, (int) DB::table('property_sleep_cards')->where('id', $s2)->value('sorting'));
        $this->assertSame(1, (int) DB::table('property_sleep_cards')->where('id', $s1)->value('sorting'));
    }

    public function test_amenity_highlights_reorder_sets_sorting_by_array_position(): void
    {
        $pid = $this->propertyIds[0];
        $a1 = DB::table('property_amenity_highlights')->insertGetId([
            'property_id' => $pid, 'emoji' => '🏖️', 'label' => 'Beach', 'sorting' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $a2 = DB::table('property_amenity_highlights')->insertGetId([
            'property_id' => $pid, 'emoji' => '🛁', 'label' => 'Hot tub', 'sorting' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $a3 = DB::table('property_amenity_highlights')->insertGetId([
            'property_id' => $pid, 'emoji' => '🐶', 'label' => 'Pet OK', 'sorting' => 2, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $resp = $this->actingAs($this->actor())
            ->postJson("/admin/properties/{$pid}/amenity-highlights/reorder", ['ids' => [$a2, $a3, $a1]]);
        $resp->assertOk()->assertJson(['ok' => true, 'reordered' => 3]);

        $this->assertSame(0, (int) DB::table('property_amenity_highlights')->where('id', $a2)->value('sorting'));
        $this->assertSame(1, (int) DB::table('property_amenity_highlights')->where('id', $a3)->value('sorting'));
        $this->assertSame(2, (int) DB::table('property_amenity_highlights')->where('id', $a1)->value('sorting'));
    }
}
