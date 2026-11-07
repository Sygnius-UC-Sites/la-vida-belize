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
                ['cd_booking_disclaimer', 'text'],
                ['cd_map_iframe_src', 'text'],
                ['cd_share_label', 'text'],
                ['cd_map_heading', 'text'],
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
            foreach (['cd_booking_disclaimer','cd_map_iframe_src','cd_share_label','cd_map_heading'] as $c) {
                if (Schema::hasColumn('cms', $c)) $table->dropColumn($c);
            }
        });
    }
};
