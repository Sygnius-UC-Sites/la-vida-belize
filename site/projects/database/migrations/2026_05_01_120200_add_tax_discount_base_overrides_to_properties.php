<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Tax base override (D20) — null = use global
            $table->boolean('tax_applies_to_nights')->nullable();
            $table->boolean('tax_applies_to_cleaning_fee')->nullable();
            $table->boolean('tax_applies_to_pet_fee')->nullable();
            $table->boolean('tax_applies_to_extra_guest_fee')->nullable();
            $table->boolean('tax_applies_to_hottub_addon')->nullable();
            $table->boolean('tax_applies_to_firewood_addon')->nullable();
            $table->boolean('tax_applies_to_rv_hookup_addon')->nullable();
            $table->boolean('tax_applies_to_extra_visitor_addon')->nullable();

            // Discount base override (D21) — null = use global
            $table->boolean('discount_applies_to_nights')->nullable();
            $table->boolean('discount_applies_to_cleaning_fee')->nullable();
            $table->boolean('discount_applies_to_pet_fee')->nullable();
            $table->boolean('discount_applies_to_extra_guest_fee')->nullable();
            $table->boolean('discount_applies_to_hottub_addon')->nullable();
            $table->boolean('discount_applies_to_firewood_addon')->nullable();
            $table->boolean('discount_applies_to_rv_hookup_addon')->nullable();
            $table->boolean('discount_applies_to_extra_visitor_addon')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'tax_applies_to_nights', 'tax_applies_to_cleaning_fee', 'tax_applies_to_pet_fee',
                'tax_applies_to_extra_guest_fee', 'tax_applies_to_hottub_addon',
                'tax_applies_to_firewood_addon', 'tax_applies_to_rv_hookup_addon',
                'tax_applies_to_extra_visitor_addon',
                'discount_applies_to_nights', 'discount_applies_to_cleaning_fee',
                'discount_applies_to_pet_fee', 'discount_applies_to_extra_guest_fee',
                'discount_applies_to_hottub_addon', 'discount_applies_to_firewood_addon',
                'discount_applies_to_rv_hookup_addon', 'discount_applies_to_extra_visitor_addon',
            ]);
        });
    }
};
