<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cms')) {
            return;
        }

        DB::table('cms')->whereIn('seo_url', [
            'privacy-policy',
            'terms-of-service',
        ])->update([
            'publish' => 'unpublished',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('cms')) {
            return;
        }

        DB::table('cms')->whereIn('seo_url', [
            'privacy-policy',
            'terms-of-service',
        ])->update([
            'publish' => 'published',
            'updated_at' => now(),
        ]);
    }
};
