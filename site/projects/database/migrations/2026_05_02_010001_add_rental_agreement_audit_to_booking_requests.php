<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('booking_requests', function (Blueprint $t) {
            $t->string('rental_agreement_pdf_path', 500)->nullable()->after('rental_agreement_link');
            $t->timestamp('rental_agreement_signed_at')->nullable()->after('rental_agreement_pdf_path');
            $t->string('rental_agreement_signed_ip', 45)->nullable()->after('rental_agreement_signed_at');
            $t->string('rental_agreement_signed_user_agent', 500)->nullable()->after('rental_agreement_signed_ip');
            $t->text('rental_agreement_consent_text')->nullable()->after('rental_agreement_signed_user_agent');
        });
    }

    public function down(): void
    {
        Schema::table('booking_requests', function (Blueprint $t) {
            $t->dropColumn([
                'rental_agreement_pdf_path',
                'rental_agreement_signed_at',
                'rental_agreement_signed_ip',
                'rental_agreement_signed_user_agent',
                'rental_agreement_consent_text',
            ]);
        });
    }
};
