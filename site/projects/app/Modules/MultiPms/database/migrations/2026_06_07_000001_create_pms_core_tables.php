<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MultiPms module — consolidated migration for all pms_* tables.
 * Creates pms_properties, pms_rooms, pms_images in their final schema.
 * Also links the core properties table to PMS rooms (additive columns only).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── pms_properties ────────────────────────────────────────────────────
        Schema::create('pms_properties', function (Blueprint $table) {
            $table->id();
            $table->string('pms_source')->default('');
            $table->string('pms_property_id')->default('');
            $table->string('name')->nullable();
            $table->string('seo_url')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zip')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('currency', 10)->nullable();
            $table->decimal('min_price', 10, 2)->nullable();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->string('status')->default('true');
            $table->string('property_type')->nullable();
            $table->text('raw_data_json')->nullable();
            $table->timestamps();
            $table->index(['pms_source', 'pms_property_id']);
        });

        // ── pms_rooms ─────────────────────────────────────────────────────────
        Schema::create('pms_rooms', function (Blueprint $table) {
            $table->id();
            $table->string('pms_source')->default('');
            $table->string('pms_property_id')->default('');
            $table->string('pms_room_id')->default('');
            $table->string('name')->nullable();
            $table->string('seo_url')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->string('heading')->nullable();
            $table->integer('max_people')->nullable();
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->integer('sleeps')->nullable();
            $table->string('currency', 10)->nullable();
            $table->decimal('min_price', 10, 2)->nullable();
            $table->decimal('max_price', 10, 2)->nullable();
            $table->decimal('original_min_price', 10, 2)->nullable();
            $table->decimal('original_max_price', 10, 2)->nullable();
            $table->string('price_unit_in_days')->nullable();
            $table->text('amenities_json')->nullable();
            $table->text('images_json')->nullable();
            $table->text('key_facts_json')->nullable();
            $table->text('pricing_json')->nullable();
            $table->text('bed_config_json')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zip')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('property_type')->nullable();
            $table->string('status')->default('true');
            $table->text('house_rules')->nullable();
            $table->text('cancellation_policy')->nullable();
            $table->text('booking_policy')->nullable();
            $table->integer('custom_min_stay')->nullable();
            $table->text('virtual_tour')->nullable();
            $table->string('review_widget')->nullable();
            $table->string('banner_image')->nullable();
            $table->integer('ordering')->default(0);
            $table->string('map')->nullable();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->string('api_id')->nullable();
            $table->string('api_pms')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('is_home')->default('false');
            $table->integer('units')->default(1);
            $table->string('area')->nullable();
            $table->string('area_unit')->nullable();
            $table->boolean('has_wifi')->default(false);
            $table->boolean('has_meal_plan')->default(false);
            $table->boolean('has_parking')->default(false);
            $table->boolean('pets_allowed')->default(false);
            $table->boolean('adults_only')->default(false);
            $table->boolean('breakfast_included')->default(false);
            $table->boolean('show_additional_key_facts')->default(false);
            $table->timestamps();
            $table->index(['pms_source', 'pms_property_id']);
            $table->index(['pms_source', 'pms_room_id']);
        });

        // ── pms_images ────────────────────────────────────────────────────────
        Schema::create('pms_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pms_room_id');
            $table->string('pms_source')->default('');
            $table->string('url');
            $table->string('caption')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index('pms_room_id');
        });

        // ── Link core properties table to PMS (additive only) ─────────────────
        if (Schema::hasTable('properties') && !Schema::hasColumn('properties', 'pms_source')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->string('pms_source')->nullable()->after('id');
                $table->string('pms_room_id')->nullable()->after('pms_source');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_images');
        Schema::dropIfExists('pms_rooms');
        Schema::dropIfExists('pms_properties');

        if (Schema::hasTable('properties') && Schema::hasColumn('properties', 'pms_source')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->dropColumn(['pms_source', 'pms_room_id']);
            });
        }
    }
};
