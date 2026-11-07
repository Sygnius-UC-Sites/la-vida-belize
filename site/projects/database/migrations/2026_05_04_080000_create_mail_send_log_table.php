<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mail_send_log', function (Blueprint $t) {
            $t->id();
            $t->string('purpose', 64)->index();             // contact | booking-enquiry | review-confirm | manual | ...
            $t->string('from_email', 255);
            $t->string('to_email', 255)->index();
            $t->string('subject', 500);
            $t->string('ses_message_id', 255)->nullable()->index();
            $t->string('status', 32)->index();              // sent | failed | suppressed | rate_limited | disabled | bounced | complained
            $t->string('error_code', 64)->nullable();
            $t->text('error_message')->nullable();
            $t->boolean('retry_eligible')->default(false);
            $t->unsignedTinyInteger('retry_count')->default(0);
            $t->ipAddress('source_ip')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();

            $t->index(['status', 'created_at'], 'idx_status_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_send_log');
    }
};
