<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('cms', function (Blueprint $table) {
            $existing = array_column(Schema::getColumns('cms'), 'name');
            $columns = [
                ['feature_ribbon_items', 'longText'],
                ['filter_categories', 'longText'],
                ['condos_count_text', 'text'],
                ['check_avail_heading', 'text'],
            ];
            foreach ($columns as [$name, $type]) {
                if (!in_array($name, $existing)) {
                    $table->{$type}($name)->nullable();
                }
            }
        });

        Schema::table('properties', function (Blueprint $table) {
            if (!Schema::hasColumn('properties', 'card_amenities')) {
                $table->longText('card_amenities')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('cms', function (Blueprint $table) {
            foreach (['feature_ribbon_items','filter_categories','condos_count_text','check_avail_heading'] as $c) {
                if (Schema::hasColumn('cms', $c)) $table->dropColumn($c);
            }
        });
        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'card_amenities')) $table->dropColumn('card_amenities');
        });
    }
};
