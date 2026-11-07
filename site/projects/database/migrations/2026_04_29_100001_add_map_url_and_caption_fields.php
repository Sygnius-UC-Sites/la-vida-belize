<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('attractions', function (Blueprint $table) {
            $table->string('map_url')->nullable()->after('address');
        });
        Schema::table('galleries', function (Blueprint $table) {
            $table->string('caption')->nullable()->after('image');
        });
    }
    public function down() {
        Schema::table('attractions', function (Blueprint $table) {
            $table->dropColumn('map_url');
        });
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('caption');
        });
    }
};
