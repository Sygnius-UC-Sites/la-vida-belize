<?php

namespace Tests\Feature;

use App\Models\Pms\PmsRoom;
use App\Models\User;
use App\Modules\VacationRental\Pms\PmsAdapter;
use App\Modules\VacationRental\Pms\PmsManager;
use App\Services\PmsPropertySyncService;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PmsPublicationSafetyTest extends TestCase
{
    public function test_release_baseline_contains_no_casa_magica_listing_data(): void
    {
        $this->assertDatabaseMissing('properties', ['seo_url' => 'casa-magica-744891']);
        $this->assertDatabaseMissing('pms_rooms', ['pms_source' => 'lodgify', 'name' => 'Legacy Vacation Rentals']);
        $this->assertDatabaseMissing('pms_rooms', ['pms_source' => 'lodgify', 'pms_room_id' => '744891']);
        $this->assertDatabaseMissing('pms_properties', ['pms_source' => 'lodgify', 'pms_property_id' => '677915']);
        $this->assertDatabaseMissing('pms_lodgify_data', ['pms_property_id' => '677915']);
        $this->assertDatabaseMissing('pms_unavailable_dates', ['property_id' => '156']);

        $this->assertGreaterThanOrEqual(1, DB::table('properties')
            ->where('status', 'true')
            ->count(), 'Release baseline should seed at least one active non-legacy property.');
    }

    public function test_synced_listing_stays_hidden_until_admin_links_and_publishes_it(): void
    {
        $adapter = Mockery::mock(PmsAdapter::class);
        $adapter->shouldReceive('fetchAndSaveRawData')->once()->with('external-123');
        $adapter->shouldReceive('getFrontendData')->once()->with('external-123')->andReturn([
            'title' => 'Unmatched Provider Listing',
            'room_type_id' => 'room-123',
            'description' => 'A provider listing that must remain private until linked.',
            'guests' => 4,
            'bedrooms' => 2,
            'bathrooms' => 1,
            'images' => ['https://images.example.test/listing.jpg'],
            'amenities' => ['Wifi'],
            'address' => ['city' => 'Your Destination', 'state' => 'AZ', 'country' => 'US'],
            'pricing' => ['basePrice' => 250, 'currency' => 'USD'],
        ]);

        $manager = Mockery::mock(PmsManager::class);
        $manager->shouldReceive('driver')->once()->with('lodgify')->andReturn($adapter);

        $result = (new PmsPropertySyncService($manager))->sync('lodgify', 'external-123');
        $this->assertTrue($result['success']);

        $room = PmsRoom::where('pms_source', 'lodgify')
            ->where('pms_room_id', 'room-123')
            ->firstOrFail();

        $this->assertSame('false', $room->status);
        $this->assertSame(0, (int) $room->active);
        $this->assertSame(
            'false',
            DB::table('pms_properties')
                ->where('pms_source', 'lodgify')
                ->where('pms_property_id', 'external-123')
                ->value('status')
        );
        $this->assertFalse(DB::table('properties')->where('seo_url', $room->seo_url)->exists());

        $this->get('/stay/' . $room->seo_url)->assertNotFound();
        $this->get('/' . $room->seo_url)->assertNotFound();
        $this->assertFalse(DB::table('properties')->where('seo_url', $room->seo_url)->exists());

        $admin = User::query()->firstOrFail();
        $this->actingAs($admin)
            ->from('/admin/properties')
            ->post(route('admin.pms_rooms.publish', $room->id), ['show_home' => '1'])
            ->assertRedirect('/admin/properties');

        $property = DB::table('properties')->where('seo_url', $room->seo_url)->first();
        $this->assertNotNull($property);
        $this->assertSame('true', $property->status);
        $this->assertSame('true', $property->is_home);
        $this->assertSame('lodgify', $property->pms_source);
        $this->assertSame($room->id, (int) $property->pms_room_id);

        $room->refresh();
        $this->assertSame('true', $room->status);
        $this->assertSame(1, (int) $room->active);
        if (\Illuminate\Support\Facades\Schema::hasTable('pms_property_links')) {
            $this->assertDatabaseHas('pms_property_links', [
                'cms_property_id' => $property->id,
                'provider_slug' => 'lodgify',
                'external_id' => (string) $room->pms_room_id,
                'last_sync_status' => 'linked',
            ]);
        }

        $this->get('/' . $room->seo_url)
            ->assertStatus(301)
            ->assertRedirect('/stay/' . $room->seo_url);
        $html = $this->get('/stay/' . $room->seo_url)->assertOk()->getContent();
        $this->assertStringContainsString('window.PMS_WIDGET', $html);
        $this->assertStringContainsString('"roomId":' . $room->id, $html);
        $this->assertStringContainsString('/js/pms-widget.js', $html);
        $this->assertStringContainsString('const pmsConfig = window.PMS_WIDGET', $html);
    }

    public function test_resync_preserves_an_explicitly_published_room(): void
    {
        DB::table('pms_properties')->insert([
            'pms_property_id' => 'external-456',
            'pms_source' => 'hostaway',
            'name' => 'Published Listing',
            'status' => 'true',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('pms_rooms')->insert([
            'pms_property_id' => 'external-456',
            'pms_room_id' => 'room-456',
            'pms_source' => 'hostaway',
            'name' => 'Published Listing',
            'seo_url' => 'published-listing',
            'status' => 'true',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $adapter = Mockery::mock(PmsAdapter::class);
        $adapter->shouldReceive('fetchAndSaveRawData')->once()->with('external-456');
        $adapter->shouldReceive('getFrontendData')->once()->with('external-456')->andReturn([
            'title' => 'Provider Renamed Listing',
            'room_type_id' => 'room-456',
            'description' => 'Updated provider content.',
            'guests' => 6,
            'bedrooms' => 3,
            'bathrooms' => 2,
            'images' => [],
            'amenities' => [],
            'pricing' => ['basePrice' => 300, 'currency' => 'USD'],
        ]);

        $manager = Mockery::mock(PmsManager::class);
        $manager->shouldReceive('driver')->once()->with('hostaway')->andReturn($adapter);

        $result = (new PmsPropertySyncService($manager))->sync('hostaway', 'external-456');
        $this->assertTrue($result['success']);

        $room = DB::table('pms_rooms')
            ->where('pms_source', 'hostaway')
            ->where('pms_room_id', 'room-456')
            ->first();
        $this->assertSame('true', $room->status);
        $this->assertSame(1, (int) $room->active);
        $this->assertSame('published-listing', $room->seo_url);
        $this->assertSame(
            'true',
            DB::table('pms_properties')
                ->where('pms_source', 'hostaway')
                ->where('pms_property_id', 'external-456')
                ->value('status')
        );
    }
}
