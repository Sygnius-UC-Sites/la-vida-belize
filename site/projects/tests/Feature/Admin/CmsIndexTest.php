<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CmsIndexTest extends TestCase
{
    private int $userId;

    /** Track CMS rows the tests create so tearDown can sweep them up. */
    private array $createdSlugs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_test_p13a_user_',
            'email'      => '_test_p13a_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        // Sweep test rows + their auto-generated _copy_N children.
        foreach ($this->createdSlugs as $slug) {
            DB::table('cms')->where('seo_url', $slug)->delete();
            DB::table('cms')->where('seo_url', 'like', $slug . '_copy_%')->delete();
        }
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    private function actor(): User
    {
        return User::findOrFail($this->userId);
    }

    private function makeCmsRow(string $slug, string $name, string $template = 'common'): int
    {
        $this->createdSlugs[] = $slug;
        return DB::table('cms')->insertGetId([
            'name'       => $name,
            'seo_url'    => $slug,
            'templete'   => $template,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cms_index_renders_for_admin(): void
    {
        $resp = $this->actingAs($this->actor())->get('/admin/cms');
        $resp->assertStatus(200);
        $resp->assertSee('Pages', false);
        $resp->assertSee('Add page', false);
    }

    public function test_cms_index_includes_seeded_marketing_rows(): void
    {
        // The live DB has the home/our-story/condos rows already seeded — assert at least one renders.
        $resp = $this->actingAs($this->actor())->get('/admin/cms');
        $resp->assertStatus(200);
        $resp->assertSee('cms-list-table', false);
        // Marketing chip should appear for the home row.
        $resp->assertSee('cms-chip--marketing', false);
    }

    public function test_duplicate_endpoint_creates_copy_with_suffix(): void
    {
        $slug = '_p13a_dup_' . uniqid();
        $id = $this->makeCmsRow($slug, 'Phase 13A duplicate source');

        $resp = $this->actingAs($this->actor())
            ->withoutMiddleware()
            ->post('/admin/cms/' . $id . '/duplicate');

        $resp->assertRedirect();

        $copy = DB::table('cms')->where('seo_url', $slug . '_copy_1')->first();
        $this->assertNotNull($copy, 'A copy row with _copy_1 suffix should exist');
        $this->assertSame('Phase 13A duplicate source (copy)', $copy->name);
    }

    public function test_duplicate_endpoint_bumps_suffix_when_copy_already_exists(): void
    {
        $slug = '_p13a_bump_' . uniqid();
        $id = $this->makeCmsRow($slug, 'Phase 13A bump source');

        // First duplicate → creates _copy_1
        $this->actingAs($this->actor())->withoutMiddleware()
            ->post('/admin/cms/' . $id . '/duplicate');

        // Second duplicate of the same source → should produce _copy_2
        $this->actingAs($this->actor())->withoutMiddleware()
            ->post('/admin/cms/' . $id . '/duplicate');

        $copy1 = DB::table('cms')->where('seo_url', $slug . '_copy_1')->first();
        $copy2 = DB::table('cms')->where('seo_url', $slug . '_copy_2')->first();
        $this->assertNotNull($copy1, '_copy_1 should still exist');
        $this->assertNotNull($copy2, '_copy_2 should have been created on the second duplicate');
    }
}
