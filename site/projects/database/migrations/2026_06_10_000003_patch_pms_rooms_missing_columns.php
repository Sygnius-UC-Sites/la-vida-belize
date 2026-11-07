<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Patch pms_rooms with any columns that might be missing if the table was
 * pre-existing before this port. Safe to re-run — all guarded with hasColumn().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pms_rooms')) {
            // Table not yet created — nothing to patch
            return;
        }

        Schema::table('pms_rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('pms_rooms', 'last_synced_at')) {
                $table->timestamp('last_synced_at')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'pms_source')) {
                $table->string('pms_source', 30)->nullable()->default('guesty');
            }
            if (!Schema::hasColumn('pms_rooms', 'accommodates')) {
                $table->integer('accommodates')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'min_stay')) {
                $table->integer('min_stay')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'deposit_percent')) {
                $table->decimal('deposit_percent', 5, 2)->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'balance_due_days')) {
                $table->integer('balance_due_days')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'key_facts_json')) {
                $table->longText('key_facts_json')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'pricing_json')) {
                $table->longText('pricing_json')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'optional_fees_json')) {
                $table->longText('optional_fees_json')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'bed_config_json')) {
                $table->longText('bed_config_json')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'cancellation_policy')) {
                $table->longText('cancellation_policy')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'house_rules')) {
                $table->longText('house_rules')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'booking_policy')) {
                $table->longText('booking_policy')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'property_type')) {
                $table->string('property_type')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'active')) {
                $table->boolean('active')->default(true);
            }
            if (!Schema::hasColumn('pms_rooms', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'address')) {
                $table->string('address')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'city')) {
                $table->string('city')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'state')) {
                $table->string('state')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'country')) {
                $table->string('country')->nullable();
            }
            if (!Schema::hasColumn('pms_rooms', 'zip')) {
                $table->string('zip')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Non-destructive patch — no rollback needed
    }
};
