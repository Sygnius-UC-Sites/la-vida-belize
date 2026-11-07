<?php

namespace Tests\Feature\Visitor;

use App\Models\Property;
use App\Support\PropertyUrl;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AttractionsPageTest extends TestCase
{
    use WithoutMiddleware;

    private array $createdLocationIds = [];
    private array $createdCategoryIds = [];
    private array $createdAttractionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $now = now();

        // 2 locations
        $this->createdLocationIds[] = DB::table('locations')->insertGetId([
            'name' => '_test_p9_loc_a_', 'seo_url' => '_p9-loc-a-' . uniqid(),
            'templete' => 'common', 'publish' => 'published',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->createdLocationIds[] = DB::table('locations')->insertGetId([
            'name' => '_test_p9_loc_b_', 'seo_url' => '_p9-loc-b-' . uniqid(),
            'templete' => 'common', 'publish' => 'published',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // 2 categories
        $this->createdCategoryIds[] = DB::table('attraction_categories')->insertGetId([
            'name' => '_test_p9_cat_food_', 'seo_url' => '_p9-cat-food-' . uniqid(),
            'templete' => 'common', 'publish' => 'published',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->createdCategoryIds[] = DB::table('attraction_categories')->insertGetId([
            'name' => '_test_p9_cat_outdoor_', 'seo_url' => '_p9-cat-outdoor-' . uniqid(),
            'templete' => 'common', 'publish' => 'published',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        [$locA, $locB] = $this->createdLocationIds;
        [$catFood, $catOutdoor] = $this->createdCategoryIds;

        // 4 attractions covering the 2x2 matrix.
        $this->createdAttractionIds[] = DB::table('attractions')->insertGetId([
            'name' => '_test_p9_attr_food_a_', 'seo_url' => '_p9-attr-food-a-' . uniqid(),
            'templete' => 'common', 'publish' => 'published',
            'location_id' => $locA, 'category_id' => $catFood,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->createdAttractionIds[] = DB::table('attractions')->insertGetId([
            'name' => '_test_p9_attr_food_b_', 'seo_url' => '_p9-attr-food-b-' . uniqid(),
            'templete' => 'common', 'publish' => 'published',
            'location_id' => $locB, 'category_id' => $catFood,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->createdAttractionIds[] = DB::table('attractions')->insertGetId([
            'name' => '_test_p9_attr_outdoor_a_', 'seo_url' => '_p9-attr-outdoor-a-' . uniqid(),
            'templete' => 'common', 'publish' => 'published',
            'location_id' => $locA, 'category_id' => $catOutdoor,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->createdAttractionIds[] = DB::table('attractions')->insertGetId([
            'name' => '_test_p9_attr_linked_to_11_', 'seo_url' => '_p9-attr-linked-' . uniqid(),
            'templete' => 'common', 'publish' => 'published',
            'location_id' => $locA, 'category_id' => $catOutdoor,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('attractions')->whereIn('id', $this->createdAttractionIds)->delete();
        DB::table('attraction_categories')->whereIn('id', $this->createdCategoryIds)->delete();
        DB::table('locations')->whereIn('id', $this->createdLocationIds)->delete();
        parent::tearDown();
    }

    public function test_things_to_do_page_renders_with_published_attractions(): void
    {
        $resp = $this->get('/things-to-do');
        $resp->assertOk();
        // All 4 published attractions should appear in the page body
        $resp->assertSee('_test_p9_attr_food_a_');
        $resp->assertSee('_test_p9_attr_food_b_');
        $resp->assertSee('_test_p9_attr_outdoor_a_');
        $resp->assertSee('_test_p9_attr_linked_to_11_');
    }

    public function test_attraction_category_page_filters_to_matching_attractions(): void
    {
        $foodCatSlug = DB::table('attraction_categories')->where('id', $this->createdCategoryIds[0])->value('seo_url');
        $resp = $this->get('/attractions/category/' . $foodCatSlug);
        $resp->assertOk();
        // The 2 food attractions render, the outdoor ones should not appear in body text
        $resp->assertSee('_test_p9_attr_food_a_');
        $resp->assertSee('_test_p9_attr_food_b_');
        $resp->assertDontSee('_test_p9_attr_outdoor_a_');
    }

    public function test_property_detail_preserves_design_without_a_nearby_attractions_block(): void
    {
        $property = Property::query()->where('status', 'true')->firstOrFail();
        $url = PropertyUrl::path($property);
        $resp = $this->get($url);
        $resp->assertOk();
        $resp->assertDontSee('Nearby Attractions');
        $resp->assertDontSee('_test_p9_attr_linked_to_11_');
    }
}
