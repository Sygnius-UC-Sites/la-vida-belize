<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            $t->dropColumn([
                'tax_applies_to_hottub_addon', 'tax_applies_to_firewood_addon',
                'tax_applies_to_rv_hookup_addon', 'tax_applies_to_extra_visitor_addon',
                'discount_applies_to_hottub_addon', 'discount_applies_to_firewood_addon',
                'discount_applies_to_rv_hookup_addon', 'discount_applies_to_extra_visitor_addon',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            $t->boolean('tax_applies_to_hottub_addon')->nullable();
            $t->boolean('tax_applies_to_firewood_addon')->nullable();
            $t->boolean('tax_applies_to_rv_hookup_addon')->nullable();
            $t->boolean('tax_applies_to_extra_visitor_addon')->nullable();
            $t->boolean('discount_applies_to_hottub_addon')->nullable();
            $t->boolean('discount_applies_to_firewood_addon')->nullable();
            $t->boolean('discount_applies_to_rv_hookup_addon')->nullable();
            $t->boolean('discount_applies_to_extra_visitor_addon')->nullable();
        });
    }
};
