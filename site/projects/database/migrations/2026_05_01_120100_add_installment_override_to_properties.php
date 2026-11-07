<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->enum('installment_plan', ['full', 'two', 'three'])->nullable();
            $table->integer('installment_2_days')->nullable();
            $table->integer('installment_2_percent')->nullable();
            $table->integer('installment_2_min_lead_days')->nullable();
            $table->integer('installment_3_days')->nullable();
            $table->integer('installment_3_percent')->nullable();
            $table->integer('installment_3_min_lead_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'installment_plan',
                'installment_2_days', 'installment_2_percent', 'installment_2_min_lead_days',
                'installment_3_days', 'installment_3_percent', 'installment_3_min_lead_days',
            ]);
        });
    }
};
