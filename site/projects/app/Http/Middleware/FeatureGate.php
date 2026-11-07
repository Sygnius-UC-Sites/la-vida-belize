<?php

namespace App\Http\Middleware;

use App\Helper\FeatureFlags;
use Closure;
use Illuminate\Http\Request;

/**
 * Blocks admin routes belonging to features switched off for this customer
 * (see FeatureFlags + the hidden /admin/dev/features panel). Menu hiding is
 * cosmetic — this middleware is what actually stops direct URL access.
 * Developers are exempt so they can support the customer.
 */
class FeatureGate
{
    public function handle(Request $request, Closure $next)
    {
        $key = FeatureFlags::featureForPath($request->path());

        if ($key !== null && !FeatureFlags::enabled($key) && !FeatureFlags::isDeveloper()) {
            abort(403, 'This feature is not included in your plan. Contact support to enable it.');
        }

        // Strip disabled features from the menu CONFIG so every UI built from
        // it (mega-menu, legacy AdminLTE sidebar, ⌘K command-palette index)
        // hides them in one place. Developers keep the full menu.
        if (!FeatureFlags::isDeveloper()) {
            $menu = config('adminlte.menu', []);
            $dirty = false;
            foreach ($menu as $si => $section) {
                if (!is_array($section) || empty($section['submenu']) || !is_array($section['submenu'])) {
                    continue;
                }
                $filtered = array_values(array_filter($section['submenu'], function ($item) {
                    return !is_array($item) || empty($item['url']) || !FeatureFlags::urlHidden($item['url']);
                }));
                if (count($filtered) !== count($section['submenu'])) {
                    $menu[$si]['submenu'] = $filtered;
                    $dirty = true;
                }
            }
            if ($dirty) {
                config(['adminlte.menu' => $menu]);
            }
        }

        return $next($request);
    }
}
