<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ical_import_list', function (Blueprint $table) {
            $table->string('label', 64)->nullable()->after('property_id');
            $table->string('color', 16)->nullable()->after('label');
            $table->timestamp('last_refreshed_at')->nullable()->after('color');
            $table->text('last_error')->nullable()->after('last_refreshed_at');
        });
    }

    public function down(): void
    {
        Schema::table('ical_import_list', function (Blueprint $table) {
            $table->dropColumn(['label', 'color', 'last_refreshed_at', 'last_error']);
        });
    }
};
