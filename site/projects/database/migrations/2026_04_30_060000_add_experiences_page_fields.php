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
                ['experiences_intro', 'longText'],
                ['experience_grid_items', 'longText'],
            ];
            foreach ($columns as [$name, $type]) {
                if (!in_array($name, $existing)) {
                    $table->{$type}($name)->nullable();
                }
            }
        });
    }

    public function down()
    {
        Schema::table('cms', function (Blueprint $table) {
            foreach (['experiences_intro','experience_grid_items'] as $c) {
                if (Schema::hasColumn('cms', $c)) $table->dropColumn($c);
            }
        });
    }
};
