<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_send_log', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('booking_id');
            $t->string('email_type', 32);
            $t->string('recipient', 255)->nullable();
            $t->string('subject', 500)->nullable();
            $t->longText('rendered_html')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->boolean('success')->default(true);
            $t->text('error')->nullable();
            $t->timestamps();

            $t->index(['booking_id', 'email_type', 'created_at'], 'idx_booking_type_created');
            $t->foreign('booking_id')->references('id')->on('booking_requests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_send_log');
    }
};
