<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_request_id');
            $table->string('pms_source')->nullable();
            $table->string('pms_booking_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('USD');
            $table->date('due_date');
            $table->string('status')->default('pending'); // pending, charged, failed, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('booking_request_id');
            $table->index(['status', 'due_date']);
        });

        // Add PMS booking columns to booking_requests if not already present
        if (Schema::hasTable('booking_requests')) {
            Schema::table('booking_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('booking_requests', 'pms_source')) {
                    $table->string('pms_source')->nullable()->after('id');
                }
                if (!Schema::hasColumn('booking_requests', 'pms_booking_id')) {
                    $table->string('pms_booking_id')->nullable()->after('pms_source');
                }
                if (!Schema::hasColumn('booking_requests', 'pms_booking_response_all')) {
                    $table->text('pms_booking_response_all')->nullable();
                }
                if (!Schema::hasColumn('booking_requests', 'stripe_deposit_charge_id')) {
                    $table->string('stripe_deposit_charge_id')->nullable();
                }
                if (!Schema::hasColumn('booking_requests', 'security_deposit_amount')) {
                    $table->decimal('security_deposit_amount', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('booking_requests', 'security_deposit_currency')) {
                    $table->string('security_deposit_currency', 10)->nullable();
                }
                if (!Schema::hasColumn('booking_requests', 'security_deposit_status')) {
                    $table->string('security_deposit_status')->nullable();
                }
                if (!Schema::hasColumn('booking_requests', 'booking_status')) {
                    $table->string('booking_status')->default('pending-payment');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_payments');
    }
};
