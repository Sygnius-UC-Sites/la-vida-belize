<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            $t->longText('rental_agreement_template_html')->nullable()->after('rental_agreement_required');
            $t->longText('rental_agreement_house_rules_html')->nullable()->after('rental_agreement_template_html');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            $t->dropColumn(['rental_agreement_template_html', 'rental_agreement_house_rules_html']);
        });
    }
};
