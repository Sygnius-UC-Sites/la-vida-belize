<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 9 — Attractions modernization + drop dead Sliders/Gallery tables.
     * Backed up to projects/storage/backups/pre_phase9_*.sql.
     */
    public function up(): void
    {
        // 1. Add nullable property_id to attractions for optional property linkage.
        if (!Schema::hasColumn('attractions', 'property_id')) {
            Schema::table('attractions', function (Blueprint $t) {
                $t->integer('property_id')->nullable()->after('location_id');
            });
        }

        // 2. Drop the dead Sliders + top-level Gallery tables (admin-only data, no public consumer).
        Schema::dropIfExists('sliders');
        Schema::dropIfExists('galleries');

        // 3. Seed Things-To-Do CMS row idempotently.
        DB::table('cms')->updateOrInsert(
            ['seo_url' => 'things-to-do'],
            [
                'name' => 'Things To Do',
                'templete' => 'attractions',
                'publish' => 'published',
                'meta_title' => 'Things To Do',
                'meta_description' => 'Explore restaurants, beaches, hikes, and other attractions near our properties.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 4. Append Things-To-Do to public nav_menu JSON. Idempotent.
        $row = DB::table('basic_settings')->where('name', 'nav_menu')->first();
        $menu = ($row && $row->value) ? (json_decode($row->value, true) ?: []) : [];
        $alreadyHas = false;
        foreach ($menu as $item) {
            if (($item['url'] ?? '') === 'things-to-do') { $alreadyHas = true; break; }
        }
        if (!$alreadyHas) {
            $menu[] = ['label' => 'Things To Do', 'url' => 'things-to-do'];
            DB::table('basic_settings')->updateOrInsert(
                ['name' => 'nav_menu'],
                ['value' => json_encode($menu), 'updated_at' => now(), 'created_at' => now()],
            );
        }
    }

    public function down(): void
    {
        throw new \RuntimeException(
            'Phase 9 destructive cleanup; restore from projects/storage/backups/pre_phase9_*.sql instead of rolling back.'
        );
    }
};
