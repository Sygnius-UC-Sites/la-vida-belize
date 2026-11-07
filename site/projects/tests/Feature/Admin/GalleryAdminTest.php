<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GalleryAdminTest extends TestCase
{
    private int $propertyId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_gallery_property_',
            'seo_url'                => '_test-gallery-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'true',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_test_gallery_user_',
            'email'      => '_test_gallery_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('property_galleries')->where('property_id', $this->propertyId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    public function test_set_cover_persists_feature_image_to_gallery_image_path(): void
    {
        $imageId = DB::table('property_galleries')->insertGetId([
            'property_id' => $this->propertyId,
            'image'       => '/uploads/gallery/test.jpg',
            'sorting'     => 0,
            'status'      => 'true',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/gallery/' . $imageId . '/set-cover');
        $response->assertRedirect();

        $featureImage = DB::table('properties')->where('id', $this->propertyId)->value('feature_image');
        $this->assertSame('/uploads/gallery/test.jpg', $featureImage);
    }

    public function test_gallery_bulk_delete_removes_only_selected_with_cross_property_isolation(): void
    {
        $myIds = [];
        foreach (['/a.jpg', '/b.jpg', '/c.jpg'] as $i => $path) {
            $myIds[] = DB::table('property_galleries')->insertGetId([
                'property_id' => $this->propertyId,
                'image'       => $path,
                'sorting'     => $i, 'status' => 'true',
                'created_at'  => now(), 'updated_at' => now(),
            ]);
        }
        $otherProp = DB::table('properties')->insertGetId([
            'name' => '_test_phase5_other_', 'seo_url' => '_test-phase5-other-' . uniqid(),
            'instant_booking_button' => '', 'status' => 'true',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $foreignId = DB::table('property_galleries')->insertGetId([
            'property_id' => $otherProp,
            'image'       => '/foreign.jpg',
            'sorting'     => 0, 'status' => 'true',
            'created_at'  => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/gallery/bulk-delete', [
                'ids' => [$myIds[0], $myIds[2], $foreignId],
            ]);
        $response->assertRedirect();

        $this->assertNull(DB::table('property_galleries')->where('id', $myIds[0])->first());
        $this->assertNotNull(DB::table('property_galleries')->where('id', $myIds[1])->first());
        $this->assertNull(DB::table('property_galleries')->where('id', $myIds[2])->first());
        $this->assertNotNull(DB::table('property_galleries')->where('id', $foreignId)->first(),
            'Cross-property isolation: foreign photo must not be deleted');

        DB::table('property_galleries')->where('id', $foreignId)->delete();
        DB::table('properties')->where('id', $otherProp)->delete();
    }

    public function test_gallery_bulk_update_category_persists(): void
    {
        $ids = [];
        foreach (['/x.jpg', '/y.jpg', '/z.jpg'] as $i => $path) {
            $ids[] = DB::table('property_galleries')->insertGetId([
                'property_id' => $this->propertyId,
                'image'       => $path,
                'category'    => null,
                'sorting'     => $i, 'status' => 'true',
                'created_at'  => now(), 'updated_at' => now(),
            ]);
        }

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/gallery/bulk-update-category', [
                'ids' => $ids, 'category' => 'bedroom',
            ]);
        $response->assertRedirect();

        foreach ($ids as $id) {
            $this->assertSame('bedroom', DB::table('property_galleries')->where('id', $id)->value('category'));
        }

        // Invalid category → no change + danger flash
        $response = $this->actingAs(User::findOrFail($this->userId))
            ->post('/admin/properties/' . $this->propertyId . '/gallery/bulk-update-category', [
                'ids' => $ids, 'category' => 'not_a_real_category',
            ]);
        $response->assertRedirect();
        $response->assertSessionHas('danger');

        foreach ($ids as $id) {
            $this->assertSame('bedroom', DB::table('property_galleries')->where('id', $id)->value('category'),
                'Invalid category should not overwrite existing value');
        }
    }

    public function test_gallery_update_one_persists_caption_and_category(): void
    {
        $imageId = DB::table('property_galleries')->insertGetId([
            'property_id' => $this->propertyId,
            'image'       => '/single.jpg',
            'sorting'     => 5, 'status' => 'true',
            'created_at'  => now(), 'updated_at'  => now(),
        ]);

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->putJson('/admin/properties/' . $this->propertyId . '/gallery/' . $imageId, [
                'caption'  => 'Master suite',
                'category' => 'bedroom',
            ]);
        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $row = DB::table('property_galleries')->where('id', $imageId)->first();
        $this->assertSame('Master suite', $row->caption);
        $this->assertSame('bedroom', $row->category);
        $this->assertSame(5, (int)$row->sorting, 'Sorting should not be touched when not in payload');
    }

    public function test_gallery_update_one_rejects_cross_property_access(): void
    {
        $otherProp = DB::table('properties')->insertGetId([
            'name' => '_test_phase5_other2_', 'seo_url' => '_test-phase5-other2-' . uniqid(),
            'instant_booking_button' => '', 'status' => 'true',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $foreignId = DB::table('property_galleries')->insertGetId([
            'property_id' => $otherProp,
            'image'       => '/foreign.jpg',
            'sorting'     => 0, 'status' => 'true',
            'created_at'  => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs(User::findOrFail($this->userId))
            ->putJson('/admin/properties/' . $this->propertyId . '/gallery/' . $foreignId, [
                'caption' => 'Should not save',
            ]);
        $response->assertNotFound();

        $this->assertNotSame('Should not save', DB::table('property_galleries')->where('id', $foreignId)->value('caption'));

        DB::table('property_galleries')->where('id', $foreignId)->delete();
        DB::table('properties')->where('id', $otherProp)->delete();
    }

    public function test_gallery_manager_uses_dense_grid_and_opaque_clickable_modal(): void
    {
        $view = file_get_contents(resource_path('views/admin/properties/sub/gallery-images.blade.php'));

        $this->assertStringContainsString('property-gallery-manager', $view);
        $this->assertStringContainsString('grid-template-columns: repeat(auto-fill, minmax(132px, 1fr))', $view);
        $this->assertStringContainsString('#photo-modal', $view);
        $this->assertStringContainsString('background: rgba(15, 23, 42, 0.58)', $view);
        $this->assertStringContainsString('z-index: 1110 !important', $view);
        $this->assertStringContainsString('pointer-events: auto', $view);
        $this->assertStringContainsString('#photo-modal .modal-content', $view);
        $this->assertStringContainsString('background: #fff', $view);
    }
}
