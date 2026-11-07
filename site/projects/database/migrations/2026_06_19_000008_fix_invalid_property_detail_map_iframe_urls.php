<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('properties') || ! Schema::hasColumn('properties', 'detail_map_iframe_src')) {
            return;
        }

        DB::table('properties')
            ->whereIn('detail_map_iframe_src', ['google.com', 'www.google.com'])
            ->update([
                'detail_map_iframe_src' => 'https://www.google.com/maps?q=Baru,+Costa+Rica&output=embed',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data cleanup only. Do not reintroduce invalid iframe URLs.
    }
};
