<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            // Email type slug — same vocabulary used by previewEmail / SendLog:
            // 'welcome', 'checkin', 'checkout', 'review', 'cancellation',
            // 'awaiting-payment', 'confirmed', 'enquiry', 'installment2',
            // 'installment3', 'receipt'.
            $table->string('email_type', 40)->index();
            // Path relative to the htdocs root (matches Upload::fileUpload return).
            $table->string('file_path');
            // Original filename — what the admin sees in the manager + what
            // the recipient's mail client shows when they download the file.
            $table->string('original_name');
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
    }
};
