<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('cms', function (Blueprint $t) {
            if (!Schema::hasColumn('cms', 'booking_empty_message')) {
                $t->text('booking_empty_message')->nullable();
            }
        });
    }

    public function down(): void {
        Schema::table('cms', function (Blueprint $t) {
            if (Schema::hasColumn('cms', 'booking_empty_message')) {
                $t->dropColumn('booking_empty_message');
            }
        });
    }
};
