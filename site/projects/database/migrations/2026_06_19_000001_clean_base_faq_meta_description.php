<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cms')
            ->where('seo_url', 'faqs')
            ->where('meta_description', 'like', '%Everything you need to know%')
            ->update([
                'meta_description' => 'Everything you need to know about booking and staying with Base Vacation Rentals.',
            ]);
    }

    public function down(): void
    {
        // No-op: do not reintroduce retired client copy into the base clone.
    }
};
