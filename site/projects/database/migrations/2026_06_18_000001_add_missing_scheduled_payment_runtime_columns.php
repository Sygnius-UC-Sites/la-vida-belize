<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('scheduled_payments')) {
            return;
        }

        Schema::table('scheduled_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('scheduled_payments', 'pms_payment_id')) {
                $table->string('pms_payment_id', 100)->nullable()->after('pms_notify_error');
            }

            if (!Schema::hasColumn('scheduled_payments', 'reminder_sent')) {
                $table->boolean('reminder_sent')->default(false)->after('pms_payment_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('scheduled_payments')) {
            return;
        }

        Schema::table('scheduled_payments', function (Blueprint $table) {
            if (Schema::hasColumn('scheduled_payments', 'reminder_sent')) {
                $table->dropColumn('reminder_sent');
            }

            if (Schema::hasColumn('scheduled_payments', 'pms_payment_id')) {
                $table->dropColumn('pms_payment_id');
            }
        });
    }
};
