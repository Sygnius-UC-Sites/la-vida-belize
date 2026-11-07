<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('property_amenities', function (Blueprint $table) {
            $table->boolean('is_card_highlight')->default(false)->after('icon');
        });

        // Best-effort backfill from existing card_amenities JSON
        $propertyIds = DB::table('properties')->whereNotNull('card_amenities')->pluck('id');
        foreach ($propertyIds as $propertyId) {
            \Helper::backfillCardHighlightsForProperty($propertyId);
        }
    }

    public function down(): void
    {
        Schema::table('property_amenities', function (Blueprint $table) {
            $table->dropColumn('is_card_highlight');
        });
    }
};
