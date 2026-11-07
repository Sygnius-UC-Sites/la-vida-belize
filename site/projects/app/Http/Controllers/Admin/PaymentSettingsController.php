<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentSettingsController extends Controller
{
    /** Keys this controller manages (NEVER includes payment-gateway credentials or enabled_payment_gateways — those are agency-only per D2). */
    private const MANAGED_KEYS = [
        // Phase 13C — currency moved here from the deleted Booking & Payments tab.
        'payment_currency',
        // Installment plan
        'installment_plan',
        'installment_2_days', 'installment_2_percent', 'installment_2_min_lead_days',
        'installment_3_days', 'installment_3_percent', 'installment_3_min_lead_days',
        // Tax base
        'tax_applies_to_nights', 'tax_applies_to_cleaning_fee', 'tax_applies_to_pet_fee', 'tax_applies_to_extra_guest_fee',
        'tax_applies_to_hottub_addon', 'tax_applies_to_firewood_addon', 'tax_applies_to_rv_hookup_addon', 'tax_applies_to_extra_visitor_addon',
        // Discount base
        'discount_applies_to_nights', 'discount_applies_to_cleaning_fee', 'discount_applies_to_pet_fee', 'discount_applies_to_extra_guest_fee',
        'discount_applies_to_hottub_addon', 'discount_applies_to_firewood_addon', 'discount_applies_to_rv_hookup_addon', 'discount_applies_to_extra_visitor_addon',
    ];

    public function index(): View
    {
        $settings = DB::table('basic_settings')
            ->whereIn('name', self::MANAGED_KEYS)
            ->pluck('value', 'name')
            ->toArray();
        return view('admin.settings.payment', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            // Phase 13C — currency selector (optional; defaults to '$' when blank)
            'payment_currency' => 'nullable|in:$,€,CAD',
            'installment_plan' => 'required|in:full,two,three',
            'installment_2_days' => 'required|integer|min:0',
            'installment_2_percent' => 'required|integer|min:0|max:100',
            'installment_2_min_lead_days' => 'required|integer|min:0',
            'installment_3_days' => 'required|integer|min:0',
            'installment_3_percent' => 'required|integer|min:0|max:100',
            'installment_3_min_lead_days' => 'required|integer|min:0',
        ];
        // Tax + discount base are checkboxes — accept missing as 0
        foreach (self::MANAGED_KEYS as $k) {
            if (str_starts_with($k, 'tax_applies_to_') || str_starts_with($k, 'discount_applies_to_')) {
                $rules[$k] = 'nullable|boolean';
            }
        }
        $request->validate($rules);

        // Validate installment percentages sum sanity
        $plan = $request->input('installment_plan');
        if ($plan === 'two') {
            $sum = (int)$request->input('installment_2_percent');
            if ($sum < 1 || $sum > 99) {
                return back()->withErrors(['installment_2_percent' => 'Installment 2 percent must be 1-99 for a 2-installment plan.'])->withInput();
            }
        } elseif ($plan === 'three') {
            $sum = (int)$request->input('installment_2_percent') + (int)$request->input('installment_3_percent');
            if ($sum < 1 || $sum > 99) {
                return back()->withErrors(['installment_3_percent' => 'Installment 2 + 3 percent must total 1-99.'])->withInput();
            }
        }

        $now = now();
        foreach (self::MANAGED_KEYS as $key) {
            $value = $request->input($key);
            // Coerce checkbox-style bools to '1'/'0'
            if (str_starts_with($key, 'tax_applies_to_') || str_starts_with($key, 'discount_applies_to_')) {
                $value = $request->boolean($key) ? '1' : '0';
            }
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $key],
                ['value' => (string)$value, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        return back()->with('success', 'Payment settings saved.');
    }
}
