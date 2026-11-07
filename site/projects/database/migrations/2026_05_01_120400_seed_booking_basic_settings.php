<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $rows = [
            // Agency-controlled (D2)
            ['name' => 'enabled_payment_gateways', 'value' => 'stripe'],

            // Installment defaults (D11/D17)
            ['name' => 'installment_plan',              'value' => 'full'],
            ['name' => 'installment_2_days',            'value' => '30'],
            ['name' => 'installment_2_percent',         'value' => '35'],
            ['name' => 'installment_2_min_lead_days',   'value' => '46'],
            ['name' => 'installment_3_days',            'value' => '15'],
            ['name' => 'installment_3_percent',         'value' => '20'],
            ['name' => 'installment_3_min_lead_days',   'value' => '115'],

            // Tax base defaults (D20)
            ['name' => 'tax_applies_to_nights',                'value' => '1'],
            ['name' => 'tax_applies_to_cleaning_fee',          'value' => '1'],
            ['name' => 'tax_applies_to_pet_fee',               'value' => '1'],
            ['name' => 'tax_applies_to_extra_guest_fee',       'value' => '1'],
            ['name' => 'tax_applies_to_hottub_addon',          'value' => '0'],
            ['name' => 'tax_applies_to_firewood_addon',        'value' => '0'],
            ['name' => 'tax_applies_to_rv_hookup_addon',       'value' => '0'],
            ['name' => 'tax_applies_to_extra_visitor_addon',   'value' => '0'],

            // Discount base defaults (D21) — only nights by default
            ['name' => 'discount_applies_to_nights',                'value' => '1'],
            ['name' => 'discount_applies_to_cleaning_fee',          'value' => '0'],
            ['name' => 'discount_applies_to_pet_fee',               'value' => '0'],
            ['name' => 'discount_applies_to_extra_guest_fee',       'value' => '0'],
            ['name' => 'discount_applies_to_hottub_addon',          'value' => '0'],
            ['name' => 'discount_applies_to_firewood_addon',        'value' => '0'],
            ['name' => 'discount_applies_to_rv_hookup_addon',       'value' => '0'],
            ['name' => 'discount_applies_to_extra_visitor_addon',   'value' => '0'],
        ];

        $now = now();
        foreach ($rows as $row) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $row['name']],
                ['value' => $row['value'], 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('basic_settings')->whereIn('name', [
            'enabled_payment_gateways',
            'installment_plan', 'installment_2_days', 'installment_2_percent', 'installment_2_min_lead_days',
            'installment_3_days', 'installment_3_percent', 'installment_3_min_lead_days',
            'tax_applies_to_nights', 'tax_applies_to_cleaning_fee', 'tax_applies_to_pet_fee',
            'tax_applies_to_extra_guest_fee', 'tax_applies_to_hottub_addon',
            'tax_applies_to_firewood_addon', 'tax_applies_to_rv_hookup_addon',
            'tax_applies_to_extra_visitor_addon',
            'discount_applies_to_nights', 'discount_applies_to_cleaning_fee',
            'discount_applies_to_pet_fee', 'discount_applies_to_extra_guest_fee',
            'discount_applies_to_hottub_addon', 'discount_applies_to_firewood_addon',
            'discount_applies_to_rv_hookup_addon', 'discount_applies_to_extra_visitor_addon',
        ])->delete();
    }
};
