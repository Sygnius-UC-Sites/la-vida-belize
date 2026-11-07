<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $pages = [
            ['name' => 'Checkout', 'seo_url' => 'checkout', 'templete' => 'checkout', 'description' => 'Complete your booking.'],
            ['name' => 'Payment Successful', 'seo_url' => 'payment-success', 'templete' => 'payment-success', 'description' => 'Thanks — your payment has been received.'],
            ['name' => 'Payment Failed', 'seo_url' => 'payment-failed', 'templete' => 'payment-failed', 'description' => 'Sorry, your payment did not go through. Please try again.'],
            ['name' => 'Complete Your Payment', 'seo_url' => 'magic-link-payment', 'templete' => 'magic-link-payment', 'description' => 'Your booking has been approved — complete your payment below.'],
            ['name' => 'Link Expired', 'seo_url' => 'magic-link-expired', 'templete' => 'magic-link-expired', 'description' => 'This payment link has expired.'],
            ['name' => 'Booking Confirmed', 'seo_url' => 'booking-confirmed-instant', 'templete' => 'booking-confirmed', 'description' => 'Your booking is confirmed.'],
            ['name' => 'Request Received', 'seo_url' => 'booking-request-received', 'templete' => 'booking-request-received', 'description' => 'We received your booking request and will get back to you within 24 hours.'],
        ];

        $now = now();
        foreach ($pages as $row) {
            DB::table('cms')->updateOrInsert(
                ['seo_url' => $row['seo_url']],
                array_merge($row, ['publish' => 'published', 'updated_at' => $now, 'created_at' => $now])
            );
        }
    }

    public function down(): void
    {
        DB::table('cms')->whereIn('seo_url', [
            'checkout', 'payment-success', 'payment-failed', 'magic-link-payment',
            'magic-link-expired', 'booking-confirmed-instant', 'booking-request-received',
        ])->delete();
    }
};
