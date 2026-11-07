<?php

namespace Tests\Feature\Admin;

use App\Models\Pms\PmsRoom;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsRoomRentalAgreementTest extends TestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId = DB::table('users')->insertGetId([
            'name' => 'PMS Agreement Admin',
            'email' => 'pms-agreement-admin-' . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function actor(): User
    {
        return User::findOrFail($this->userId);
    }

    public function test_owner_can_enable_website_managed_agreement_for_pms_room(): void
    {
        $room = PmsRoom::create([
            'pms_property_id' => 'admin-agreement-property',
            'pms_room_id' => 'admin-agreement-room',
            'pms_source' => 'lodgify',
            'name' => 'Admin Agreement PMS Room',
            'seo_url' => 'admin-agreement-pms-room',
            'status' => 'true',
            'currency' => 'USD',
        ]);

        $response = $this->actingAs($this->actor())
            ->get('/admin/pms_rooms/' . $room->id . '/edit');

        $response->assertOk();
        $response->assertSee('Use website-managed rental agreement for this PMS listing');

        $updatedSlug = 'admin-agreement-pms-room-updated';
        $update = $this->actingAs($this->actor())
            ->put('/admin/pms_rooms/' . $room->id, [
                'seo_url' => $updatedSlug,
                'status' => 'true',
                'is_home' => 'false',
                'rental_agreement_required' => '1',
                'rental_agreement_signing_mode' => 'post_confirmation',
                'rental_agreement_template_html' => '<p>PMS room agreement for {{guest_name}}</p>',
                'rental_agreement_house_rules_html' => '<p>No late checkout.</p>',
            ]);

        $update->assertRedirect();
        $update->assertSessionMissing('errors');

        $this->assertDatabaseHas('pms_rooms', [
            'id' => $room->id,
            'seo_url' => $updatedSlug,
            'rental_agreement_required' => 1,
            'rental_agreement_signing_mode' => 'post_confirmation',
            'rental_agreement_template_html' => '<p>PMS room agreement for {{guest_name}}</p>',
            'rental_agreement_house_rules_html' => '<p>No late checkout.</p>',
        ]);
    }

    public function test_owner_can_leave_pms_agreement_handling_disabled(): void
    {
        $room = PmsRoom::create([
            'pms_property_id' => 'admin-agreement-off-property',
            'pms_room_id' => 'admin-agreement-off-room',
            'pms_source' => 'hostfully',
            'name' => 'PMS Agreement Off Room',
            'seo_url' => 'admin-agreement-off-room',
            'status' => 'true',
            'rental_agreement_required' => 1,
            'rental_agreement_signing_mode' => 'post_confirmation',
        ]);

        $response = $this->actingAs($this->actor())
            ->put('/admin/pms_rooms/' . $room->id, [
                'seo_url' => 'admin-agreement-off-room',
                'status' => 'true',
                'is_home' => 'false',
                'rental_agreement_required' => '0',
                'rental_agreement_signing_mode' => '',
            ]);

        $response->assertRedirect();
        $response->assertSessionMissing('errors');

        $this->assertDatabaseHas('pms_rooms', [
            'id' => $room->id,
            'rental_agreement_required' => 0,
            'rental_agreement_signing_mode' => 'inherit',
        ]);
    }
}
