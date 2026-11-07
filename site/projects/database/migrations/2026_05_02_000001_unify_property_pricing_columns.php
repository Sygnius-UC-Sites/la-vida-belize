<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: backfill standard_rate from price for properties where price has real data
        if (Schema::hasColumn('properties', 'price')) {
            DB::statement('UPDATE properties SET standard_rate = price WHERE price IS NOT NULL AND price > 0');
        }

        // Step 2: drop the now-redundant price column
        if (Schema::hasColumn('properties', 'price')) {
            Schema::table('properties', function (Blueprint $t) {
                $t->dropColumn('price');
            });
        }

        // Step 3: resolve property_rates schema drift — these columns exist in live DB but had no migration
        $columns = array_column(Schema::getColumns('property_rates'), 'name');
        Schema::table('property_rates', function (Blueprint $t) use ($columns) {
            if (!in_array('rate_group_id', $columns)) {
                $t->unsignedBigInteger('rate_group_id')->nullable()->after('property_id');
                $t->index('rate_group_id', 'idx_property_rates_group');
            }
            if (!in_array('checkin_day', $columns))      $t->string('checkin_day', 20)->nullable();
            if (!in_array('checkout_day', $columns))     $t->string('checkout_day', 20)->nullable();
            if (!in_array('discount_weekly', $columns))  $t->integer('discount_weekly')->nullable();
            if (!in_array('discount_monthly', $columns)) $t->integer('discount_monthly')->nullable();
        });

        // Composite index for the calculator's per-day lookup (Layer 4)
        $indexes = array_column(Schema::getIndexes('property_rates'), 'name');
        if (!in_array('idx_property_rates_date', $indexes)) {
            Schema::table('property_rates', function (Blueprint $t) {
                $t->index(['property_id', 'single_date'], 'idx_property_rates_date');
            });
        }
    }

    public function down(): void
    {
        // Restore price column and copy values back from standard_rate
        if (!Schema::hasColumn('properties', 'price')) {
            Schema::table('properties', function (Blueprint $t) {
                $t->decimal('price', 8, 2)->nullable();
            });
            DB::statement('UPDATE properties SET price = standard_rate');
        }
        // Don't undo the property_rates drift fix — those columns are needed by current code
    }
};
