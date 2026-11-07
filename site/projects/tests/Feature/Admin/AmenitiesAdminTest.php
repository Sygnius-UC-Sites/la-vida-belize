<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AmenitiesAdminTest extends TestCase
{
    private int $propertyId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_amenities_property_',
            'seo_url'                => '_test-amenities-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'true',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_test_amenities_user_',
            'email'      => '_test_amenities_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        $groupIds = DB::table('property_amenity_groups')->where('property_id', $this->propertyId)->pluck('id');
        DB::table('property_amenities')->whereIn('property_amenity_id', $groupIds)->delete();
        DB::table('property_amenity_groups')->where('property_id', $this->propertyId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_add_group_inline_creates_property_amenity_group(): void
    {
        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/group-amenities/create', [
                'property_id' => $this->propertyId,
                'name'        => 'Bathroom',
                'status'      => 'true',
                'sorting'     => 0,
            ]);
        $response->assertRedirect();

        $row = DB::table('property_amenity_groups')
            ->where('property_id', $this->propertyId)
            ->where('name', 'Bathroom')
            ->first();
        $this->assertNotNull($row);
    }

    public function test_add_amenity_inline_under_group_redirects_to_property_edit(): void
    {
        $groupId = DB::table('property_amenity_groups')->insertGetId([
            'property_id' => $this->propertyId,
            'name'        => 'Tech',
            'status'      => 'true',
            'sorting'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/group-amenities/' . $groupId . '/amenities/store', [
                'property_amenity_id' => $groupId,
                'name'                => 'Wi-Fi',
                'status'              => 'true',
                'sorting'             => 0,
            ]);
        $response->assertRedirect();

        // Phase 4B: redirect should go to /admin/properties/{id}/edit, NOT to the orphan list page.
        $this->assertStringContainsString('/admin/properties/' . $this->propertyId . '/edit', $response->headers->get('Location'));

        $row = DB::table('property_amenities')
            ->where('property_amenity_id', $groupId)
            ->where('name', 'Wi-Fi')
            ->first();
        $this->assertNotNull($row);
    }

    public function test_sort_group_up_swaps_with_neighbour(): void
    {
        $firstId  = DB::table('property_amenity_groups')->insertGetId([
            'property_id' => $this->propertyId,
            'name'        => 'First',
            'status'      => 'true',
            'sorting'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        $secondId = DB::table('property_amenity_groups')->insertGetId([
            'property_id' => $this->propertyId,
            'name'        => 'Second',
            'status'      => 'true',
            'sorting'     => 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/group-amenities/' . $secondId . '/sort', [
                'direction' => 'up',
            ]);
        $response->assertRedirect();

        $this->assertSame(0, (int) DB::table('property_amenity_groups')->where('id', $secondId)->value('sorting'));
        $this->assertSame(1, (int) DB::table('property_amenity_groups')->where('id', $firstId)->value('sorting'));
    }

    public function test_amenity_store_persists_icon(): void
    {
        $groupId = DB::table('property_amenity_groups')->insertGetId([
            'property_id' => $this->propertyId,
            'name'        => 'Tech',
            'status'      => 'true',
            'sorting'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/group-amenities/' . $groupId . '/amenities/store', [
                'property_amenity_id' => $groupId,
                'name'                => 'Wi-Fi',
                'icon'                => 'wifi',
                'status'              => 'true',
                'sorting'             => 0,
            ]);
        $response->assertRedirect();

        $row = DB::table('property_amenities')
            ->where('property_amenity_id', $groupId)
            ->where('name', 'Wi-Fi')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('wifi', $row->icon);
    }

    public function test_sync_library_creates_groups_and_amenities(): void
    {
        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/amenities/sync-library', [
                'library_amenities' => [
                    'Bathroom::Bathtub',
                    'Internet & office::Wi-Fi',
                ],
            ]);
        $response->assertRedirect(route('properties.edit', $this->propertyId) . '#amenities');

        $bathroom = DB::table('property_amenity_groups')
            ->where('property_id', $this->propertyId)
            ->where('name', 'Bathroom')
            ->first();
        $this->assertNotNull($bathroom);

        $internet = DB::table('property_amenity_groups')
            ->where('property_id', $this->propertyId)
            ->where('name', 'Internet & office')
            ->first();
        $this->assertNotNull($internet);

        $bathtub = DB::table('property_amenities')
            ->where('property_amenity_id', $bathroom->id)
            ->where('name', 'Bathtub')
            ->first();
        $this->assertNotNull($bathtub);
        $this->assertSame('bath', $bathtub->icon);

        $wifi = DB::table('property_amenities')
            ->where('property_amenity_id', $internet->id)
            ->where('name', 'Wi-Fi')
            ->first();
        $this->assertNotNull($wifi);
        $this->assertSame('wifi', $wifi->icon);
    }

    public function test_sync_library_unticks_remove_amenities_but_preserve_custom(): void
    {
        $groupId = DB::table('property_amenity_groups')->insertGetId([
            'property_id' => $this->propertyId,
            'name'        => 'Bathroom',
            'status'      => 'true',
            'sorting'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        DB::table('property_amenities')->insert([
            ['property_amenity_id' => $groupId, 'name' => 'Bathtub', 'icon' => 'bath',
             'status' => 'true', 'sorting' => 0, 'created_at' => now(), 'updated_at' => now()],
            ['property_amenity_id' => $groupId, 'name' => 'Custom thing', 'icon' => null,
             'status' => 'true', 'sorting' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/amenities/sync-library', []);
        $response->assertRedirect(route('properties.edit', $this->propertyId) . '#amenities');

        $this->assertNull(DB::table('property_amenities')
            ->where('property_amenity_id', $groupId)
            ->where('name', 'Bathtub')
            ->first());
        $this->assertNotNull(DB::table('property_amenities')
            ->where('property_amenity_id', $groupId)
            ->where('name', 'Custom thing')
            ->first());
    }

    public function test_sync_library_does_not_duplicate_existing_picks(): void
    {
        $groupId = DB::table('property_amenity_groups')->insertGetId([
            'property_id' => $this->propertyId,
            'name'        => 'Bathroom',
            'status'      => 'true',
            'sorting'     => 0,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        DB::table('property_amenities')->insert([
            'property_amenity_id' => $groupId,
            'name'                => 'Bathtub',
            'icon'                => 'bath',
            'status'              => 'true',
            'sorting'             => 0,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/amenities/sync-library', [
                'library_amenities' => ['Bathroom::Bathtub'],
            ]);
        $response->assertRedirect(route('properties.edit', $this->propertyId) . '#amenities');

        $count = DB::table('property_amenities')
            ->where('property_amenity_id', $groupId)
            ->where('name', 'Bathtub')
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_amenity_library_has_at_least_100_entries(): void
    {
        $library = \Helper::getAmenityLibrary();
        $totalEntries = collect($library)->flatten(1)->count();
        $this->assertGreaterThanOrEqual(100, $totalEntries, 'Library should have at least 100 amenity entries');
        $this->assertGreaterThanOrEqual(12, count($library), 'Library should have at least 12 categories');
    }

    public function test_custom_amenities_batch_add_creates_rows_in_chosen_categories(): void
    {
        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/amenities/custom-add', [
                'name'            => ['Tennis court', 'Sauna', 'Fairy door'],
                'icon'            => ['star', 'temperature-high', ''],
                'category'        => ['Outdoor', 'Outdoor', '__custom__'],
                'custom_category' => ['', '', 'Whimsical'],
            ]);
        $response->assertRedirect(route('properties.edit', $this->propertyId) . '#amenities');

        $outdoorGroup = DB::table('property_amenity_groups')
            ->where('property_id', $this->propertyId)
            ->where('name', 'Outdoor')->first();
        $this->assertNotNull($outdoorGroup);
        $this->assertSame(2, DB::table('property_amenities')
            ->where('property_amenity_id', $outdoorGroup->id)->count());

        $whimsicalGroup = DB::table('property_amenity_groups')
            ->where('property_id', $this->propertyId)
            ->where('name', 'Whimsical')->first();
        $this->assertNotNull($whimsicalGroup);
        $fairy = DB::table('property_amenities')
            ->where('property_amenity_id', $whimsicalGroup->id)
            ->where('name', 'Fairy door')->first();
        $this->assertNotNull($fairy);
        $this->assertNull($fairy->icon, 'Fairy door has no icon (passed empty string)');
    }

    public function test_custom_amenities_batch_delete_removes_only_selected_with_cross_property_isolation(): void
    {
        $myGroupId = DB::table('property_amenity_groups')->insertGetId([
            'property_id' => $this->propertyId, 'name' => 'Bathroom',
            'status' => 'true', 'sorting' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $myIds = [];
        foreach (['First', 'Second', 'Third'] as $i => $n) {
            $myIds[] = DB::table('property_amenities')->insertGetId([
                'property_amenity_id' => $myGroupId, 'name' => $n, 'icon' => null,
                'status' => 'true', 'sorting' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $otherProp = DB::table('properties')->insertGetId([
            'name' => '_test_phase4e_other_', 'seo_url' => '_test-phase4e-other-' . uniqid(),
            'instant_booking_button' => '', 'status' => 'true',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherGroupId = DB::table('property_amenity_groups')->insertGetId([
            'property_id' => $otherProp, 'name' => 'Bathroom',
            'status' => 'true', 'sorting' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherAmenityId = DB::table('property_amenities')->insertGetId([
            'property_amenity_id' => $otherGroupId, 'name' => 'Foreign', 'icon' => null,
            'status' => 'true', 'sorting' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/amenities/custom-delete', [
                'ids' => [$myIds[0], $myIds[2], $otherAmenityId],
            ]);
        $response->assertRedirect(route('properties.edit', $this->propertyId) . '#amenities');

        $this->assertNull(DB::table('property_amenities')->where('id', $myIds[0])->first());
        $this->assertNotNull(DB::table('property_amenities')->where('id', $myIds[1])->first());
        $this->assertNull(DB::table('property_amenities')->where('id', $myIds[2])->first());
        $this->assertNotNull(DB::table('property_amenities')->where('id', $otherAmenityId)->first(),
            'Cross-property isolation: foreign amenity must not be deleted');

        DB::table('property_amenities')->where('id', $otherAmenityId)->delete();
        DB::table('property_amenity_groups')->where('id', $otherGroupId)->delete();
        DB::table('properties')->where('id', $otherProp)->delete();
    }

    public function test_sync_library_persists_card_highlights_with_isolation(): void
    {
        // Pre-seed a Bathroom group + 1 amenity that we'll mark as a highlight via the sync form.
        $groupId = DB::table('property_amenity_groups')->insertGetId([
            'property_id' => $this->propertyId, 'name' => 'Bathroom',
            'status' => 'true', 'sorting' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $amenityId = DB::table('property_amenities')->insertGetId([
            'property_amenity_id' => $groupId, 'name' => 'Bathtub', 'icon' => 'bath',
            'status' => 'true', 'sorting' => 0, 'is_card_highlight' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Pre-seed a SECOND property + its own group + amenity (to test isolation).
        $otherProp = DB::table('properties')->insertGetId([
            'name' => '_test_phase4f1_other_', 'seo_url' => '_test-phase4f1-other-' . uniqid(),
            'instant_booking_button' => '', 'status' => 'true',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherGroupId = DB::table('property_amenity_groups')->insertGetId([
            'property_id' => $otherProp, 'name' => 'Bathroom',
            'status' => 'true', 'sorting' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherAmenityId = DB::table('property_amenities')->insertGetId([
            'property_amenity_id' => $otherGroupId, 'name' => 'Foreign', 'icon' => null,
            'status' => 'true', 'sorting' => 0, 'is_card_highlight' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // POST sync-library with library tick + card_highlights[] including BOTH our amenity AND the foreign one.
        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/amenities/sync-library', [
                'library_amenities' => ['Bathroom::Bathtub'],
                'card_highlights'   => [$amenityId, $otherAmenityId],
            ]);
        $response->assertRedirect(route('properties.edit', $this->propertyId) . '#amenities');

        // Our amenity flagged
        $this->assertSame(1, (int) DB::table('property_amenities')->where('id', $amenityId)->value('is_card_highlight'));
        // Foreign amenity untouched (still 1 because we never wrote to its property)
        $this->assertSame(1, (int) DB::table('property_amenities')->where('id', $otherAmenityId)->value('is_card_highlight'),
            'Cross-property isolation: foreign amenity highlight state must not change');

        // Now POST again WITHOUT the highlight in card_highlights[] → should unset our amenity's flag (without affecting foreign).
        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/amenities/sync-library', [
                'library_amenities' => ['Bathroom::Bathtub'],
                'card_highlights'   => [],
            ]);
        $response->assertRedirect(route('properties.edit', $this->propertyId) . '#amenities');
        $this->assertSame(0, (int) DB::table('property_amenities')->where('id', $amenityId)->value('is_card_highlight'));
        $this->assertSame(1, (int) DB::table('property_amenities')->where('id', $otherAmenityId)->value('is_card_highlight'));

        // Cleanup foreign rows
        DB::table('property_amenities')->where('id', $otherAmenityId)->delete();
        DB::table('property_amenity_groups')->where('id', $otherGroupId)->delete();
        DB::table('properties')->where('id', $otherProp)->delete();
    }

    public function test_card_highlights_query_returns_only_flagged_active_rows(): void
    {
        $groupId = DB::table('property_amenity_groups')->insertGetId([
            'property_id' => $this->propertyId, 'name' => 'Test',
            'status' => 'true', 'sorting' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $rowSpecs = [
            ['name' => 'A', 'is_card_highlight' => 1, 'status' => 'true',  'sorting' => 0],
            ['name' => 'B', 'is_card_highlight' => 1, 'status' => 'true',  'sorting' => 1],
            ['name' => 'C', 'is_card_highlight' => 0, 'status' => 'true',  'sorting' => 2],
            ['name' => 'D', 'is_card_highlight' => 1, 'status' => 'false', 'sorting' => 3],
            ['name' => 'E', 'is_card_highlight' => 1, 'status' => 'true',  'sorting' => 4],
        ];
        foreach ($rowSpecs as $spec) {
            DB::table('property_amenities')->insert(array_merge($spec, [
                'property_amenity_id' => $groupId,
                'icon' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]));
        }

        $highlights = \App\Models\PropertyAmenity::whereIn('property_amenity_id', [$groupId])
            ->where('is_card_highlight', 1)
            ->where('status', 'true')
            ->orderBy('sorting')->orderBy('id')
            ->take(6)
            ->get();

        $this->assertSame(3, $highlights->count());
        $this->assertSame(['A', 'B', 'E'], $highlights->pluck('name')->all());
    }

    public function test_backfill_card_highlights_matches_case_insensitive_names(): void
    {
        DB::table('properties')->where('id', $this->propertyId)
            ->update(['card_amenities' => json_encode(['Wi-Fi', 'POOL', 'Made-Up'])]);

        $groupId = DB::table('property_amenity_groups')->insertGetId([
            'property_id' => $this->propertyId, 'name' => 'Misc',
            'status' => 'true', 'sorting' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $wifiId = DB::table('property_amenities')->insertGetId([
            'property_amenity_id' => $groupId, 'name' => 'Wi-Fi', 'icon' => 'wifi',
            'status' => 'true', 'sorting' => 0, 'is_card_highlight' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $poolId = DB::table('property_amenities')->insertGetId([
            'property_amenity_id' => $groupId, 'name' => 'Pool', 'icon' => 'swimming-pool',
            'status' => 'true', 'sorting' => 1, 'is_card_highlight' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $saunaId = DB::table('property_amenities')->insertGetId([
            'property_amenity_id' => $groupId, 'name' => 'Sauna', 'icon' => null,
            'status' => 'true', 'sorting' => 2, 'is_card_highlight' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $matches = \Helper::backfillCardHighlightsForProperty($this->propertyId);

        $this->assertSame(2, $matches);
        $this->assertSame(1, (int) DB::table('property_amenities')->where('id', $wifiId)->value('is_card_highlight'));
        $this->assertSame(1, (int) DB::table('property_amenities')->where('id', $poolId)->value('is_card_highlight'));
        $this->assertSame(0, (int) DB::table('property_amenities')->where('id', $saunaId)->value('is_card_highlight'),
            'Sauna was not in card_amenities, must remain unflagged');
    }
}
