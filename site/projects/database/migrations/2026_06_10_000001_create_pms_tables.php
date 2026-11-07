<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated PMS table creation for Miami.
 * All creations guarded with Schema::hasTable() so safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        // pms_properties
        if (!Schema::hasTable('pms_properties')) {
            Schema::create('pms_properties', function (Blueprint $table) {
                $table->id();
                $table->string('pms_property_id')->nullable()->index();
                $table->string('pms_source', 30)->default('guesty');
                $table->string('name')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('address')->nullable();
                $table->string('zip')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('image_url')->nullable();
                $table->boolean('has_addons')->default(false);
                $table->boolean('has_agreement')->default(false);
                $table->longText('agreement_text')->nullable();
                $table->string('agreement_url')->nullable();
                $table->string('owner')->nullable();
                $table->decimal('rating', 3, 1)->nullable();
                $table->boolean('price_unit_in_days')->default(true);
                $table->decimal('min_price', 10, 2)->nullable();
                $table->decimal('original_min_price', 10, 2)->nullable();
                $table->decimal('max_price', 10, 2)->nullable();
                $table->decimal('original_max_price', 10, 2)->nullable();
                $table->integer('rooms')->nullable();
                $table->string('in_out_max_date')->nullable();
                $table->string('seo_url')->nullable();
                $table->string('meta_title')->nullable();
                $table->longText('meta_keywords')->nullable();
                $table->longText('meta_description')->nullable();
                $table->boolean('is_home')->default(false);
                $table->string('status')->default('false');
                $table->longText('map')->nullable();
                $table->string('banner_image')->nullable();
                $table->string('currency', 10)->nullable();
                $table->longText('subscription_plans')->nullable();
                $table->unsignedBigInteger('location_id')->nullable();
                $table->string('review_widget')->nullable();
                $table->timestamps();
            });
        }

        // pms_images
        if (!Schema::hasTable('pms_images')) {
            Schema::create('pms_images', function (Blueprint $table) {
                $table->id();
                $table->string('pms_property_id')->nullable()->index();
                $table->string('pms_room_id')->nullable()->index();
                $table->string('pms_source', 30)->nullable();
                $table->string('text')->nullable();
                $table->string('url')->nullable();
                $table->timestamps();
            });
        }

        // pms_rooms
        if (!Schema::hasTable('pms_rooms')) {
            Schema::create('pms_rooms', function (Blueprint $table) {
                $table->id();
                $table->string('pms_property_id')->nullable()->index();
                $table->string('pms_room_id')->nullable()->index();
                $table->string('pms_source', 30)->default('guesty');
                $table->string('name')->nullable();
                $table->longText('description')->nullable();
                $table->boolean('breakfast_included')->default(false);
                $table->boolean('has_parking')->default(false);
                $table->boolean('adults_only')->default(false);
                $table->boolean('pets_allowed')->default(false);
                $table->boolean('show_additional_key_facts')->default(false);
                $table->string('image_url')->nullable();
                $table->integer('max_people')->nullable();
                $table->integer('accommodates')->nullable();
                $table->integer('units')->nullable();
                $table->boolean('has_wifi')->default(false);
                $table->boolean('has_meal_plan')->default(false);
                $table->integer('bedrooms')->nullable();
                $table->decimal('bathrooms', 4, 1)->nullable();
                $table->string('area_unit')->nullable();
                $table->decimal('area', 10, 2)->nullable();
                $table->decimal('min_price', 10, 2)->nullable();
                $table->decimal('original_min_price', 10, 2)->nullable();
                $table->decimal('max_price', 10, 2)->nullable();
                $table->decimal('original_max_price', 10, 2)->nullable();
                $table->boolean('price_unit_in_days')->default(true);
                $table->string('currency', 10)->nullable();
                $table->longText('images_json')->nullable();
                $table->longText('amenities_json')->nullable();
                $table->string('seo_url')->nullable();
                $table->string('meta_title')->nullable();
                $table->longText('meta_keywords')->nullable();
                $table->longText('meta_description')->nullable();
                $table->boolean('is_home')->default(false);
                $table->string('status')->default('active');
                $table->string('banner_image')->nullable();
                $table->unsignedBigInteger('location_id')->nullable();
                $table->string('review_widget')->nullable();
                $table->longText('map')->nullable();
                $table->integer('ordering')->nullable();
                $table->integer('custom_min_stay')->nullable();
                $table->integer('min_stay')->nullable();
                $table->decimal('deposit_percent', 5, 2)->nullable();
                $table->integer('balance_due_days')->nullable();
                $table->string('api_id')->nullable();
                $table->string('api_pms')->nullable();
                $table->string('virtual_tour')->nullable();
                $table->longText('house_rules')->nullable();
                $table->longText('cancellation_policy')->nullable();
                $table->longText('booking_policy')->nullable();
                $table->longText('key_facts_json')->nullable();
                $table->longText('optional_fees_json')->nullable();
                $table->integer('max_pets')->nullable();
                $table->longText('pricing_json')->nullable();
                $table->string('address')->nullable();
                $table->string('city')->nullable();
                $table->string('country')->nullable();
                $table->string('zip')->nullable();
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->string('property_type')->nullable();
                $table->longText('bed_config_json')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                $table->boolean('active')->default(false);
                $table->timestamps();
            });
        }

        // pms_rates
        if (!Schema::hasTable('pms_rates')) {
            Schema::create('pms_rates', function (Blueprint $table) {
                $table->id();
                $table->string('property_id')->nullable()->index();
                $table->string('pms_source', 30)->nullable();
                $table->date('single_date')->nullable()->index();
                $table->unsignedBigInteger('single_date_timestamp')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->boolean('is_available')->default(true);
                $table->string('platform_type')->nullable();
                $table->string('currency', 10)->nullable();
                $table->decimal('base_price', 10, 2)->nullable();
                $table->longText('notes')->nullable();
                $table->integer('min_stay')->nullable();
                $table->integer('base_min_stay')->nullable();
                $table->string('checkin_day')->nullable();
                $table->string('checkout_day')->nullable();
                $table->decimal('discount_weekly', 5, 2)->nullable();
                $table->decimal('discount_monthly', 5, 2)->nullable();
                $table->unsignedBigInteger('rate_group_id')->nullable();
                $table->timestamps();
            });
        }

        // pms_unavailable_dates
        if (!Schema::hasTable('pms_unavailable_dates')) {
            Schema::create('pms_unavailable_dates', function (Blueprint $table) {
                $table->id();
                $table->date('un_date')->nullable();
                $table->string('property_id')->nullable()->index();
                $table->string('pms_source', 30)->nullable();
                $table->timestamps();
            });
        }

        // pms_bookings
        if (!Schema::hasTable('pms_bookings')) {
            Schema::create('pms_bookings', function (Blueprint $table) {
                $table->id();
                $table->date('arrival')->nullable();
                $table->date('departure')->nullable();
                $table->integer('people')->nullable();
                $table->string('property_id')->nullable()->index();
                $table->string('pms_booking_id')->nullable()->index();
                $table->string('pms_source', 30)->nullable();
                $table->string('latest_webhook_event_id')->nullable();
                $table->string('type')->nullable();
                $table->string('booking_type')->nullable();
                $table->string('status')->nullable();
                $table->string('source')->nullable();
                $table->string('source_text')->nullable();
                $table->longText('guest')->nullable();
                $table->string('property_name')->nullable();
                $table->longText('rooms')->nullable();
                $table->longText('room_ids')->nullable();
                $table->boolean('is_replied')->default(false);
                $table->boolean('is_deleted')->default(false);
                $table->timestamp('date_deleted')->nullable();
                $table->decimal('total_amount', 10, 2)->nullable();
                $table->decimal('total_paid', 10, 2)->nullable();
                $table->decimal('amount_to_pay', 10, 2)->nullable();
                $table->string('currency', 10)->nullable();
                $table->timestamps();
            });
        }

        // pms_calendar
        if (!Schema::hasTable('pms_calendar')) {
            Schema::create('pms_calendar', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('pms_source', 30);
                $table->string('pms_property_id', 255);
                $table->string('pms_room_id', 255);
                $table->date('date');
                $table->tinyInteger('state')->default(-1)->comment('-1=unknown, 0=unavailable, 1=available');
                $table->smallInteger('inventory')->unsigned()->nullable();
                $table->decimal('nightly_rate', 10, 4)->nullable();
                $table->char('currency', 3)->nullable();
                $table->unsignedTinyInteger('min_stay')->nullable();
                $table->unsignedSmallInteger('max_stay')->nullable();
                $table->boolean('closed_to_arrival')->default(false);
                $table->boolean('closed_to_departure')->default(false);
                $table->boolean('stop_sell')->default(false);
                $table->timestamp('pms_updated_at')->nullable();
                $table->timestamp('synced_at')->useCurrent()->useCurrentOnUpdate();
                $table->unique(['pms_source', 'pms_room_id', 'date'], 'uq_pms_calendar');
                $table->index(['pms_room_id', 'date'], 'idx_room_date');
                $table->index(['pms_property_id', 'date'], 'idx_property_date');
                $table->index('state', 'idx_state');
            });
        }

        // pms_calendar_discrepancies
        if (!Schema::hasTable('pms_calendar_discrepancies')) {
            Schema::create('pms_calendar_discrepancies', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('pms_source', 30);
                $table->string('pms_property_id', 255);
                $table->string('pms_room_id', 255);
                $table->string('property_name', 255)->nullable();
                $table->date('date');
                $table->string('field', 50);
                $table->string('local_value', 100)->nullable();
                $table->string('live_value', 100)->nullable();
                $table->string('severity', 10);
                $table->timestamp('detected_at')->useCurrent();
                $table->boolean('resolved')->default(false);
                $table->timestamp('resolved_at')->nullable();
                $table->index(['pms_source', 'date'], 'idx_disc_pms_date');
                $table->index(['severity', 'resolved'], 'idx_disc_severity');
                $table->index('detected_at', 'idx_disc_detected');
            });
        }

        // pms_amenity_groups
        if (!Schema::hasTable('pms_amenity_groups')) {
            Schema::create('pms_amenity_groups', function (Blueprint $table) {
                $table->id();
                $table->string('pms_property_id')->nullable()->index();
                $table->string('pms_room_id')->nullable()->index();
                $table->string('pms_source', 30)->nullable();
                $table->string('text')->nullable();
                $table->timestamps();
            });
        }

        // pms_amenity_group_items
        if (!Schema::hasTable('pms_amenity_group_items')) {
            Schema::create('pms_amenity_group_items', function (Blueprint $table) {
                $table->id();
                $table->string('pms_property_id')->nullable()->index();
                $table->string('pms_room_id')->nullable()->index();
                $table->string('pms_source', 30)->nullable();
                $table->unsignedBigInteger('pms_amenity_group_id')->nullable()->index();
                $table->string('name')->nullable();
                $table->string('prefix')->nullable();
                $table->string('bracket')->nullable();
                $table->string('text')->nullable();
                $table->timestamps();
            });
        }

        // scheduled_payments
        if (!Schema::hasTable('scheduled_payments')) {
            Schema::create('scheduled_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('booking_request_id');
                $table->string('pms_source', 30);
                $table->string('pms_booking_id')->nullable();
                $table->string('stripe_customer_id')->nullable();
                $table->string('stripe_payment_method_id', 50)->nullable();
                $table->decimal('amount', 10, 2);
                $table->string('currency', 3)->default('USD');
                $table->date('due_date');
                $table->string('status', 20)->default('pending');
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('charged_at')->nullable();
                $table->string('stripe_charge_id')->nullable();
                $table->text('error_message')->nullable();
                $table->boolean('pms_notified')->default(false);
                $table->text('pms_notify_error')->nullable();
                $table->string('pms_payment_id', 100)->nullable();
                $table->boolean('reminder_sent')->default(false);
                $table->timestamps();
                $table->index(['status', 'due_date']);
                $table->index('booking_request_id');
            });
        }

        // payment_refunds
        if (!Schema::hasTable('payment_refunds')) {
            Schema::create('payment_refunds', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('payment_id');
                $table->string('stripe_refund_id', 100)->unique();
                $table->decimal('amount', 10, 2);
                $table->string('currency', 10)->default('USD');
                $table->string('pms_sync_status', 40)->default('pending');
                $table->string('pms_refund_id', 100)->nullable();
                $table->text('pms_sync_error')->nullable();
                $table->unsignedTinyInteger('retry_count')->default(0);
                $table->timestamps();
                $table->index('payment_id');
                $table->index(['pms_sync_status', 'retry_count']);
            });
        }

        // lodgify_rate_calendar
        if (!Schema::hasTable('lodgify_rate_calendar')) {
            Schema::create('lodgify_rate_calendar', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('property_id');
                $table->unsignedInteger('room_type_id');
                $table->date('date');
                $table->boolean('is_default')->default(false);
                $table->unsignedSmallInteger('min_stay')->default(1);
                $table->unsignedSmallInteger('max_stay')->default(0);
                $table->decimal('price_per_day', 10, 4);
                $table->decimal('price_per_additional_guest', 10, 4)->default(0);
                $table->unsignedTinyInteger('additional_guests_starts_from')->default(0);
                $table->char('currency_code', 3)->default('USD');
                $table->decimal('vat', 5, 2)->default(0);
                $table->boolean('is_vat_exclusive')->default(false);
                $table->string('source_api_version', 5)->default('v2');
                $table->timestamp('pms_updated_at')->nullable();
                $table->timestamp('synced_at')->useCurrent()->useCurrentOnUpdate();
                $table->unique(
                    ['property_id', 'room_type_id', 'date', 'is_default', 'min_stay', 'max_stay'],
                    'uq_lodgify_rate_band'
                );
                $table->index(['property_id', 'room_type_id', 'date'], 'idx_lrc_room_date');
                $table->index('date', 'idx_lrc_date');
            });
        }
    }

    public function down(): void
    {
        // Drop in reverse dependency order
        Schema::dropIfExists('lodgify_rate_calendar');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('scheduled_payments');
        Schema::dropIfExists('pms_amenity_group_items');
        Schema::dropIfExists('pms_amenity_groups');
        Schema::dropIfExists('pms_calendar_discrepancies');
        Schema::dropIfExists('pms_calendar');
        Schema::dropIfExists('pms_bookings');
        Schema::dropIfExists('pms_unavailable_dates');
        Schema::dropIfExists('pms_rates');
        Schema::dropIfExists('pms_rooms');
        Schema::dropIfExists('pms_images');
        Schema::dropIfExists('pms_properties');
    }
};
