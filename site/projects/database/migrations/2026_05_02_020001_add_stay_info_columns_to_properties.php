<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            // The free-form check-in notes column referenced by the existing Blade template
            // (booking-checkin-info.blade.php) but never actually created. Add it now so the
            // resolver can use it as the property override for the global checkin_default_notes_html.
            $t->longText('detail_check_in_instructions')->nullable()->after('checkin_day');

            // Check-in structured fields
            $t->string('checkin_arrival_window', 100)->nullable()->after('detail_check_in_instructions');
            $t->string('checkin_door_code', 100)->nullable()->after('checkin_arrival_window');
            $t->string('checkin_wifi_name', 191)->nullable()->after('checkin_door_code');
            $t->string('checkin_wifi_password', 191)->nullable()->after('checkin_wifi_name');
            $t->text('checkin_parking_instructions')->nullable()->after('checkin_wifi_password');
            $t->string('checkin_host_name', 191)->nullable()->after('checkin_parking_instructions');
            $t->string('checkin_host_phone', 50)->nullable()->after('checkin_host_name');
            $t->string('checkin_emergency_phone', 50)->nullable()->after('checkin_host_phone');

            // Check-out structured fields
            $t->string('checkout_departure_time', 100)->nullable()->after('checkin_emergency_phone');
            $t->text('checkout_key_return')->nullable()->after('checkout_departure_time');
            $t->longText('checkout_cleaning_checklist')->nullable()->after('checkout_key_return');
            $t->longText('checkout_notes')->nullable()->after('checkout_cleaning_checklist');

            // Per-property timing overrides (NULL = inherit global)
            $t->unsignedInteger('welcome_email_send_day')->nullable()->after('checkout_notes');
            $t->unsignedInteger('checkin_email_send_day')->nullable()->after('welcome_email_send_day');
            $t->unsignedInteger('checkout_email_send_day')->nullable()->after('checkin_email_send_day');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $t) {
            $t->dropColumn([
                'detail_check_in_instructions',
                'checkin_arrival_window', 'checkin_door_code', 'checkin_wifi_name', 'checkin_wifi_password',
                'checkin_parking_instructions', 'checkin_host_name', 'checkin_host_phone', 'checkin_emergency_phone',
                'checkout_departure_time', 'checkout_key_return', 'checkout_cleaning_checklist', 'checkout_notes',
                'welcome_email_send_day', 'checkin_email_send_day', 'checkout_email_send_day',
            ]);
        });
    }
};
