<?php

namespace App\Console\Commands;

use App\Models\Property;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Fills the related tables (fees, spaces, room layouts, sleep-cards, amenity
 * highlights, rates) for every active property so manual testing of the
 * booking flow has data on every selectable / displayed surface.
 *
 * Idempotent — only inserts if the property has no rows in that table yet.
 *
 *   php artisan properties:fill-related
 */
class PropertiesFillRelated extends Command
{
    protected $signature = 'properties:fill-related {--id= : restrict to one property id}';
    protected $description = 'Fill property_fees, property_spaces, property_rooms+items, property_rates, etc. so booking flow has full data.';

    public function handle(): int
    {
        $q = Property::where('status', 'true');
        if ($id = $this->option('id')) $q->where('id', $id);
        $props = $q->get();

        if ($props->isEmpty()) { $this->warn('No active properties.'); return self::SUCCESS; }

        $totals = ['fees' => 0, 'spaces' => 0, 'rooms' => 0, 'sleep' => 0, 'highlights' => 0, 'rates' => 0];

        foreach ($props as $p) {
            $changed = ['fees' => 0, 'spaces' => 0, 'rooms' => 0, 'sleep' => 0, 'highlights' => 0, 'rates' => 0];
            $now = now();

            // ── property_fees (cleaning, pet, optional add-ons) ──
            if (!DB::table('property_fees')->where('property_id', $p->id)->exists()) {
                // billing_type is an enum: only `one_time` or `per_day` allowed.
                $fees = [
                    ['name' => 'Cleaning fee',              'rate' => 75.00,  'billing_type' => 'one_time', 'taxable' => 1, 'sort_order' => 0],
                    ['name' => 'Pet fee',                   'rate' => 25.00,  'billing_type' => 'one_time', 'taxable' => 0, 'sort_order' => 1],
                    ['name' => 'Hot tub heating',           'rate' => 35.00,  'billing_type' => 'per_day',  'taxable' => 1, 'sort_order' => 2],
                    ['name' => 'Beach gear rental',         'rate' => 20.00,  'billing_type' => 'per_day',  'taxable' => 1, 'sort_order' => 3],
                    ['name' => 'Extra guest fee',           'rate' => 15.00,  'billing_type' => 'per_day',  'taxable' => 1, 'sort_order' => 4],
                    ['name' => 'Refundable damage deposit', 'rate' => 300.00, 'billing_type' => 'one_time', 'taxable' => 0, 'sort_order' => 5],
                ];
                foreach ($fees as $f) {
                    DB::table('property_fees')->insert(array_merge($f, [
                        'property_id'      => $p->id,
                        'applies_discount' => 0,
                        'status'           => 'active',
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]));
                    $changed['fees']++;
                }
            }

            // ── property_spaces (Kitchen, Living room, Terrace, etc.) ──
            if (!DB::table('property_spaces')->where('property_id', $p->id)->exists()) {
                $spaces = ['Living room', 'Kitchen', 'Terrace / Outdoor', 'Pool area'];
                foreach ($spaces as $i => $name) {
                    DB::table('property_spaces')->insert([
                        'property_id'  => $p->id,
                        'space_name'   => $name,
                        'space_status' => 'true',
                        'space_image'  => null,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                    $changed['spaces']++;
                }
            }

            // ── property_rooms + property_room_items (bedroom breakdown) ──
            if (!DB::table('property_rooms')->where('property_id', $p->id)->exists()) {
                $bedrooms = max(1, (int) ($p->bedroom ?? 1));
                for ($i = 1; $i <= min($bedrooms, 6); $i++) {
                    $title = $i === 1 ? 'Master Bedroom' : "Bedroom $i";
                    $bedType = $i === 1 ? 'King-size bed' : ($i === 2 ? 'Queen-size bed' : 'Twin beds (2)');
                    $roomId = DB::table('property_rooms')->insertGetId([
                        'property_id'    => $p->id,
                        'room_title'     => $title,
                        'room_sub_title' => $bedType,
                        'room_description' => '<p>Spacious, comfortable, with private bath access. ' . $bedType . '. Blackout curtains and ceiling fan.</p>',
                        'room_status'    => 'true',
                        'created_at'     => $now,
                        'updated_at'     => $now,
                    ]);
                    $changed['rooms']++;
                    DB::table('property_room_items')->insert([
                        'room_id'           => $roomId,
                        'sub_room_title'    => 'En-suite bathroom',
                        'sub_room_sub_title'=> 'Walk-in shower · hot water · towels provided',
                        'sub_room_description' => null,
                        'sub_room_status'   => 'true',
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]);
                }
            }

            // ── property_sleep_cards (only if empty for this property) ──
            if (!DB::table('property_sleep_cards')->where('property_id', $p->id)->exists()) {
                $bedrooms = max(1, (int) ($p->bedroom ?? 1));
                $cards = [];
                for ($i = 1; $i <= min($bedrooms, 4); $i++) {
                    $cards[] = [
                        'title' => $i === 1 ? 'Main Bedroom' : "Bedroom $i",
                        'text'  => $i === 1 ? '1 King-size bed' : ($i === 2 ? '1 Queen-size bed' : '2 Twin beds'),
                    ];
                }
                if ($p->sleeps && $p->sleeps > $bedrooms * 2) {
                    $cards[] = ['title' => 'Common areas', 'text' => 'Sofa bed in living room (sleeps 2)'];
                }
                foreach ($cards as $idx => $c) {
                    DB::table('property_sleep_cards')->insert([
                        'property_id' => $p->id,
                        'title'       => $c['title'],
                        'text'        => $c['text'],
                        'sorting'     => $idx,
                        'status'      => 'true',
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ]);
                    $changed['sleep']++;
                }
            }

            // ── property_amenity_highlights (only if empty) ──
            if (!DB::table('property_amenity_highlights')->where('property_id', $p->id)->exists()) {
                $hl = [
                    ['emoji' => '🌊', 'label' => 'Ocean view'],
                    ['emoji' => '🏊', 'label' => 'Private pool'],
                    ['emoji' => '🌳', 'label' => 'Lush garden'],
                    ['emoji' => '📡', 'label' => 'Fast Wi-Fi'],
                    ['emoji' => '🚗', 'label' => 'Free parking'],
                    ['emoji' => '🐾', 'label' => 'Pet-friendly'],
                ];
                foreach ($hl as $idx => $h) {
                    DB::table('property_amenity_highlights')->insert([
                        'property_id' => $p->id,
                        'emoji'       => $h['emoji'],
                        'label'       => $h['label'],
                        'sorting'     => $idx,
                        'status'      => 'true',
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    ]);
                    $changed['highlights']++;
                }
            }

            // ── property_rates: seed a 90-day rate window so the booking
            //    quote calculator has something to chew on. Idempotent: skip
            //    if the property already has any rate row in the upcoming 90d. ──
            $hasRecentRates = DB::table('property_rates')
                ->where('property_id', $p->id)
                ->where('single_date', '>=', now()->toDateString())
                ->exists();
            if (!$hasRecentRates) {
                $base = (float) ($p->standard_rate ?? 200);
                for ($d = 0; $d < 90; $d++) {
                    $date = now()->addDays($d);
                    $isWeekend = in_array($date->dayOfWeek, [5, 6]); // Fri, Sat
                    $price = $base * ($isWeekend ? 1.20 : 1.00);
                    DB::table('property_rates')->insert([
                        'property_id'           => (string) $p->id,
                        'single_date'           => $date->toDateString(),
                        'single_date_timestamp' => $date->timestamp,
                        'price'                 => number_format($price, 2, '.', ''),
                        'is_available'          => '1',
                        'platform_type'         => 'website',
                        'currency'              => 'USD',
                        'min_stay'              => '2',
                        'created_at'            => $now,
                        'updated_at'            => $now,
                    ]);
                    $changed['rates']++;
                }
            }

            $totalChanged = array_sum($changed);
            if ($totalChanged > 0) {
                $bits = [];
                foreach ($changed as $k => $v) { if ($v) $bits[] = "$v $k"; }
                $this->info("  ✓ #{$p->id} {$p->name} — " . implode(', ', $bits));
                foreach ($changed as $k => $v) { $totals[$k] += $v; }
            } else {
                $this->line("  · #{$p->id} {$p->name} — already complete");
            }
        }

        $this->info("\nFilled: " . implode(', ', array_map(fn($k,$v) => "$v $k", array_keys($totals), $totals)));
        return self::SUCCESS;
    }
}
