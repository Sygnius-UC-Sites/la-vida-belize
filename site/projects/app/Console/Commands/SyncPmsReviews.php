<?php

namespace App\Console\Commands;

use App\Models\Testimonial;
use App\Models\Pms\PmsRoom;
use App\Modules\VacationRental\Pms\PmsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * SyncPmsReviews — pulls guest reviews from all 4 PMS and upserts into the testimonials table.
 *
 * Deduplication: uses (pms_source, pms_review_id) — re-running never creates duplicates.
 * All imported reviews are set to status='true' (visible) automatically.
 * Existing manually-entered reviews (pms_review_id = null) are never touched.
 *
 * Usage:
 *   php projects/artisan pms:sync-reviews              — sync all PMS
 *   php projects/artisan pms:sync-reviews --pms=guesty — sync one PMS
 *   php projects/artisan pms:sync-reviews --dry-run    — preview without saving
 */
class SyncPmsReviews extends Command
{
    protected $signature   = 'pms:sync-reviews
                                {--pms= : Only sync a specific PMS (hostfully|hostaway|guesty|lodgify)}
                                {--dry-run : Preview what would be imported without saving}';
    protected $description = 'Sync guest reviews from all PMS into the testimonials table';

    public function __construct(private PmsManager $pms)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun     = $this->option('dry-run');
        $filterPms  = $this->option('pms');

        // Load all active PMS rooms grouped by pms_source
        $rooms = PmsRoom::where('status', 'true')
            ->when($filterPms, fn($q) => $q->where('pms_source', $filterPms))
            ->get(['id', 'name', 'pms_source', 'pms_property_id']);

        if ($rooms->isEmpty()) {
            $this->info('No active PMS rooms found.');
            return 0;
        }

        $totalImported = 0;
        $totalSkipped  = 0;
        $totalFailed   = 0;

        foreach ($rooms->groupBy('pms_source') as $pmsSource => $pmsRooms) {
            $this->info("\nSyncing reviews from {$pmsSource} ({$pmsRooms->count()} properties)...");

            try {
                $adapter = $this->pms->driver($pmsSource);
            } catch (\Throwable $e) {
                $this->error("  Could not load {$pmsSource} adapter: " . $e->getMessage());
                $totalFailed++;
                continue;
            }

            // Hostaway: API doesn't support per-listing filter — fetch all account reviews
            // once and distribute per-property by internalListingName match.
            $hostawayAllReviews = null;
            if ($pmsSource === 'hostaway') {
                try {
                    $hostawayAllReviews = $adapter->getReviews('all');
                } catch (\Throwable $e) {
                    $this->error("  Hostaway: could not fetch reviews: " . $e->getMessage());
                    $totalFailed++;
                    continue; // skip to next pms_source
                }
            }

            foreach ($pmsRooms as $room) {
                $this->line("  Property: {$room->name} (pms_id: {$room->pms_property_id})");

                try {
                    if ($pmsSource === 'hostaway') {
                        // Filter account-level reviews to this listing by name match
                        $reviews = array_values(array_filter(
                            $hostawayAllReviews ?? [],
                            fn($r) => strtolower(trim($r['listing_name'] ?? '')) === strtolower(trim($room->name))
                        ));
                    } else {
                        // Guesty, Hostfully, Lodgify: all support per-property API calls
                        $reviews = $adapter->getReviews($room->pms_property_id);
                    }
                } catch (\Throwable $e) {
                    $this->warn("    ✗ API error: " . $e->getMessage());
                    Log::channel('lodgify')->error("SyncPmsReviews: getReviews failed", [
                        'pms'        => $pmsSource,
                        'property'   => $room->pms_property_id,
                        'error'      => $e->getMessage(),
                    ]);
                    $totalFailed++;
                    continue;
                }

                if (empty($reviews)) {
                    $this->line("    No reviews returned.");
                    continue;
                }

                $imported = $skipped = $emptyMsg = 0;

                foreach ($reviews as $review) {
                    $reviewId = (string)($review['id'] ?? '');
                    if (!$reviewId || empty($review['message'])) {
                        $emptyMsg++;
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("    [DRY RUN] Would import/update: \"{$review['name']}\" — score {$review['score']} — " . substr($review['message'], 0, 60) . '...');
                        $imported++;
                        continue;
                    }

                    try {
                        // firstOrCreate: property_id is set on first import only — never
                        // overwritten. This prevents PMS accounts where the same review ID
                        // appears across multiple listing calls (e.g. Hostaway global reviews)
                        // from stealing ownership on every re-sync.
                        [$record, $wasNew] = [
                            Testimonial::firstOrCreate(
                                ['pms_source' => $pmsSource, 'pms_review_id' => $reviewId],
                                [
                                    'name'          => $review['name']      ?? 'Guest',
                                    'message'       => $review['message'],
                                    'score'         => $review['score']     ?? 5,
                                    'stay_date'     => $review['stay_date'] ?? null,
                                    'end_date'      => $review['end_date']  ?? null,
                                    'profile'       => $review['profile']   ?? null,
                                    'property_id'   => (string)$room->pms_property_id,
                                    'pms_source'    => $pmsSource,
                                    'pms_review_id' => $reviewId,
                                    'status'        => 'true',
                                    'email'         => '',
                                    'mobile'        => '',
                                ]
                            ),
                            null, // wasNew placeholder
                        ];
                        $wasNew = $record->wasRecentlyCreated;
                        if ($wasNew) { $imported++; } else { $skipped++; }
                    } catch (\Throwable $e) {
                        $this->warn("    ✗ Failed to save review {$reviewId}: " . $e->getMessage());
                        Log::channel('lodgify')->error("SyncPmsReviews: upsert failed", [
                            'pms'       => $pmsSource,
                            'property'  => $room->pms_property_id,
                            'review_id' => $reviewId,
                            'error'     => $e->getMessage(),
                        ]);
                        $totalFailed++;
                    }
                }

                $totalImported += $imported;
                $totalSkipped  += $skipped;

                $this->line("    ✓ Imported: {$imported}, Already exists: {$skipped}, Empty/no message: {$emptyMsg}");

                Log::channel('lodgify')->info("SyncPmsReviews: synced", [
                    'pms'      => $pmsSource,
                    'property' => $room->pms_property_id,
                    'imported' => $imported,
                    'skipped'  => $skipped,
                ]);
            }
        }

        $suffix = $dryRun ? ' (DRY RUN — nothing saved)' : '';
        $this->info("\nDone{$suffix}. Imported: {$totalImported}, Already existed: {$totalSkipped}, Failed: {$totalFailed}.");
        return 0;
    }
}
