<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('property_fees');
        Schema::create('property_fees', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('property_id');
            $t->string('name', 191);
            $t->decimal('rate', 10, 2);
            $t->enum('billing_type', ['one_time', 'per_day'])->default('one_time');
            $t->boolean('taxable')->default(true);
            $t->boolean('applies_discount')->default(false);
            $t->integer('sort_order')->default(0);
            $t->enum('status', ['active', 'inactive'])->default('active');
            $t->timestamps();
            $t->index('property_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_fees');
        Schema::create('property_fees', function (Blueprint $t) {
            $t->id();
            $t->string('fee_name');
            $t->string('fee_rate');
            $t->string('fee_type')->default('Excat');
            $t->string('fee_apply')->default('total');
            $t->string('fee_status')->default('active');
            $t->string('property_id');
            $t->timestamps();
        });
    }
};
