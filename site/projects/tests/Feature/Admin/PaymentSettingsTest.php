<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentSettingsTest extends TestCase
{
    use WithoutMiddleware;

    public function test_index_shows_current_settings(): void
    {
        DB::table('basic_settings')->updateOrInsert(['name' => 'installment_plan'], ['value' => 'two', 'updated_at' => now(), 'created_at' => now()]);
        DB::table('basic_settings')->updateOrInsert(['name' => 'installment_2_percent'], ['value' => '40', 'updated_at' => now(), 'created_at' => now()]);

        // WithoutMiddleware strips ShareErrorsFromSession, so $errors is undefined in the view.
        // Pre-share an empty errors bag so the Blade `@if($errors->any())` block doesn't blow up.
        $response = $this->withViewErrors([])->get('/admin/settings/payment');

        $response->assertStatus(200);
        $response->assertSee('Payment Settings');
        // Form should reflect current value (40 in installment_2_percent input)
        $response->assertSee('value="40"', false);

        // Reset
        DB::table('basic_settings')->where('name', 'installment_plan')->update(['value' => 'full']);
        DB::table('basic_settings')->where('name', 'installment_2_percent')->update(['value' => '35']);
    }

    public function test_post_persists_installment_settings(): void
    {
        $response = $this->post('/admin/settings/payment', [
            'installment_plan' => 'three',
            'installment_2_days' => 30,
            'installment_2_percent' => 35,
            'installment_2_min_lead_days' => 50,
            'installment_3_days' => 20,
            'installment_3_percent' => 20,
            'installment_3_min_lead_days' => 120,
        ]);

        $response->assertSessionHas('success');
        $this->assertSame('three', DB::table('basic_settings')->where('name', 'installment_plan')->value('value'));
        $this->assertSame('30', DB::table('basic_settings')->where('name', 'installment_2_days')->value('value'));
        $this->assertSame('120', DB::table('basic_settings')->where('name', 'installment_3_min_lead_days')->value('value'));

        // Reset
        DB::table('basic_settings')->where('name', 'installment_plan')->update(['value' => 'full']);
    }

    public function test_post_rejects_invalid_installment_plan(): void
    {
        $response = $this->post('/admin/settings/payment', [
            'installment_plan' => 'four',  // invalid
            'installment_2_days' => 30,
            'installment_2_percent' => 35,
            'installment_2_min_lead_days' => 50,
            'installment_3_days' => 20,
            'installment_3_percent' => 20,
            'installment_3_min_lead_days' => 120,
        ]);

        $response->assertSessionHasErrors('installment_plan');
    }

    public function test_post_persists_tax_base_checkboxes(): void
    {
        $response = $this->post('/admin/settings/payment', [
            'installment_plan' => 'full',
            'installment_2_days' => 30, 'installment_2_percent' => 35, 'installment_2_min_lead_days' => 46,
            'installment_3_days' => 15, 'installment_3_percent' => 20, 'installment_3_min_lead_days' => 115,
            'tax_applies_to_nights' => '1',
            'tax_applies_to_cleaning_fee' => '1',
            'tax_applies_to_hottub_addon' => '1',  // owner enables tax on hot tub
            // Other tax_* and all discount_* not in payload — should default to 0
        ]);

        $response->assertSessionHas('success');
        $this->assertSame('1', DB::table('basic_settings')->where('name', 'tax_applies_to_nights')->value('value'));
        $this->assertSame('1', DB::table('basic_settings')->where('name', 'tax_applies_to_hottub_addon')->value('value'));
        $this->assertSame('0', DB::table('basic_settings')->where('name', 'tax_applies_to_pet_fee')->value('value'));  // not posted = 0
        $this->assertSame('0', DB::table('basic_settings')->where('name', 'discount_applies_to_nights')->value('value'));  // not posted = 0

        // Reset to global defaults
        DB::table('basic_settings')->where('name', 'tax_applies_to_pet_fee')->update(['value' => '1']);
        DB::table('basic_settings')->where('name', 'tax_applies_to_hottub_addon')->update(['value' => '0']);
        DB::table('basic_settings')->where('name', 'discount_applies_to_nights')->update(['value' => '1']);
    }
}
