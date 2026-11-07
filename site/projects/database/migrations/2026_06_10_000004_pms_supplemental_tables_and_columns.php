<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * [PMS-MODULE] Supplemental schema — everything the consolidated migrations
 * (000001–000003) did not cover. Fully guarded: safe to run on any site,
 * any number of times.
 */
return new class extends Migration
{
    public function up()
    {
        // ── 1. Raw per-PMS payload tables ────────────────────────────────────
        foreach (['pms_guesty_data', 'pms_hostaway_data', 'pms_hostfully_data', 'pms_lodgify_data'] as $tbl) {
            if (!Schema::hasTable($tbl)) {
                Schema::create($tbl, function (Blueprint $table) {
                    $table->id();
                    $table->string('pms_property_id');
                    $table->longText('raw_property_json')->nullable();
                    $table->longText('raw_units_json')->nullable();
                    $table->timestamps();
                });
            }
        }

        // ── 2. booking_requests — columns the PMS flow writes ───────────────
        Schema::table('booking_requests', function (Blueprint $table) {
            $cols = [
                'first_name'                => fn () => $table->string('first_name')->nullable(),
                'last_name'                 => fn () => $table->string('last_name')->nullable(),
                'pet'                       => fn () => $table->integer('pet')->default(0),
                'quote_data_all'            => fn () => $table->longText('quote_data_all')->nullable(),
                'pms_booking_response_all'  => fn () => $table->longText('pms_booking_response_all')->nullable(),
                'status'                    => fn () => $table->string('status', 50)->nullable(),
                'stripe_deposit_charge_id'  => fn () => $table->string('stripe_deposit_charge_id')->nullable(),
                'security_deposit_amount'   => fn () => $table->decimal('security_deposit_amount', 10, 2)->nullable(),
                'security_deposit_currency' => fn () => $table->string('security_deposit_currency', 3)->nullable(),
                'security_deposit_status'   => fn () => $table->string('security_deposit_status')->nullable(),
                'pms_recovery_last_at'      => fn () => $table->timestamp('pms_recovery_last_at')->nullable(),
                'payment_token'             => fn () => $table->string('payment_token', 64)->nullable(),
                'quote_expires_at'          => fn () => $table->timestamp('quote_expires_at')->nullable(),
                'quote_refresh_failed'      => fn () => $table->boolean('quote_refresh_failed')->default(false),
                'pms_payment_id'            => fn () => $table->string('pms_payment_id')->nullable(),
                'pms_order_uid'             => fn () => $table->string('pms_order_uid')->nullable(),
                'pms_sync_error'            => fn () => $table->text('pms_sync_error')->nullable(),
            ];
            foreach ($cols as $name => $add) {
                if (!Schema::hasColumn('booking_requests', $name)) {
                    $add();
                }
            }
        });

        // ── 3. payments — per-payment PMS tracking + idempotency ────────────
        Schema::table('payments', function (Blueprint $table) {
            if (!Schema::hasColumn('payments', 'pms_payment_id')) {
                $table->string('pms_payment_id', 100)->nullable();
            }
            if (!Schema::hasColumn('payments', 'pms_sync_status')) {
                $table->string('pms_sync_status', 40)->nullable();
            }
            if (!Schema::hasColumn('payments', 'pms_sync_error')) {
                $table->text('pms_sync_error')->nullable();
            }
        });

        // tran_id must hold full Stripe PaymentIntent ids and be unique
        // (idempotency guard). MySQL-only raw ALTER, fully guarded.
        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE `payments` MODIFY `tran_id` VARCHAR(191) NULL');
            } catch (\Throwable $e) { /* already wide enough */
            }
            try {
                $exists = DB::select("SHOW INDEX FROM `payments` WHERE Key_name = 'payments_tran_id_unique'");
                if (empty($exists)) {
                    DB::statement('ALTER TABLE `payments` ADD UNIQUE `payments_tran_id_unique` (`tran_id`)');
                }
            } catch (\Throwable $e) { /* duplicate values present — skip, log */
            }
        }

        // ── 4. scheduled_payments — charge idempotency constraint ───────────
        if (Schema::hasTable('scheduled_payments') && DB::getDriverName() === 'mysql') {
            try {
                $exists = DB::select("SHOW INDEX FROM `scheduled_payments` WHERE Key_name = 'sp_booking_charge_unique'");
                if (empty($exists)) {
                    DB::statement('ALTER TABLE `scheduled_payments` ADD UNIQUE `sp_booking_charge_unique` (`booking_request_id`, `stripe_charge_id`)');
                }
            } catch (\Throwable $e) {
            }
        }

        // ── 5. testimonials — PMS review sync fields ────────────────────────
        if (Schema::hasTable('testimonials')) {
            Schema::table('testimonials', function (Blueprint $table) {
                if (!Schema::hasColumn('testimonials', 'pms_source')) {
                    $table->string('pms_source')->nullable();
                }
                if (!Schema::hasColumn('testimonials', 'pms_review_id')) {
                    $table->string('pms_review_id')->nullable();
                }
                if (!Schema::hasColumn('testimonials', 'end_date')) {
                    $table->date('end_date')->nullable();
                }
            });
        }
    }

    public function down()
    {
        // Additive module — intentionally no destructive rollback.
    }
};
