<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('property_amenities', function (Blueprint $table) {
            $table->string('icon', 64)->nullable()->after('image');
        });
        Schema::table('property_amenity_groups', function (Blueprint $table) {
            $table->string('icon', 64)->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('property_amenities', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
        Schema::table('property_amenity_groups', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
