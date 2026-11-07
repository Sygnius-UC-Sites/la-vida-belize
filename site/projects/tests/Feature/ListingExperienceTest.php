<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\PropertyCategory;
use App\Models\Pms\PmsRoom;
use App\Models\User;
use App\Support\PropertyUrl;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ListingExperienceTest extends TestCase
{
    public function test_pms_calendar_is_the_search_and_datepicker_source_of_truth(): void
    {
        [$property, $room] = $this->createPmsProperty('calendar-source-test');
        $this->seedCalendar($room, '2031-04-10', '2031-04-13');

        $this->get('/stays?check_in=2031-04-10&check_out=2031-04-13')
            ->assertOk()
            ->assertSee('data-slug="' . $property->seo_url . '"', false);

        DB::table('pms_calendar')
            ->where('pms_source', $room->pms_source)
            ->where('pms_room_id', $room->pms_room_id)
            ->where('date', '2031-04-11')
            ->update(['state' => 0]);

        $this->get('/stays?check_in=2031-04-10&check_out=2031-04-13')
            ->assertOk()
            ->assertDontSee('data-slug="' . $property->seo_url . '"', false);

        $this->getJson('/pms-availability?room_id=' . $room->id
            . '&start_date=2031-04-10&end_date=2031-04-13')
            ->assertOk()
            ->assertJsonPath('source', 'synced_calendar')
            ->assertJsonFragment(['2031-04-11']);
    }

    public function test_categories_are_optional_and_filter_only_assigned_properties(): void
    {
        $category = PropertyCategory::create([
            'name' => 'Large Groups',
            'slug' => 'large-groups',
            'status' => true,
        ]);
        $assigned = $this->createLegacyProperty('assigned-category-test');
        $unassigned = $this->createLegacyProperty('unassigned-category-test');
        $assigned->categories()->attach($category);

        $this->get('/stays')
            ->assertOk()
            ->assertSee($assigned->seo_url)
            ->assertSee($unassigned->seo_url)
            ->assertSee('Large Groups');

        $this->get('/stays?category=large-groups')
            ->assertOk()
            ->assertSee('data-slug="' . $assigned->seo_url . '"', false)
            ->assertDontSee('data-slug="' . $unassigned->seo_url . '"', false);
    }

    public function test_property_slug_uses_stay_canonical_when_site_has_no_divisions(): void
    {
        $this->resetPropertyUrlCache();
        $property = $this->createLegacyProperty('canonical-property-test');

        $this->assertSame('/stay/' . $property->seo_url, PropertyUrl::path($property));

        $this->get('/stay/' . $property->seo_url)->assertOk();
        $this->get('/' . $property->seo_url)
            ->assertStatus(301)
            ->assertRedirect('/stay/' . $property->seo_url);
        $this->get('/condos/' . $property->seo_url)
            ->assertStatus(301)
            ->assertRedirect('/stay/' . $property->seo_url);
        $this->get('/houses/' . $property->seo_url)
            ->assertStatus(301)
            ->assertRedirect('/stay/' . $property->seo_url);
    }

    public function test_property_slug_uses_type_prefixes_only_when_site_has_multiple_divisions(): void
    {
        $this->resetPropertyUrlCache();
        $condo = $this->createLegacyProperty('canonical-condo-test', 'condo');
        $house = $this->createLegacyProperty('canonical-house-test', 'house');
        $general = $this->createLegacyProperty('canonical-general-test', 'property');

        $this->resetPropertyUrlCache();

        $this->assertSame('/condos/' . $condo->seo_url, PropertyUrl::path($condo));
        $this->assertSame('/houses/' . $house->seo_url, PropertyUrl::path($house));
        $this->assertSame('/stay/' . $general->seo_url, PropertyUrl::path($general));

        $this->get('/condos/' . $condo->seo_url)->assertOk();
        $this->get('/houses/' . $house->seo_url)->assertOk();
        $this->get('/stay/' . $general->seo_url)->assertOk();

        $this->get('/stay/' . $condo->seo_url . '?check_in=2031-05-01')
            ->assertStatus(301)
            ->assertRedirect('/condos/' . $condo->seo_url . '?check_in=2031-05-01');
    }

    public function test_admin_can_manage_categories(): void
    {
        $this->actingAs(User::query()->firstOrFail())
            ->post(route('property-categories.store'), [
                'name' => 'Beachfront',
                'slug' => 'beachfront',
                'status' => 1,
                'sort_order' => 2,
            ])
            ->assertRedirect(route('property-categories.index'));

        $this->assertDatabaseHas('property_categories', [
            'name' => 'Beachfront',
            'slug' => 'beachfront',
        ]);
    }

    public function test_mobile_quote_amounts_are_kept_in_a_separate_non_wrapping_column(): void
    {
        $quote = file_get_contents(resource_path('views/front/static/pms-get-quote.blade.php'));
        $fees = file_get_contents(resource_path('views/front/partials/pms-fee-groups-css.blade.php'));

        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr) auto', $quote);
        $this->assertStringContainsString('white-space: nowrap', $quote);
        $this->assertStringContainsString('font-variant-numeric: tabular-nums', $fees);
    }

    private function createLegacyProperty(string $slug, string $type = 'property'): Property
    {
        return Property::create([
            'name' => ucfirst(str_replace('-', ' ', $slug)),
            'heading' => 'Test property',
            'seo_url' => $slug,
            'instant_booking_button' => '',
            'status' => 'true',
            'type' => $type,
            'standard_rate' => 250,
            'min_stay' => 1,
            'sleeps' => 4,
            'no_of_guest' => 4,
        ]);
    }

    private function createPmsProperty(string $slug): array
    {
        $room = PmsRoom::create([
            'pms_property_id' => 'property-' . $slug,
            'pms_room_id' => 'room-' . $slug,
            'pms_source' => 'lodgify',
            'name' => 'PMS ' . $slug,
            'seo_url' => $slug,
            'status' => 'true',
            'active' => 1,
            'max_people' => 4,
            'currency' => 'USD',
            'pets_allowed' => 0,
        ]);
        $property = $this->createLegacyProperty($slug);
        $property->forceFill([
            'pms_source' => 'lodgify',
            'pms_room_id' => $room->id,
        ])->save();

        return [$property, $room];
    }

    private function seedCalendar(PmsRoom $room, string $from, string $to): void
    {
        $period = \Carbon\CarbonPeriod::create($from, $to);
        foreach ($period as $date) {
            DB::table('pms_calendar')->insert([
                'pms_source' => $room->pms_source,
                'pms_property_id' => $room->pms_property_id,
                'pms_room_id' => $room->pms_room_id,
                'date' => $date->format('Y-m-d'),
                'state' => 1,
                'inventory' => 1,
                'nightly_rate' => 275,
                'currency' => 'USD',
                'min_stay' => 2,
                'closed_to_arrival' => 0,
                'closed_to_departure' => 0,
                'stop_sell' => 0,
                'synced_at' => now(),
            ]);
        }
    }

    private function resetPropertyUrlCache(): void
    {
        $ref = new \ReflectionProperty(PropertyUrl::class, 'hasMultipleTypes');
        $ref->setAccessible(true);
        $ref->setValue(null, null);
    }
}
