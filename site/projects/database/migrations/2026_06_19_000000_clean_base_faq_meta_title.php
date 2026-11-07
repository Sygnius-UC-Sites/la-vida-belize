<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('cms')
            ->where('seo_url', 'faqs')
            ->where('meta_title', 'like', 'FAQ%')
            ->update([
                'meta_title' => 'FAQs · Base Vacation Rentals, Your Destination',
            ]);
    }

    public function down(): void
    {
        // No-op: do not reintroduce retired client names into the base clone.
    }
};
