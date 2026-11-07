<?php

namespace App\Console\Commands;

/**
 * [PMS-MODULE] pms:backfill-legacy
 *
 * Keeps the host site's LEGACY property table (guesty_properties) refreshed
 * from the synced PMS data — so every homepage / listing / search section,
 * whatever its per-site design, keeps reading the table it always read while
 * the data behind it now comes from the PMS.
 *
 * Writes only the display fields the listing views consume:
 *   title/nickname, bedrooms, bathrooms, beds, accommodates/guests,
 *   picture/pictures (legacy JSON shapes), prices.basePrice, address JSON,
 *   terms_min_night.
 *
 * NEVER touches on existing rows: seo_url (public URL), active (admin's
 * publish switch), status, or meta_* (site SEO). New PMS-only listings are
 * inserted as private drafts until an administrator explicitly links them.
 */

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PmsBackfillLegacy extends Command
{
    protected $signature = 'pms:backfill-legacy {--dry-run}';
    protected $description = 'Refresh the legacy guesty_properties display data from synced pms_rooms';

    public function handle(): int
    {
        if (!Schema::hasTable('guesty_properties') || !Schema::hasTable('pms_rooms')) {
            $this->warn('guesty_properties or pms_rooms table missing — nothing to backfill.');
            return 0;
        }

        $dry = $this->option('dry-run');
        $updated = 0;
        $inserted = 0;

        foreach (DB::table('pms_rooms')->where('pms_source', 'guesty')->get() as $room) {
            $images = json_decode((string) $room->images_json, true) ?: [];
            if (empty($images) && empty($room->name)) {
                continue; // nothing useful synced yet
            }

            // Legacy JSON shape: BOTH picture and pictures columns hold an
            // ARRAY of image objects (blades loop them and read ['original']).
            $pictures = array_map(function ($u) use ($room) {
                return [
                    'original'  => $u,
                    'thumbnail' => $u,
                    'source'    => $u,
                    'caption'   => (string) $room->name,
                ];
            }, array_values(array_filter($images)));

            $values = array_filter([
                'title'           => $room->name,
                'nickname'        => $room->name,
                'bedrooms'        => $room->bedrooms,
                'bathrooms'       => $room->bathrooms,
                'accommodates'    => $room->max_people,
                'guests'          => $room->max_people,
                'terms_min_night' => $room->min_stay,
                // page text comes from the PMS too
                'first_description' => $room->description,
                'summary'           => $room->description ? substr(strip_tags($room->description), 0, 480) : null,
            ], fn ($v) => $v !== null && $v !== '');

            if (!empty($pictures)) {
                $values['pictures'] = json_encode($pictures);
                $values['picture']  = json_encode($pictures); // legacy column is ALSO an array
            }
            // amenities: same plain JSON-array-of-strings format as the old data
            if (!empty($room->amenities_json) && $room->amenities_json !== '[]') {
                $values['amenities'] = $room->amenities_json;
            }
            if (!empty($room->min_price)) {
                $values['prices'] = json_encode(['basePrice' => (float) $room->min_price, 'currency' => $room->currency ?: 'USD']);
            }
            if (!empty($room->city) || !empty($room->address)) {
                $values['address'] = json_encode([
                    'full'  => trim(($room->address ?? '') . ', ' . ($room->city ?? ''), ', '),
                    'city'  => $room->city,
                    'state' => $room->state,
                ]);
            }

            $existing = DB::table('guesty_properties')->where('_id', $room->pms_room_id)->first();

            if ($existing) {
                if (!$dry) {
                    DB::table('guesty_properties')->where('id', $existing->id)
                        ->update($values + ['updated_at' => now()]);
                }
                $updated++;
            } else {
                if (!$dry) {
                    DB::table('guesty_properties')->insert($values + [
                        '_id'        => $room->pms_room_id,
                        'seo_url'    => $room->seo_url,
                        'location_id'=> DB::table('guesty_properties')->whereNotNull('location_id')->value('location_id') ?? DB::table('locations')->value('id'),
                        'active'     => 0,
                        'status'     => 'false',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $inserted++;
                $this->line("  + new listing row: {$room->name} ({$room->seo_url})");
            }
        }

        $this->info(($dry ? '[DRY-RUN] ' : '') . "Legacy backfill: {$updated} updated, {$inserted} inserted.");

        // ── Availability + nightly prices for the legacy date-search ───────
        // The homepage/listing date search reads guesty_availablity_prices;
        // refresh it from pms_calendar so search results use live PMS data.
        if (Schema::hasTable('guesty_availablity_prices') && Schema::hasTable('pms_calendar')) {
            $rows = 0;
            $roomIds = DB::table('pms_rooms')->where('pms_source', 'guesty')->pluck('pms_room_id');
            foreach ($roomIds as $rid) {
                $cal = DB::table('pms_calendar')
                    ->where('pms_room_id', $rid)
                    ->whereDate('date', '>=', now()->toDateString())
                    ->orderBy('date')
                    ->limit(750)
                    ->get();
                if ($cal->isEmpty()) {
                    continue; // calendar not synced yet — keep existing data
                }
                if (!$dry) {
                    DB::table('guesty_availablity_prices')->where('listingId', $rid)->delete();
                    $batch = [];
                    foreach ($cal as $c) {
                        $batch[] = [
                            'start_date' => $c->date,
                            'listingId'  => $rid,
                            'price'      => $c->nightly_rate,
                            'minNights'  => $c->min_stay ?? 1,
                            'status'     => ((int) $c->state === 1) ? 'available' : 'unavailable',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                        if (count($batch) === 200) { DB::table('guesty_availablity_prices')->insert($batch); $batch = []; }
                    }
                    if ($batch) { DB::table('guesty_availablity_prices')->insert($batch); }
                }
                $rows += $cal->count();
            }
            $this->info(($dry ? '[DRY-RUN] ' : '') . "Availability/price rows refreshed: {$rows}");
        }


        // ── Blocked dates for the site's OWN date picker ────────────────────
        // The picker reads ical_events (event_pid = guesty_properties.id).
        // Convert pms_calendar unavailable runs into date ranges there, so the
        // untouched picker shows live PMS availability. Our rows: uid='PMS'.
        if (Schema::hasTable('ical_events') && Schema::hasTable('pms_calendar')) {
            $n = 0;
            foreach (DB::table('guesty_properties')->whereIn('_id', DB::table('pms_rooms')->pluck('pms_room_id'))->get(['id', '_id']) as $gp) {
                $cal = DB::table('pms_calendar')->where('pms_room_id', $gp->_id)
                    ->whereDate('date', '>=', now()->toDateString())
                    ->orderBy('date')->limit(750)->get(['date', 'state']);
                if ($cal->isEmpty()) continue;
                if (!$dry) DB::table('ical_events')->where('event_pid', $gp->id)->where('uid', 'PMS')->delete();
                $ranges = []; $start = null; $prev = null;
                foreach ($cal as $c) {
                    if ((int) $c->state === 0) {
                        if ($start === null) $start = $c->date;
                        $prev = $c->date;
                    } elseif ($start !== null) {
                        $ranges[] = [$start, $prev]; $start = null;
                    }
                }
                if ($start !== null) $ranges[] = [$start, $prev];
                foreach ($ranges as $r) {
                    if (!$dry) DB::table('ical_events')->insert([
                        'event_pid' => $gp->id, 'uid' => 'PMS', 'event_type' => 'pms',
                        'start_date' => $r[0],
                        'end_date' => date('Y-m-d', strtotime($r[1] . ' +1 day')),
                        'text' => 'Blocked (PMS)', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $n++;
                }
            }
            $this->info(($dry ? '[DRY-RUN] ' : '') . "Picker blocked-date ranges written: {$n}");
        }

        return 0;
    }
}
