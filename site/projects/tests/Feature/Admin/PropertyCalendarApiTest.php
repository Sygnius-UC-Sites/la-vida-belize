<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyCalendarApiTest extends TestCase
{
    private int $propertyId;
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->propertyId = DB::table('properties')->insertGetId([
            'name'                   => '_test_cal_property_',
            'seo_url'                => '_test-cal-' . uniqid(),
            'instant_booking_button' => '',
            'status'                 => 'true',
            'standard_rate'          => 200,
            'min_stay'               => 2,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->userId = DB::table('users')->insertGetId([
            'name'       => '_test_cal_user_',
            'email'      => '_test_cal_' . uniqid() . '@example.com',
            'password'   => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        DB::table('property_rates')->where('property_id', $this->propertyId)->delete();
        DB::table('properties_rates_group')->where('property_id', $this->propertyId)->delete();
        DB::table('ical_events')->where('event_pid', $this->propertyId)->delete();
        DB::table('ical_import_list')->where('property_id', $this->propertyId)->delete();
        DB::table('booking_requests')->where('property_id', $this->propertyId)->delete();
        DB::table('properties')->where('id', $this->propertyId)->delete();
        DB::table('users')->where('id', $this->userId)->delete();
        parent::tearDown();
    }

    private function actor(): User { return User::findOrFail($this->userId); }

    public function test_days_returns_default_price_when_no_rules(): void
    {
        $from = now()->toDateString();
        $to = now()->addDays(2)->toDateString();
        $resp = $this->actingAs($this->actor())
            ->getJson('/admin/properties/' . $this->propertyId . '/calendar/days?from=' . $from . '&to=' . $to);
        $resp->assertOk();
        $days = $resp->json('days');
        $this->assertCount(3, $days);
        $this->assertSame(200, $days[0]['price']);
        $this->assertSame('available', $days[0]['status']);
        $this->assertSame('default', $days[0]['source']);
    }

    public function test_days_resolves_range_then_override_then_booking(): void
    {
        $today = now()->startOfDay();
        $rangeStart = $today->copy()->toDateString();
        $rangeEnd = $today->copy()->addDays(10)->toDateString();
        DB::table('properties_rates_group')->insert([
            'property_id' => $this->propertyId,
            'name_of_price' => 'Test range',
            'type_of_price' => 'default',
            'start_date' => $rangeStart,
            'start_date_timestamp' => strtotime($rangeStart),
            'end_date' => $rangeEnd,
            'end_date_timestamp' => strtotime($rangeEnd),
            'price' => 350,
            'min_stay' => 3,
            'is_available' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Day-1 single override
        $overrideDate = $today->copy()->addDays(1)->toDateString();
        DB::table('property_rates')->insert([
            'property_id' => $this->propertyId,
            'single_date' => $overrideDate,
            'single_date_timestamp' => strtotime($overrideDate),
            'price' => 999,
            'is_available' => 1,
            'platform_type' => 'airbnb',
            'currency' => 'USD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Day-3 booking
        $bookCheckin = $today->copy()->addDays(3)->toDateString();
        $bookCheckout = $today->copy()->addDays(5)->toDateString();
        DB::table('booking_requests')->insert([
            'property_id' => $this->propertyId,
            'checkin' => $bookCheckin,
            'checkout' => $bookCheckout,
            'name' => 'Test Guest',
            'email' => 't@t.com',
            'booking_status' => 'booked',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->actingAs($this->actor())
            ->getJson('/admin/properties/' . $this->propertyId . '/calendar/days?from=' . $rangeStart . '&to=' . $rangeEnd);
        $resp->assertOk();
        $days = collect($resp->json('days'))->keyBy('date');
        // Day 0 — range only
        $this->assertSame(350, (int)$days[$rangeStart]['price']);
        $this->assertSame('range', $days[$rangeStart]['source']);
        // Day 1 — single override beats range
        $this->assertSame(999, (int)$days[$overrideDate]['price']);
        $this->assertSame('override', $days[$overrideDate]['source']);
        // Day 3 — booking wins
        $this->assertSame('booked', $days[$bookCheckin]['status']);
        $this->assertSame('booking', $days[$bookCheckin]['source']);
    }

    public function test_bulk_update_creates_overrides_per_date(): void
    {
        $from = now()->toDateString();
        $to = now()->addDays(2)->toDateString();
        $resp = $this->actingAs($this->actor())
            ->postJson('/admin/properties/' . $this->propertyId . '/calendar/days/bulk-update', [
                'from' => $from, 'to' => $to,
                'price' => 425, 'min_stay' => 4, 'is_available' => true,
            ]);
        $resp->assertOk()->assertJson(['ok' => true, 'updated' => 3]);
        $this->assertSame(3, DB::table('property_rates')
            ->where('property_id', $this->propertyId)
            ->whereNull('rate_group_id')
            ->where('price', 425)
            ->count());

        // Second call: should upsert, not duplicate.
        $this->actingAs($this->actor())
            ->postJson('/admin/properties/' . $this->propertyId . '/calendar/days/bulk-update', [
                'from' => $from, 'to' => $to,
                'price' => 500, 'is_available' => true,
            ])->assertOk();
        $this->assertSame(3, DB::table('property_rates')
            ->where('property_id', $this->propertyId)
            ->whereNull('rate_group_id')
            ->count());
        $this->assertSame(3, DB::table('property_rates')
            ->where('property_id', $this->propertyId)
            ->where('price', 500)
            ->count());
    }

    public function test_pricing_rule_store_creates_rate_group(): void
    {
        $resp = $this->actingAs($this->actor())
            ->postJson('/admin/properties/' . $this->propertyId . '/calendar/pricing-rule', [
                'name_of_price' => 'Summer block',
                'start_date' => '2026-06-01',
                'end_date' => '2026-08-31',
                'price' => 300,
                'min_stay' => 3,
                'friday_price' => 350,
                'saturday_price' => 350,
            ]);
        $resp->assertCreated()->assertJsonPath('ok', true);
        $this->assertSame(1, DB::table('properties_rates_group')
            ->where('property_id', $this->propertyId)
            ->where('name_of_price', 'Summer block')
            ->count());
    }

    public function test_ical_feed_refresh_writes_last_error_on_failure(): void
    {
        $feedId = DB::table('ical_import_list')->insertGetId([
            'property_id' => (string)$this->propertyId,
            'ical_link' => 'https://nonexistent.invalid/no-such.ics',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->actor())
            ->postJson('/admin/properties/' . $this->propertyId . '/calendar/ical-feeds/' . $feedId . '/refresh')
            ->assertOk();

        $row = DB::table('ical_import_list')->where('id', $feedId)->first();
        $this->assertNotNull($row->last_refreshed_at);
        $this->assertNotEmpty($row->last_error);
    }

    public function test_round_trip_save_then_refetch_preserves_override_data(): void
    {
        $date = '2026-05-15';

        // Step 1: save price + min_stay + notes for that day.
        $this->actingAs($this->actor())
            ->postJson('/admin/properties/' . $this->propertyId . '/calendar/days/bulk-update', [
                'from' => $date, 'to' => $date,
                'price' => 350, 'min_stay' => 3, 'is_available' => true, 'notes' => 'My note',
            ])->assertOk();

        // Step 2: re-fetch the day and assert the override comes back intact.
        $resp = $this->actingAs($this->actor())
            ->getJson('/admin/properties/' . $this->propertyId . '/calendar/days?from=' . $date . '&to=' . $date);
        $resp->assertOk();
        $day = $resp->json('days')[0];
        $this->assertSame('override', $day['source'], 'source should be "override" after a single-day save');
        $this->assertEquals(350, $day['price']);
        $this->assertEquals(3, $day['min_stay']);
        $this->assertTrue($day['is_available']);
        $this->assertSame('My note', $day['notes']);
    }

    public function test_update_defaults_persists_standard_rate_and_min_stay(): void
    {
        $this->actingAs($this->actor())
            ->postJson('/admin/properties/' . $this->propertyId . '/calendar/defaults', [
                'standard_rate' => 425,
                'min_stay'      => 5,
            ])->assertOk()->assertJson(['ok' => true, 'standard_rate' => 425, 'min_stay' => 5]);
        $row = DB::table('properties')->where('id', $this->propertyId)->first();
        $this->assertEquals(425, $row->standard_rate);
        $this->assertEquals(5, $row->min_stay);

        // Days endpoint should now return 425 as the default price for unmatched days.
        $resp = $this->actingAs($this->actor())
            ->getJson('/admin/properties/' . $this->propertyId . '/calendar/days?from=2026-12-01&to=2026-12-01');
        $this->assertSame(425, $resp->json('days')[0]['price']);
        $this->assertSame(5, $resp->json('days')[0]['min_stay']);
    }

    public function test_unknown_property_returns_404(): void
    {
        $this->actingAs($this->actor())
            ->getJson('/admin/properties/999999999/calendar/days?from=2026-01-01&to=2026-01-02')
            ->assertNotFound();
    }

    public function test_days_applies_weekend_rate_on_weekend_days_only(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'weekend_rate' => 300,
            'weekend_days' => '5,6',
        ]);

        // 2026-05-11 = Mon, 2026-05-15 = Fri, 2026-05-16 = Sat, 2026-05-17 = Sun
        $resp = $this->actingAs($this->actor())
            ->getJson('/admin/properties/' . $this->propertyId . '/calendar/days?from=2026-05-11&to=2026-05-17');
        $resp->assertOk();
        $days = collect($resp->json('days'))->keyBy('date');

        $this->assertSame(200, (int) $days['2026-05-11']['price'], 'Mon = standard_rate');
        $this->assertSame('default', $days['2026-05-11']['source']);
        $this->assertSame(300, (int) $days['2026-05-15']['price'], 'Fri = weekend rate');
        $this->assertSame('weekend', $days['2026-05-15']['source']);
        $this->assertSame('Weekend rate', $days['2026-05-15']['source_label']);
        $this->assertSame(300, (int) $days['2026-05-16']['price'], 'Sat = weekend rate');
        $this->assertSame('weekend', $days['2026-05-16']['source']);
        $this->assertSame(200, (int) $days['2026-05-17']['price'], 'Sun = standard_rate (not in weekend_days)');
        $this->assertSame('default', $days['2026-05-17']['source']);
    }

    public function test_weekend_rate_yields_to_range_rule(): void
    {
        DB::table('properties')->where('id', $this->propertyId)->update([
            'weekend_rate' => 300,
            'weekend_days' => '5,6',
        ]);
        DB::table('properties_rates_group')->insert([
            'property_id' => $this->propertyId,
            'name_of_price' => 'Summer 2026',
            'type_of_price' => 'default',
            'start_date' => '2026-05-15',
            'start_date_timestamp' => strtotime('2026-05-15'),
            'end_date' => '2026-05-15',
            'end_date_timestamp' => strtotime('2026-05-15'),
            'price' => 500,
            'is_available' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $resp = $this->actingAs($this->actor())
            ->getJson('/admin/properties/' . $this->propertyId . '/calendar/days?from=2026-05-15&to=2026-05-15');
        $resp->assertOk();
        $day = $resp->json('days')[0];
        $this->assertSame(500, (int) $day['price'], 'range rule wins over weekend preset');
        $this->assertSame('range', $day['source']);
    }

    public function test_update_defaults_persists_weekend_rate_and_days(): void
    {
        $this->actingAs($this->actor())
            ->postJson('/admin/properties/' . $this->propertyId . '/calendar/defaults', [
                'standard_rate' => 200,
                'min_stay'      => 2,
                'weekend_rate'  => 275,
                'weekend_days'  => '5,6',
            ])->assertOk()->assertJson([
                'ok' => true,
                'weekend_rate' => 275,
                'weekend_days' => '5,6',
            ]);
        $row = DB::table('properties')->where('id', $this->propertyId)->first();
        $this->assertEquals(275, $row->weekend_rate);
        $this->assertSame('5,6', $row->weekend_days);

        // Clear back to null.
        $this->actingAs($this->actor())
            ->postJson('/admin/properties/' . $this->propertyId . '/calendar/defaults', [
                'standard_rate' => 200,
                'min_stay'      => 2,
                'weekend_rate'  => null,
                'weekend_days'  => null,
            ])->assertOk();
        $row = DB::table('properties')->where('id', $this->propertyId)->first();
        $this->assertNull($row->weekend_rate);
        $this->assertNull($row->weekend_days);
    }

    public function test_overrides_count_endpoint_returns_count_in_range(): void
    {
        // Seed: 3 inside [2026-06-01, 2026-06-10], 2 outside.
        $rows = [];
        foreach (['2026-06-02', '2026-06-05', '2026-06-09', '2026-05-30', '2026-06-15'] as $d) {
            $rows[] = [
                'property_id' => $this->propertyId,
                'single_date' => $d,
                'single_date_timestamp' => strtotime($d),
                'price' => 250,
                'is_available' => 1,
                'platform_type' => 'airbnb',
                'currency' => 'USD',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('property_rates')->insert($rows);

        $resp = $this->actingAs($this->actor())
            ->getJson('/admin/properties/' . $this->propertyId . '/calendar/overrides-count?from=2026-06-01&to=2026-06-10');
        $resp->assertOk()->assertJson(['count' => 3]);
    }

    public function test_clear_day_overrides_within_range_only(): void
    {
        // Same seed as overrides-count test.
        $rows = [];
        foreach (['2026-06-02', '2026-06-05', '2026-06-09', '2026-05-30', '2026-06-15'] as $d) {
            $rows[] = [
                'property_id' => $this->propertyId,
                'single_date' => $d,
                'single_date_timestamp' => strtotime($d),
                'price' => 250,
                'is_available' => 1,
                'platform_type' => 'airbnb',
                'currency' => 'USD',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('property_rates')->insert($rows);

        $this->actingAs($this->actor())
            ->deleteJson('/admin/properties/' . $this->propertyId . '/calendar/days?from=2026-06-01&to=2026-06-10')
            ->assertOk()->assertJson(['ok' => true, 'deleted' => 3]);

        $remaining = DB::table('property_rates')
            ->where('property_id', $this->propertyId)
            ->whereNull('rate_group_id')
            ->pluck('single_date')
            ->map(fn ($d) => (string) $d)
            ->all();
        sort($remaining);
        $this->assertSame(['2026-05-30', '2026-06-15'], $remaining, 'rows outside the range survive');
    }
}
