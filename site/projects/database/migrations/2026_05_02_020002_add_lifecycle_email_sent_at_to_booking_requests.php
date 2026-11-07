<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_requests', function (Blueprint $t) {
            $t->timestamp('welcome_email_sent_at')->nullable()->after('welcome_email');
            $t->timestamp('checkin_email_sent_at')->nullable()->after('checkin_email');
            $t->timestamp('checkout_email_sent_at')->nullable()->after('checkout_email');
        });
    }

    public function down(): void
    {
        Schema::table('booking_requests', function (Blueprint $t) {
            $t->dropColumn(['welcome_email_sent_at', 'checkin_email_sent_at', 'checkout_email_sent_at']);
        });
    }
};
