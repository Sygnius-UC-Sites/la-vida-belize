<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    public function test_notification_feed_resolves_regular_and_pms_property_names(): void
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Notification Test Admin',
            'email' => 'notification-admin@example.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roomId = DB::table('pms_rooms')->insertGetId([
            'pms_property_id' => 'pms-property-test',
            'pms_room_id' => 'pms-room-test',
            'pms_source' => 'lodgify',
            'name' => 'PMS Notification Property',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('booking_requests')->insert([
            'property_id' => $roomId,
            'pms_source' => 'lodgify',
            'checkin' => '2036-01-10',
            'checkout' => '2036-01-12',
            'total_night' => 2,
            'booking_status' => 'booking-confirmed',
            'name' => 'PMS Notification Guest',
            'email' => 'pms-notification@example.test',
            'request_id' => 'pms-notification-test',
            'created_at' => now()->addSecond(),
            'updated_at' => now()->addSecond(),
        ]);

        $response = $this->actingAs(User::findOrFail($userId))
            ->getJson('/admin/api/notifications');

        $response->assertOk()
            ->assertJsonFragment([
                'title' => 'PMS Notification Guest (booking confirmed)',
                'subtitle' => 'PMS Notification Property, Jan 10 - Jan 12, 2 nights',
            ]);
    }
}
