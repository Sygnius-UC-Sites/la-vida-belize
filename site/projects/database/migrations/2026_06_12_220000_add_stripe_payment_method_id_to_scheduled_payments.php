<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The scheduled_payments table on this site was created by the module's
 * 2026_06_07 migration, which predates the stripe_payment_method_id column
 * (added later for PaymentIntent-based off-session balance charges and
 * present in the model's fillable + the newer create-table definition).
 *
 * Without it, StripeController::saveInstallments() throws on insert — the
 * error is caught and only logged, so every balance installment was
 * silently dropped and the auto-charge scheduler had nothing to charge.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scheduled_payments')
            && !Schema::hasColumn('scheduled_payments', 'stripe_payment_method_id')) {
            Schema::table('scheduled_payments', function (Blueprint $table) {
                $table->string('stripe_payment_method_id', 50)->nullable()->after('stripe_customer_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('scheduled_payments')
            && Schema::hasColumn('scheduled_payments', 'stripe_payment_method_id')) {
            Schema::table('scheduled_payments', function (Blueprint $table) {
                $table->dropColumn('stripe_payment_method_id');
            });
        }
    }
};
