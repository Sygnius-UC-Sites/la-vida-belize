<?php

namespace App\Http\Controllers\Admin;

use App\Helper\FeatureFlags;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Hidden developer panel — /admin/dev/features
 *
 * Not linked anywhere in the admin menu. Only developer accounts (see
 * FeatureFlags::isDeveloper) can open it; everyone else gets a 404 so the
 * page's existence isn't revealed. Used to switch off admin features the
 * customer hasn't bought.
 */
class DevFeatureController extends Controller
{
    public function index()
    {
        abort_unless(FeatureFlags::isDeveloper(), 404);

        return view('admin.dev-features', [
            'features'    => FeatureFlags::FEATURES,
            'subFeatures' => FeatureFlags::SUB_FEATURES,
            'flags'       => FeatureFlags::flags(),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless(FeatureFlags::isDeveloper(), 404);

        $incoming = (array) $request->input('features', []);
        $flags    = [];
        foreach (FeatureFlags::allKeys() as $key) {
            $flags[$key] = array_key_exists($key, $incoming);
        }
        FeatureFlags::save($flags);

        return redirect()->back()->with('success', 'Feature flags saved. Disabled features are now hidden from the customer.');
    }
}
