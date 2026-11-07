<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of the rental-agreement rebuild
 * (plan: docs/superpowers/plans/2026-05-08-rental-agreement-rebuild.md).
 *
 * Adds:
 *   • properties.rental_agreement_signing_mode — enum: inherit | pre_payment | post_confirmation.
 *     "inherit" means "use the global setting" (default).
 *   • properties.rental_agreement_source_doc_path — saved path of the .docx the
 *     owner uploaded (Phase 2 will populate this; here we just add the column).
 *   • booking_requests.rental_agreement_html_snapshot — the merged HTML the guest
 *     actually signed, captured at signing time so we have a permanent audit
 *     trail even if the property's template changes later.
 *
 * Plus a BasicSetting row `rental_agreement_signing_mode_global` defaulting to
 * 'pre_payment' so today's behaviour is preserved exactly.
 *
 * Strictly additive — existing data untouched.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            // Position after the existing rental_agreement_required column so
            // related fields cluster together when admins inspect the schema.
            if (!Schema::hasColumn('properties', 'rental_agreement_signing_mode')) {
                $table->enum('rental_agreement_signing_mode', ['inherit', 'pre_payment', 'post_confirmation'])
                    ->default('inherit')
                    ->after('rental_agreement_required');
            }
            if (!Schema::hasColumn('properties', 'rental_agreement_source_doc_path')) {
                // Path is relative to the htdocs root (matches the convention
                // Upload::fileUpload uses for every other uploaded asset).
                $table->string('rental_agreement_source_doc_path', 500)
                    ->nullable()
                    ->after('rental_aggrement_attachment');
            }
        });

        Schema::table('booking_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('booking_requests', 'rental_agreement_html_snapshot')) {
                // longtext — agreements with embedded images / long house-rules
                // can easily exceed text's 64 KB ceiling.
                $table->longText('rental_agreement_html_snapshot')
                    ->nullable()
                    ->after('rental_agreement_consent_text');
            }
        });

        // Seed the global signing-mode setting at the current behaviour
        // (pre_payment) so nothing visibly changes for installs that ran the
        // old code. Phase 4 wires the admin UI to flip this value.
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'rental_agreement_signing_mode_global'],
            [
                'value' => 'pre_payment',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (Schema::hasColumn('properties', 'rental_agreement_source_doc_path')) {
                $table->dropColumn('rental_agreement_source_doc_path');
            }
            if (Schema::hasColumn('properties', 'rental_agreement_signing_mode')) {
                $table->dropColumn('rental_agreement_signing_mode');
            }
        });

        Schema::table('booking_requests', function (Blueprint $table) {
            if (Schema::hasColumn('booking_requests', 'rental_agreement_html_snapshot')) {
                $table->dropColumn('rental_agreement_html_snapshot');
            }
        });

        DB::table('basic_settings')->where('name', 'rental_agreement_signing_mode_global')->delete();
    }
};
