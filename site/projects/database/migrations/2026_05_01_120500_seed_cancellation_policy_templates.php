<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $templates = [
            'cancellation_template_flexible'     => 'Free cancellation up to 24 hours before check-in. After that, no refunds.',
            'cancellation_template_moderate'     => 'Free cancellation until 5 days before check-in. After that, no refunds.',
            'cancellation_template_strict'       => 'Free cancellation until 14 days before check-in. 50% refund 7-14 days before. No refund within 7 days.',
            'cancellation_template_firm'         => 'Free cancellation until 30 days before check-in. 50% refund 14-30 days before. No refund within 14 days.',
            'cancellation_template_super_strict' => '50% refund until 60 days before check-in. No refund within 60 days.',
        ];

        $now = now();
        foreach ($templates as $name => $value) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'updated_at' => $now, 'created_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('basic_settings')->whereIn('name', [
            'cancellation_template_flexible',
            'cancellation_template_moderate',
            'cancellation_template_strict',
            'cancellation_template_firm',
            'cancellation_template_super_strict',
        ])->delete();
    }
};
