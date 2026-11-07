<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            // Stash scraped amenity labels as raw JSON. Admin reviews and applies
            // them to the structured PropertyAmenity catalog manually so we never
            // silently mis-tag a property with the wrong amenity.
            $t->longText('scraped_amenities_raw')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            $t->dropColumn('scraped_amenities_raw');
        });
    }
};
