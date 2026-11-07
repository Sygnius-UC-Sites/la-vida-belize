<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentGatewaysSettingsController extends Controller
{
    private const FIELDS = [
        'stripe_publish_key', 'stripe_secret_key', 'stripe_webhook_secret',
        'paypal_access_token',
        'square_ACCESS_TOKEN', 'square_Application_id', 'square_location_id', 'square_environment',
        'AUTHORIZED_MERCHANT_LOGIN_ID', 'AUTHORIZED_MERCHANT_TRANSACTION_KEY',
        'AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY', 'authorize_environment',
    ];

    /** Card processors are mutually exclusive — owner picks ONE. */
    private const CARD_GATEWAYS = ['stripe', 'authorize', 'square'];

    public function edit()
    {
        $rows = DB::table('basic_settings')
            ->whereIn('name', array_merge(self::FIELDS, ['enabled_payment_gateways']))
            ->pluck('value', 'name')->toArray();

        $values = [];
        foreach (self::FIELDS as $f) {
            $values[$f] = $rows[$f] ?? '';
        }

        $enabledList = array_filter(array_map('trim', explode(',', $rows['enabled_payment_gateways'] ?? '')));

        // Derive the active card processor (only one can be saved at a time).
        // If somehow multiple card gateways are persisted (legacy data), we
        // pick the first one in the canonical order so the UI stays sane.
        $cardGateway = '';
        foreach (self::CARD_GATEWAYS as $g) {
            if (in_array($g, $enabledList, true)) { $cardGateway = $g; break; }
        }
        $paypalEnabled = in_array('paypal', $enabledList, true);

        return view('admin.settings.payment-gateways', compact(
            'values', 'cardGateway', 'paypalEnabled'
        ));
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (self::FIELDS as $f) {
            $rules[$f] = str_ends_with($f, '_environment') ? 'nullable|in:sandbox,production' : 'nullable|string';
        }
        $rules['card_gateway']      = 'nullable|in:' . implode(',', self::CARD_GATEWAYS);
        $rules['paypal_enabled']    = 'nullable|boolean';
        $rules['gateway_enabled']   = 'nullable|array';
        $rules['gateway_enabled.*'] = 'nullable|boolean';
        $validated = $request->validate($rules);

        // Build the enabled-gateways list with the constraint:
        //   * 0 or 1 card gateway (mutually exclusive)
        //   * paypal is independent, can sit alongside the card gateway
        $enabled = [];
        if (array_key_exists('card_gateway', $validated)) {
            if (!empty($validated['card_gateway'])) {
                $enabled[] = $validated['card_gateway'];
            }
        } else {
            $legacyEnabled = (array) ($validated['gateway_enabled'] ?? []);
            foreach (self::CARD_GATEWAYS as $gateway) {
                if (filter_var($legacyEnabled[$gateway] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $enabled[] = $gateway;
                    break;
                }
            }
        }
        $paypalEnabled = array_key_exists('paypal_enabled', $validated)
            ? $request->boolean('paypal_enabled')
            : filter_var(($validated['gateway_enabled']['paypal'] ?? false), FILTER_VALIDATE_BOOLEAN);
        if ($paypalEnabled) {
            $enabled[] = 'paypal';
        }

        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'enabled_payment_gateways'],
            ['value' => implode(',', $enabled), 'updated_at' => now()]
        );

        // Persist credentials — only update fields that were submitted
        // (array_key_exists guard protects existing values from being wiped if a field is omitted).
        foreach (self::FIELDS as $f) {
            if (!array_key_exists($f, $validated)) continue;
            $value = (string) ($validated[$f] ?? '');
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $f],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        return redirect()->back()->with('success', 'Payment gateway settings updated.');
    }
}
