<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('booking_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_requests', 'first_name')) {
                $table->string('first_name', 255)->nullable()->after('name');
            }
            if (!Schema::hasColumn('booking_requests', 'last_name')) {
                $table->string('last_name', 255)->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('booking_requests', 'source')) {
                $table->string('source', 255)->nullable()->after('last_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('booking_requests', function (Blueprint $table) {
            foreach (['first_name', 'last_name', 'source'] as $col) {
                if (Schema::hasColumn('booking_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
