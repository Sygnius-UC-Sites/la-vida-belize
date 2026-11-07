<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            // Stay constraint
            if (!Schema::hasColumn('properties', 'max_stay')) {
                $t->integer('max_stay')->nullable();
            }

            // Per-property tax base override (NULL = use global default)
            foreach ([
                'tax_applies_to_nights',
                'tax_applies_to_cleaning_fee',
                'tax_applies_to_pet_fee',
                'tax_applies_to_extra_guest_fee',
                'tax_applies_to_hottub_addon',
                'tax_applies_to_firewood_addon',
                'tax_applies_to_rv_hookup_addon',
                'tax_applies_to_extra_visitor_addon',
            ] as $col) {
                if (!Schema::hasColumn('properties', $col)) {
                    $t->boolean($col)->nullable();
                }
            }

            // Per-property discount base override (NULL = use global default)
            foreach ([
                'discount_applies_to_nights',
                'discount_applies_to_cleaning_fee',
                'discount_applies_to_pet_fee',
                'discount_applies_to_extra_guest_fee',
                'discount_applies_to_hottub_addon',
                'discount_applies_to_firewood_addon',
                'discount_applies_to_rv_hookup_addon',
                'discount_applies_to_extra_visitor_addon',
            ] as $col) {
                if (!Schema::hasColumn('properties', $col)) {
                    $t->boolean($col)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            $cols = [
                'max_stay',
                'tax_applies_to_nights', 'tax_applies_to_cleaning_fee', 'tax_applies_to_pet_fee',
                'tax_applies_to_extra_guest_fee', 'tax_applies_to_hottub_addon',
                'tax_applies_to_firewood_addon', 'tax_applies_to_rv_hookup_addon',
                'tax_applies_to_extra_visitor_addon',
                'discount_applies_to_nights', 'discount_applies_to_cleaning_fee',
                'discount_applies_to_pet_fee', 'discount_applies_to_extra_guest_fee',
                'discount_applies_to_hottub_addon', 'discount_applies_to_firewood_addon',
                'discount_applies_to_rv_hookup_addon', 'discount_applies_to_extra_visitor_addon',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('properties', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
