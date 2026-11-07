<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $database = (string) DB::connection()->getDatabaseName();
        if (DB::connection()->getDriverName() !== 'sqlite'
            || !str_starts_with($database, sys_get_temp_dir() . '/base-crm-phpunit-')) {
            throw new \RuntimeException('Tests may only run against an isolated temporary SQLite database.');
        }

        $this->seedCanonicalTestFixtures();
    }

    private function seedCanonicalTestFixtures(): void
    {
        if (!DB::table('properties')->where('id', 1)->exists()) {
            $property = (array) DB::table('properties')->orderBy('id')->first();
            if (!$property) {
                throw new \RuntimeException('The base demo migration did not create a property fixture.');
            }

            $property['id'] = 1;
            $property['name'] = 'Canonical Test Property';
            $property['seo_url'] = 'condo-1';
            $property['status'] = 'true';
            $property['is_home'] = 'false';
            $property['standard_rate'] = 200;
            $property['pms_source'] = null;
            $property['pms_room_id'] = null;
            $property['welcome_email_send_day'] = null;
            $property['checkin_email_send_day'] = null;
            $property['checkout_email_send_day'] = null;
            $property['rental_agreement_required'] = 0;
            $property['rental_agreement_signing_mode'] = 'inherit';
            $property['created_at'] = now();
            $property['updated_at'] = now();
            $columns = array_flip(array_column(\Schema::getColumns('properties'), 'name'));
            DB::table('properties')->insert(array_intersect_key($property, $columns));
        }

        if (!DB::table('booking_requests')->where('id', 1)->exists()) {
            DB::table('booking_requests')->insert([
                'id' => 1,
                'property_id' => 1,
                'checkin' => '2035-01-10',
                'checkout' => '2035-01-12',
                'total_guests' => 2,
                'adults' => 2,
                'child' => 0,
                'gross_amount' => 400,
                'total_night' => 2,
                'sub_amount' => 400,
                'total_amount' => 400,
                'after_total_fees' => '[]',
                'before_total_fees' => '[]',
                'request_id' => 'canonical-test-booking',
                'booking_status' => 'pending-payment',
                'payment_status' => 'pending',
                'name' => 'Test Guest',
                'email' => 'canonical-booking@example.test',
                'pet' => 0,
                'quote_refresh_failed' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
