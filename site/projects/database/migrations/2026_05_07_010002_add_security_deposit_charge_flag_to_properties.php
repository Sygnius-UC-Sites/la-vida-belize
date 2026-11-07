<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (!Schema::hasColumn('properties', 'security_deposit_charge_at_booking')) {
                $table->boolean('security_deposit_charge_at_booking')
                    ->default(false)
                    ->after('security_deposit_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'security_deposit_charge_at_booking')) {
                $table->dropColumn('security_deposit_charge_at_booking');
            }
        });
    }
};
