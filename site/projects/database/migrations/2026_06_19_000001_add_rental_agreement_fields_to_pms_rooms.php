<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pms_rooms')) {
            return;
        }

        Schema::table('pms_rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('pms_rooms', 'rental_agreement_required')) {
                $table->boolean('rental_agreement_required')->default(false)->after('booking_policy');
            }
            if (!Schema::hasColumn('pms_rooms', 'rental_agreement_signing_mode')) {
                $table->string('rental_agreement_signing_mode', 30)->default('inherit')->after('rental_agreement_required');
            }
            if (!Schema::hasColumn('pms_rooms', 'rental_agreement_template_html')) {
                $table->longText('rental_agreement_template_html')->nullable()->after('rental_agreement_signing_mode');
            }
            if (!Schema::hasColumn('pms_rooms', 'rental_agreement_house_rules_html')) {
                $table->longText('rental_agreement_house_rules_html')->nullable()->after('rental_agreement_template_html');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pms_rooms')) {
            return;
        }

        Schema::table('pms_rooms', function (Blueprint $table) {
            foreach ([
                'rental_agreement_house_rules_html',
                'rental_agreement_template_html',
                'rental_agreement_signing_mode',
                'rental_agreement_required',
            ] as $column) {
                if (Schema::hasColumn('pms_rooms', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
