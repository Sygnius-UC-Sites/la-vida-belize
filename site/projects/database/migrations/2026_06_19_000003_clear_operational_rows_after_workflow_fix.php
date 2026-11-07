<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($this->tables() as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        // Starter cleanup is intentionally not reversible.
    }

    private function tables(): array
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
};
