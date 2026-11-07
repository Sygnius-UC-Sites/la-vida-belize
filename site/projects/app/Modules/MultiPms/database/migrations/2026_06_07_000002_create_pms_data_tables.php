<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MultiPms module — raw PMS data cache tables (one per PMS).
 * These store the full API response so syncs can detect changes
 * without hitting the PMS API on every page load.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_guesty_data', function (Blueprint $table) {
            $table->id();
            $table->string('pms_property_id')->unique();
            $table->longText('raw_property_json')->nullable();
            $table->longText('raw_units_json')->nullable();
            $table->timestamps();
        });

        Schema::create('pms_hostaway_data', function (Blueprint $table) {
            $table->id();
            $table->string('pms_property_id')->unique();
            $table->longText('raw_property_json')->nullable();
            $table->longText('raw_units_json')->nullable();
            $table->timestamps();
        });

        Schema::create('pms_hostfully_data', function (Blueprint $table) {
            $table->id();
            $table->string('pms_property_id')->unique();
            $table->longText('raw_property_json')->nullable();
            $table->longText('raw_units_json')->nullable();
            $table->timestamps();
        });

        Schema::create('pms_lodgify_data', function (Blueprint $table) {
            $table->id();
            $table->string('pms_property_id')->unique();
            $table->longText('raw_property_json')->nullable();
            $table->longText('raw_units_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_lodgify_data');
        Schema::dropIfExists('pms_hostfully_data');
        Schema::dropIfExists('pms_hostaway_data');
        Schema::dropIfExists('pms_guesty_data');
    }
};
