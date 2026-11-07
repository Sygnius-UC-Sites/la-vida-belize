<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($this->emptyTables() as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $now = now();

        if (Schema::hasTable('users')) {
            DB::table('users')->where('email', '!=', 'info@sygniusdigital.com')->delete();
            DB::table('users')->updateOrInsert(
                ['email' => 'info@sygniusdigital.com'],
                [
                    'name' => 'Sygnius Digital',
                    'password' => Hash::make('sygnius@123'),
                    'is_developer' => 1,
                    'email_verified_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $this->clearSensitiveSettings();
        $this->setGenericSettings();
    }

    public function down(): void
    {
        // Starter cleanup is intentionally not reversible.
    }

    private function emptyTables(): array
    {
        return [
            'activity_log',
            'booking_requests',
            'common_booking_requests',
            'contactus_requests',
            'email_send_log',
            'failed_jobs',
            'ical_events',
            'ical_import_list',
            'job_batches',
            'jobs',
            'lodgify_rate_calendar',
            'mail_health_events',
            'mail_send_log',
            'ops_runs',
            'payment_refunds',
            'payments',
            'personal_access_tokens',
            'pms_amenity_group_items',
            'pms_amenity_groups',
            'pms_availability_cache',
            'pms_booking_links',
            'pms_bookings',
            'pms_calendar',
            'pms_calendar_discrepancies',
            'pms_guesty_data',
            'pms_hostaway_data',
            'pms_hostfully_data',
            'pms_images',
            'pms_lodgify_data',
            'pms_lodgify_fees',
            'pms_lodgify_properties',
            'pms_lodgify_rooms',
            'pms_lodgify_taxes',
            'pms_properties',
            'pms_property_links',
            'pms_rate_cache',
            'pms_rates',
            'pms_rooms',
            'pms_sync_log',
            'pms_unavailable_dates',
            'pms_webhook_log',
            'properties',
            'properties_rates_group',
            'property_category_property',
            'property_fees',
            'property_galleries',
            'property_highlights',
            'property_rates',
            'property_reviews',
            'property_room_items',
            'property_rooms',
            'property_sleep_cards',
            'property_spaces',
            'scheduled_payments',
            'scrape_runs',
            'scrape_sources',
            'testimonials',
            'attractions',
            'attraction_categories',
        ];
    }

    private function clearSensitiveSettings(): void
    {
        if (!Schema::hasTable('basic_settings')) {
            return;
        }

        $clearNames = [
            'AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY',
            'AUTHORIZED_MERCHANT_TRANSACTION_KEY',
            'AUTHORIZED_MERCHANT_LOGIN_ID',
            'AUTHORIZED_ENV',
            'google_captcha_secret_key',
            'google_captcha_site_key',
            'google_map_access_token',
            'guesty_api_key',
            'guesty_client_id',
            'guesty_webhook_token',
            'hostaway_account_id',
            'hostaway_api_key',
            'hostaway_webhook_token',
            'hostfully_agency_uid',
            'hostfully_api_key',
            'hostfully_webhook_token',
            'importer_api_token',
            'importer_webhook_secret',
            'lodgify_api_key',
            'lodgify_api_token',
            'lodgify_webhook_secret',
            'lodgify_webhook_token',
            'paypal_access_token',
            'square_ACCESS_TOKEN',
            'square_LOCATION_ID',
            'stripe_webhook_secret',
            'smtp_host',
            'smtp_pass',
            'smtp_port',
            'smtp_user',
        ];

        DB::table('basic_settings')
            ->whereIn('name', $clearNames)
            ->update(['value' => '', 'updated_at' => now()]);
    }

    private function setGenericSettings(): void
    {
        if (!Schema::hasTable('basic_settings')) {
            return;
        }

        $settings = [
            'website' => 'Base Vacation Rentals',
            'email' => 'hello@example.com',
            'mobile' => '555-0100',
            'mobile1' => '',
            'mobile2' => '',
            'whatsapp_number' => '',
            'whatsapp_url' => '',
            'address' => 'Your Destination',
            'owner_name' => 'Demo Hosts',
            'mail_from_name' => 'Base Vacation Rentals',
            'pms_driver' => '',
            'pms_source' => '',
            'lodgify_health_status' => '',
            'lodgify_last_health_check_at' => '',
            'lodgify_consecutive_failures' => '0',
            'stripe_publish_key' => 'pk_test_YTuklIXpPf0UvOjLxew4tgIf',
            'stripe_secret_key' => '',
        ];

        foreach ($settings as $name => $value) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }
};
