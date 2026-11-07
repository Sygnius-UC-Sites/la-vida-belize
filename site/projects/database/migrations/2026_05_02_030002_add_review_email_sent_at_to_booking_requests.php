<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_requests', function (Blueprint $t) {
            $t->timestamp('review_email_sent_at')->nullable()->after('review_email');
        });
    }

    public function down(): void
    {
        Schema::table('booking_requests', function (Blueprint $t) {
            $t->dropColumn('review_email_sent_at');
        });
    }
};
