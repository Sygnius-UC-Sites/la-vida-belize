<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InlineTaxonomyTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_test_p11_user_',
            'email'      => '_test_p11_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('locations')->where('name', 'like', '_p11_%')->orWhere('seo_url', 'like', '_p11_%')->delete();
        DB::table('attraction_categories')->where('name', 'like', '_p11_%')->orWhere('seo_url', 'like', '_p11_%')->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    private function actor(): User
    {
        return User::findOrFail($this->userId);
    }

    public function test_quick_create_location_returns_id_and_creates_row(): void
    {
        $resp = $this->actingAs($this->actor())
            ->postJson('/admin/locations/quick-create', [
                'name'    => '_p11_loc_basic_',
                'seo_url' => '_p11-loc-basic-' . uniqid(),
            ]);

        $resp->assertOk();
        $resp->assertJsonStructure(['id', 'name', 'image']);
        $id = $resp->json('id');
        $this->assertNotNull($id);
        $row = DB::table('locations')->where('id', $id)->first();
        $this->assertNotNull($row);
        $this->assertSame('_p11_loc_basic_', $row->name);
        $this->assertSame('common', $row->templete);
        $this->assertSame('published', $row->publish);
    }

    public function test_quick_create_location_auto_generates_slug_when_empty(): void
    {
        $resp = $this->actingAs($this->actor())
            ->postJson('/admin/locations/quick-create', [
                'name'    => '_p11_Manuel Antonio_',
                'seo_url' => '',
            ]);

        $resp->assertOk();
        $row = DB::table('locations')->where('id', $resp->json('id'))->first();
        $this->assertNotNull($row);
        // Str::slug strips underscores too — so "_p11_Manuel Antonio_" → "p11_manuel-antonio" (Laravel)
        // Just assert the slug is non-empty + lowercase + matches the regex
        $this->assertNotEmpty($row->seo_url);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9-_]+$/', $row->seo_url);
        $this->assertStringContainsString('manuel-antonio', $row->seo_url);
    }

    public function test_quick_create_attraction_category_uploads_image(): void
    {
        $file = UploadedFile::fake()->image('p11-cat.jpg', 200, 100);

        $resp = $this->actingAs($this->actor())
            ->postJson('/admin/attraction-categories/quick-create', [
                'name'    => '_p11_cat_with_image_',
                'seo_url' => '_p11-cat-img-' . uniqid(),
                'image'   => $file,
            ]);

        $resp->assertOk();
        $resp->assertJsonStructure(['id', 'name', 'image']);
        $this->assertNotNull($resp->json('image'), 'image URL should be set when an image was uploaded');
        $row = DB::table('attraction_categories')->where('id', $resp->json('id'))->first();
        $this->assertNotNull($row);
        $this->assertNotEmpty($row->image, 'DB image column should be populated');
        // Image path should land under the attraction-categories upload dir
        $this->assertStringContainsString('attraction-categories', (string) $row->image);
    }

    public function test_quick_create_returns_422_on_duplicate_slug(): void
    {
        $slug = '_p11-dup-' . uniqid();

        // First create succeeds
        $this->actingAs($this->actor())
            ->postJson('/admin/locations/quick-create', [
                'name'    => '_p11_first_',
                'seo_url' => $slug,
            ])->assertOk();

        // Second with same slug should 422
        $resp = $this->actingAs($this->actor())
            ->postJson('/admin/locations/quick-create', [
                'name'    => '_p11_second_',
                'seo_url' => $slug,
            ]);
        $resp->assertStatus(422);
        $errors = $resp->json('errors');
        $this->assertArrayHasKey('seo_url', $errors);
    }
}
