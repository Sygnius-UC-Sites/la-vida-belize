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
                ['compare_heading', 'text'],
                ['compare_table_data', 'longText'],
                ['houses_subtitle_heading', 'text'],
            ];
            foreach ($columns as [$name, $type]) {
                if (!in_array($name, $existing)) {
                    $table->{$type}($name)->nullable();
                }
            }
        });

        Schema::table('properties', function (Blueprint $table) {
            if (!Schema::hasColumn('properties', 'rating')) {
                $table->string('rating', 16)->nullable();
            }
            if (!Schema::hasColumn('properties', 'subtitle')) {
                $table->string('subtitle')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('cms', function (Blueprint $table) {
            foreach (['compare_heading','compare_table_data','houses_subtitle_heading'] as $c) {
                if (Schema::hasColumn('cms', $c)) $table->dropColumn($c);
            }
        });
        Schema::table('properties', function (Blueprint $table) {
            foreach (['rating','subtitle'] as $c) {
                if (Schema::hasColumn('properties', $c)) $table->dropColumn($c);
            }
        });
    }
};
