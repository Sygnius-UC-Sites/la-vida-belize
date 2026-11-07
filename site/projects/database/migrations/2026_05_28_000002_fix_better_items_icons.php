<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Frontend QA pass — replace the broken external `luxuryaccess.com` icon
 * URLs in the Better-than-Airbnb section with Bootstrap-Icons classes.
 *
 * QA flagged the icons as missing on the live site.
 * Cause: the seeder defaults pointed at a third-party CDN that's now serving
 * 404s. Bootstrap Icons are loaded site-wide and render reliably.
 *
 * The new admin form lets editors mix `bi-*` classes with image URLs/paths,
 * so this migration only rewrites the *broken* defaults. Custom edits that
 * already point at admin-uploaded images are left untouched.
 */
return new class extends Migration {
    public function up(): void
    {
        $row = DB::table('cms')->where('seo_url', 'home')->first();
        if (!$row || empty($row->better_items)) {
            return;
        }
        $items = json_decode($row->better_items, true);
        if (!is_array($items)) {
            return;
        }

        $fallbackByTitle = [
            'Better rates'           => 'bi-piggy-bank',
            'Flexible'               => 'bi-arrow-repeat',
            'Discount on Experiences'=> 'bi-tags',
            '24/7 Concierge'         => 'bi-headset',
        ];

        $changed = false;
        foreach ($items as $i => $item) {
            $icon = (string)($item['icon'] ?? '');
            // Only rewrite if the icon points at the broken external CDN.
            if (str_contains($icon, 'luxuryaccess.com')) {
                $title = (string)($item['title'] ?? '');
                $items[$i]['icon'] = $fallbackByTitle[$title] ?? 'bi-stars';
                $changed = true;
            }
        }

        if ($changed) {
            DB::table('cms')
                ->where('id', $row->id)
                ->update(['better_items' => json_encode($items)]);
        }
    }

    public function down(): void
    {
        // No-op — we never want to put broken URLs back.
    }
};
