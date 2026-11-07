<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->integer('max_uses')->nullable();
            $table->integer('max_uses_per_customer')->nullable()->default(1);
            $table->decimal('min_order_amount', 8, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            $table->dropColumn(['max_uses', 'max_uses_per_customer', 'min_order_amount']);
        });
    }
};
