<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defaults = [
            // Tax base defaults (1 = include in tax calculation)
            'tax_applies_to_nights'              => '1',
            'tax_applies_to_cleaning_fee'        => '1',
            'tax_applies_to_pet_fee'             => '1',
            'tax_applies_to_extra_guest_fee'     => '1',
            'tax_applies_to_hottub_addon'        => '0',
            'tax_applies_to_firewood_addon'      => '0',
            'tax_applies_to_rv_hookup_addon'     => '0',
            'tax_applies_to_extra_visitor_addon' => '0',
            // LOS discount base defaults (1 = include in discount calculation)
            'discount_applies_to_nights'              => '1',
            'discount_applies_to_cleaning_fee'        => '0',
            'discount_applies_to_pet_fee'             => '0',
            'discount_applies_to_extra_guest_fee'     => '0',
            'discount_applies_to_hottub_addon'        => '0',
            'discount_applies_to_firewood_addon'      => '0',
            'discount_applies_to_rv_hookup_addon'     => '0',
            'discount_applies_to_extra_visitor_addon' => '0',
        ];

        foreach ($defaults as $name => $value) {
            $exists = DB::table('basic_settings')->where('name', $name)->exists();
            if (!$exists) {
                DB::table('basic_settings')->insert([
                    'name' => $name,
                    'value' => $value,
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('basic_settings')
            ->whereIn('name', [
                'tax_applies_to_nights', 'tax_applies_to_cleaning_fee', 'tax_applies_to_pet_fee',
                'tax_applies_to_extra_guest_fee', 'tax_applies_to_hottub_addon',
                'tax_applies_to_firewood_addon', 'tax_applies_to_rv_hookup_addon',
                'tax_applies_to_extra_visitor_addon',
                'discount_applies_to_nights', 'discount_applies_to_cleaning_fee',
                'discount_applies_to_pet_fee', 'discount_applies_to_extra_guest_fee',
                'discount_applies_to_hottub_addon', 'discount_applies_to_firewood_addon',
                'discount_applies_to_rv_hookup_addon', 'discount_applies_to_extra_visitor_addon',
            ])
            ->delete();
    }
};
