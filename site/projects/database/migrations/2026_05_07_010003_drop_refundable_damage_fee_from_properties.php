<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Safety: refuse to drop if any property has a non-zero value, since
        // that would silently lose money. Empty across the install today.
        $live = DB::table('properties')->where('refundable_damage_fee', '>', 0)->count();
        if ($live > 0) {
            throw new \RuntimeException(
                "Refusing to drop refundable_damage_fee — {$live} property rows still have a non-zero value. "
                . "Migrate those values to security_deposit_amount first, then re-run this migration."
            );
        }

        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'refundable_damage_fee')) {
                $table->dropColumn('refundable_damage_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (!Schema::hasColumn('properties', 'refundable_damage_fee')) {
                $table->decimal('refundable_damage_fee', 8, 2)->nullable()->after('cleaning_fee');
            }
        });
    }
};
