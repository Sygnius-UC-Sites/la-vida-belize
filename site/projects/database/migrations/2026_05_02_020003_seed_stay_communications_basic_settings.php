<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $welcomeDescription = '<p>Welcome to <strong>{{property_name}}</strong>, {{guest_name}}! Your stay starts on <strong>{{check_in}}</strong>. We can\'t wait to host you. If you have questions, your host {{host_name}} can be reached at <a href="tel:{{host_phone}}">{{host_phone}}</a>.</p>';

        $defaults = [
            'welcome_default_description'         => $welcomeDescription,
            'welcome_default_attachment_path'     => '',
            'checkin_default_arrival_window'      => 'Any time after 4:00 PM',
            'checkin_default_door_code'           => '',
            'checkin_default_wifi_name'           => '',
            'checkin_default_wifi_password'       => '',
            'checkin_default_parking_instructions' => '<p>Park in the marked spot under the carport.</p>',
            'checkin_default_host_name'           => 'Legacy Vacation Rentals Concierge',
            'checkin_default_host_phone'          => '',
            'checkin_default_emergency_phone'     => '911 (or local equivalent)',
            'checkin_default_notes_html'          => '',
            'checkout_default_departure_time'     => 'By 11:00 AM',
            'checkout_default_key_return'         => '<p>Leave the key in the lockbox using the same code.</p>',
            'checkout_default_cleaning_checklist' => '<ul><li>Empty the trash and place outside</li><li>Start the dishwasher if used</li><li>Turn off AC and lights</li><li>Lock all doors and windows</li></ul>',
            'checkout_default_notes_html'         => '<p>Safe travels! We\'d love a review of your stay.</p>',
            'checkin_email_send_day'              => '0',
            'checkout_email_send_day'             => '0',
        ];

        foreach ($defaults as $name => $value) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('basic_settings')->whereIn('name', [
            'welcome_default_description', 'welcome_default_attachment_path',
            'checkin_default_arrival_window', 'checkin_default_door_code', 'checkin_default_wifi_name', 'checkin_default_wifi_password',
            'checkin_default_parking_instructions', 'checkin_default_host_name', 'checkin_default_host_phone', 'checkin_default_emergency_phone',
            'checkin_default_notes_html',
            'checkout_default_departure_time', 'checkout_default_key_return', 'checkout_default_cleaning_checklist', 'checkout_default_notes_html',
            'checkin_email_send_day', 'checkout_email_send_day',
        ])->delete();
    }
};
