<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('properties') || ! Schema::hasColumn('properties', 'map')) {
            return;
        }

        DB::table('properties')
            ->whereIn('map', ['google.com', 'www.google.com'])
            ->update([
                'map' => 'https://www.google.com/maps?q=Baru,+Costa+Rica&output=embed',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Data cleanup only. Do not reintroduce invalid iframe URLs.
    }
};
