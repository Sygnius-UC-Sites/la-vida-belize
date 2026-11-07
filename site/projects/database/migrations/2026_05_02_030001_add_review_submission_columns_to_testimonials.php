<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('testimonials', function (Blueprint $t) {
            $t->unsignedBigInteger('booking_id')->nullable()->after('property_id');
            $t->string('submission_token', 64)->nullable()->unique()->after('booking_id');
            $t->unsignedTinyInteger('rating')->nullable()->after('submission_token');
            $t->timestamp('submitted_at')->nullable()->after('rating');
            $t->string('submitted_ip', 45)->nullable()->after('submitted_at');
            $t->index('booking_id');
        });
    }

    public function down(): void
    {
        Schema::table('testimonials', function (Blueprint $t) {
            $t->dropIndex(['booking_id']);
            $t->dropUnique(['submission_token']);
            $t->dropColumn(['booking_id', 'submission_token', 'rating', 'submitted_at', 'submitted_ip']);
        });
    }
};
