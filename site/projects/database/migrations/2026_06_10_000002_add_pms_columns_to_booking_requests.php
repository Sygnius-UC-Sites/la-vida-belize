<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add PMS and PaymentIntents columns to booking_requests.
 * Each column is guarded with hasColumn() — safe to re-run.
 */
return new class extends Migration
{
    private array $columns = [
        'pms_source'                  => ['string', 30, null],
        'pms_room_id'                 => ['string', null, null],
        'pms_booking_id'              => ['string', null, null],
        'pms_recovery_attempts'       => ['unsignedTinyInteger', null, 0],
        'pms_sync_status'             => ['string', 40, 'pending'],
        'infants'                     => ['integer', null, 0],
        'currency'                    => ['string', 10, 'USD'],
        'security_deposit'            => ['decimal', [10, 2], null],
        'deposit_due_amount'          => ['decimal', [10, 2], null],
        'optional_services'           => ['longText', null, null],
        'requested_services'          => ['longText', null, null],
        'quoted_fees_snapshot'        => ['longText', null, null],
        'price_changed_to'            => ['decimal', [10, 2], null],
        'price_changed_at'            => ['timestamp', null, null],
        'payment_intent_id'           => ['string', 50, null],
        'stripe_payment_intent_id'    => ['string', 50, null],
        'stripe_refund_id'            => ['string', 100, null],
        'deposit_payment_intent_id'   => ['string', 50, null],
        'deposit_paid_at'             => ['timestamp', null, null],
        'balance_payment_intent_id'   => ['string', 50, null],
        'balance_paid_at'             => ['timestamp', null, null],
    ];

    public function up(): void
    {
        Schema::table('booking_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_requests', 'pms_source')) {
                $table->string('pms_source', 30)->nullable()->after('id');
            }
            if (!Schema::hasColumn('booking_requests', 'pms_room_id')) {
                $table->string('pms_room_id')->nullable()->after('pms_source');
            }
            if (!Schema::hasColumn('booking_requests', 'pms_booking_id')) {
                $table->string('pms_booking_id')->nullable()->after('pms_room_id');
            }
            if (!Schema::hasColumn('booking_requests', 'pms_recovery_attempts')) {
                $table->unsignedTinyInteger('pms_recovery_attempts')->default(0)->nullable()->after('pms_booking_id');
            }
            if (!Schema::hasColumn('booking_requests', 'pms_sync_status')) {
                $table->string('pms_sync_status', 40)->nullable()->after('pms_recovery_attempts');
            }
            if (!Schema::hasColumn('booking_requests', 'infants')) {
                $table->integer('infants')->default(0)->nullable()->after('child');
            }
            if (!Schema::hasColumn('booking_requests', 'currency')) {
                $table->string('currency', 10)->nullable()->default('USD');
            }
            if (!Schema::hasColumn('booking_requests', 'security_deposit')) {
                $table->decimal('security_deposit', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('booking_requests', 'deposit_due_amount')) {
                $table->decimal('deposit_due_amount', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('booking_requests', 'optional_services')) {
                $table->longText('optional_services')->nullable();
            }
            if (!Schema::hasColumn('booking_requests', 'requested_services')) {
                $table->longText('requested_services')->nullable();
            }
            if (!Schema::hasColumn('booking_requests', 'quoted_fees_snapshot')) {
                $table->longText('quoted_fees_snapshot')->nullable();
            }
            if (!Schema::hasColumn('booking_requests', 'price_changed_to')) {
                $table->decimal('price_changed_to', 10, 2)->nullable();
            }
            if (!Schema::hasColumn('booking_requests', 'price_changed_at')) {
                $table->timestamp('price_changed_at')->nullable();
            }
            if (!Schema::hasColumn('booking_requests', 'payment_intent_id')) {
                $table->string('payment_intent_id', 50)->nullable();
            }
            if (!Schema::hasColumn('booking_requests', 'stripe_payment_intent_id')) {
                $table->string('stripe_payment_intent_id', 50)->nullable();
            }
            if (!Schema::hasColumn('booking_requests', 'stripe_refund_id')) {
                $table->string('stripe_refund_id', 100)->nullable();
            }
            if (!Schema::hasColumn('booking_requests', 'deposit_payment_intent_id')) {
                $table->string('deposit_payment_intent_id', 50)->nullable();
            }
            if (!Schema::hasColumn('booking_requests', 'deposit_paid_at')) {
                $table->timestamp('deposit_paid_at')->nullable();
            }
            if (!Schema::hasColumn('booking_requests', 'balance_payment_intent_id')) {
                $table->string('balance_payment_intent_id', 50)->nullable();
            }
            if (!Schema::hasColumn('booking_requests', 'balance_paid_at')) {
                $table->timestamp('balance_paid_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        $cols = [
            'pms_source', 'pms_room_id', 'pms_booking_id', 'pms_recovery_attempts',
            'pms_sync_status', 'infants', 'currency', 'security_deposit', 'deposit_due_amount',
            'optional_services', 'requested_services', 'quoted_fees_snapshot',
            'price_changed_to', 'price_changed_at', 'payment_intent_id',
            'stripe_payment_intent_id', 'stripe_refund_id', 'deposit_payment_intent_id',
            'deposit_paid_at', 'balance_payment_intent_id', 'balance_paid_at',
        ];
        Schema::table('booking_requests', function (Blueprint $table) use ($cols) {
            foreach ($cols as $col) {
                if (Schema::hasColumn('booking_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
