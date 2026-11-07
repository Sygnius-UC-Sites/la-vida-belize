<?php

namespace Tests\Feature;

use App\Models\Pms\PmsProperty;
use App\Modules\VacationRental\Pms\PmsAdapter;
use App\Modules\VacationRental\Pms\PmsManager;
use App\Services\PmsPropertySyncService;
use Mockery;
use Tests\TestCase;

class PmsPropertySyncEncodingTest extends TestCase
{
    public function test_meta_description_truncation_preserves_valid_utf8(): void
    {
        $description = str_repeat('A', 159) . '’ smart apostrophe at the boundary';

        $adapter = Mockery::mock(PmsAdapter::class);
        $adapter->shouldReceive('fetchAndSaveRawData')->once()->with('encoding-property');
        $adapter->shouldReceive('getFrontendData')->once()->with('encoding-property')->andReturn([
            'title' => 'Encoding Boundary Listing',
            'room_type_id' => 'encoding-room',
            'description' => $description,
            'guests' => 2,
            'bedrooms' => 1,
            'bathrooms' => 1,
            'images' => [],
            'amenities' => [],
            'address' => [],
            'pricing' => ['basePrice' => 100, 'currency' => 'USD'],
        ]);

        $manager = Mockery::mock(PmsManager::class);
        $manager->shouldReceive('driver')->once()->with('lodgify')->andReturn($adapter);

        $result = (new PmsPropertySyncService($manager))->sync('lodgify', 'encoding-property');

        $this->assertTrue($result['success'], $result['message']);

        $metaDescription = PmsProperty::where('pms_source', 'lodgify')
            ->where('pms_property_id', 'encoding-property')
            ->value('meta_description');

        $this->assertNotNull($metaDescription);
        $this->assertTrue(mb_check_encoding($metaDescription, 'UTF-8'));
        $this->assertLessThanOrEqual(160, mb_strlen($metaDescription));
    }
}
