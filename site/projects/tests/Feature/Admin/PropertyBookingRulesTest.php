<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PropertyBookingRulesTest extends TestCase
{
    use WithoutMiddleware;

    public function test_property_update_persists_booking_rules(): void
    {
        // Snapshot original values for restore
        $original = (array) DB::table('properties')->where('id', 1)->select(
            'instant_booking_enabled', 'rental_agreement_required', 'cancellation_policy_type',
            'cancellation_policy', 'min_stay', 'max_stay',
            'accepts_pets', 'smoking_allowed', 'parties_allowed', 'wheelchair_accessible'
        )->first();

        // Get the existing property's required-by-validator fields, then submit them along with new fields.
        $p = DB::table('properties')->where('id', 1)->first();
        $payload = [
            'name' => $p->name,
            'seo_url' => $p->seo_url,
            'type' => $p->type,
            // New booking-rules fields
            'instant_booking_enabled' => 1,
            'rental_agreement_required' => 1,
            'cancellation_policy_type' => 'strict',
            'cancellation_policy' => 'Test policy text',
            'min_stay' => 3,
            'max_stay' => 14,
            'accepts_pets' => 1,
            'smoking_allowed' => 0,
            'parties_allowed' => 0,
            'wheelchair_accessible' => 1,
        ];
        $this->put('/admin/properties/1', $payload);

        $updated = DB::table('properties')->where('id', 1)->first();
        $this->assertSame(1, (int)$updated->instant_booking_enabled);
        $this->assertSame(1, (int)$updated->rental_agreement_required);
        $this->assertSame('strict', $updated->cancellation_policy_type);
        $this->assertSame('Test policy text', $updated->cancellation_policy);
        $this->assertSame(3, (int)$updated->min_stay);
        $this->assertSame(14, (int)$updated->max_stay);
        $this->assertSame(1, (int)$updated->accepts_pets);
        $this->assertSame(0, (int)$updated->smoking_allowed);
        $this->assertSame(1, (int)$updated->wheelchair_accessible);

        // Restore original values
        DB::table('properties')->where('id', 1)->update($original);
    }

    public function test_property_update_persists_per_property_installment_override(): void
    {
        $original = (array) DB::table('properties')->where('id', 1)->select(
            'installment_plan', 'installment_2_days', 'installment_2_percent', 'installment_2_min_lead_days',
            'installment_3_days', 'installment_3_percent', 'installment_3_min_lead_days'
        )->first();

        $p = DB::table('properties')->where('id', 1)->first();
        $payload = [
            'name' => $p->name, 'seo_url' => $p->seo_url, 'type' => $p->type,
            'installment_plan' => 'three',
            'installment_2_days' => 25,
            'installment_2_percent' => 40,
            'installment_2_min_lead_days' => 30,
            'installment_3_days' => 10,
            'installment_3_percent' => 25,
            'installment_3_min_lead_days' => 90,
        ];
        $this->put('/admin/properties/1', $payload);

        $updated = DB::table('properties')->where('id', 1)->first();
        $this->assertSame('three', $updated->installment_plan);
        $this->assertSame(25, (int)$updated->installment_2_days);
        $this->assertSame(40, (int)$updated->installment_2_percent);
        $this->assertSame(90, (int)$updated->installment_3_min_lead_days);

        // Restore
        DB::table('properties')->where('id', 1)->update($original);
    }

    public function test_empty_string_override_persists_as_null(): void
    {
        // Set a non-null override first
        DB::table('properties')->where('id', 1)->update(['installment_plan' => 'two']);

        $p = DB::table('properties')->where('id', 1)->first();
        $payload = [
            'name' => $p->name, 'seo_url' => $p->seo_url, 'type' => $p->type,
            'installment_plan' => '',  // empty string from "use global" option
            'installment_2_days' => '',
            'installment_2_percent' => '',
        ];
        $this->put('/admin/properties/1', $payload);

        $updated = DB::table('properties')->where('id', 1)->first();
        $this->assertNull($updated->installment_plan, 'Empty string from form should persist as NULL for override columns');
    }
}
