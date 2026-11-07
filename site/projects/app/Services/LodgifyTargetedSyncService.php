<?php

namespace App\Services;

use App\Models\Pms\PmsProperty;
use App\Models\Pms\PmsRoom;
use App\Models\Pms\PmsImage;
use App\Models\Pms\PmsAmenityGroup;
use App\Models\Pms\PmsAmenityGroupItem;
use App\Models\Pms\PmsUnavailableDate;
use App\Modules\VacationRental\Pms\Adapters\LodgifyAdapter;
use Illuminate\Support\Facades\Log;
use Helper;

/**
 * LodgifyTargetedSyncService
 *
 * Syncs a single Lodgify property on-demand.
 * Triggered by webhooks (UPDATED_PROPERTY, NEW_PROPERTY) so the server
 * only ever fetches data for the one property that actually changed.
 *
 * This replaces the brute-force cron job approach that fetched every
 * property on a schedule, which caused API rate-limit exhaustion and
 * server timeouts.
 *
 * Flow:
 *   Lodgify Edit
 *     → Lodgify fires webhook to /api/lodgify-webhook?token=<TOKEN>
 *       → WebhookController extracts property_id
 *         → LodgifyTargetedSyncService::syncSingleProperty($id)
 *           → API: GET /v1/properties/{id}        (property + unavailable dates)
 *           → API: GET /v1/properties/{id}/rooms  (rooms list)
 *           → For each room: GET /v1/properties/{id}/rooms/{rid}
 *             (images, amenities, pricing)
 *           → Upsert DB records
 *           → ~2 seconds total, zero server load
 */
class LodgifyTargetedSyncService
{
    protected \App\Modules\VacationRental\Pms\PmsAdapter $adapter;

    public function __construct(?\App\Modules\VacationRental\Pms\PmsAdapter $adapter = null)
    {
        $this->adapter = $adapter ?? app(\App\Modules\VacationRental\Pms\PmsAdapter::class);
    }

    /**
     * Sync a single Lodgify property by its Lodgify property ID.
     *
     * @param  int|string $propertyId  The Lodgify property ID (property_uid from webhook)
     * @return array  ['success' => bool, 'message' => string]
     */
    public function syncSingleProperty($propertyId): array
    {
        $propertyId = (int) $propertyId;

        Log::channel('pms_webhooks')->info("TargetedSync: starting sync for property #{$propertyId}");

        try {
            // ── Step 1: Fetch full property detail from Lodgify ──────────────
            $propertyRes = $this->adapter->getProperty((string) $propertyId);

            if (($propertyRes['status'] ?? 0) !== 200) {
                $error = $propertyRes['error'] ?? 'Unknown error';
                Log::channel('pms_webhooks')->error("TargetedSync: API error fetching property #{$propertyId}", ['error' => $error]);
                return ['success' => false, 'message' => "API error: {$error}"];
            }

            $data = $propertyRes['data'] ?? [];

            // ── Step 2: Extract and store unavailable dates ───────────────────
            PmsUnavailableDate::where('property_id', $propertyId)->delete();

            if (isset($data['in_out']['not_available'])) {
                foreach ($data['in_out']['not_available'] as $blocked) {
                    PmsUnavailableDate::create([
                        'property_id' => $propertyId,
                        'un_date'     => $blocked['date'],
                    ]);
                }
            }
            unset($data['in_out']);

            // ── Step 3: Prepare and upsert the PmsProperty record ────────
            $rooms = [];
            if (isset($data['owner'])) { $data['owner'] = json_encode($data['owner']); }
            if (isset($data['rooms'])) { $rooms = $data['rooms']; }
            $data['rooms'] = json_encode($rooms);

            // Remove the raw Lodgify 'id' field — we store it as pms_property_id
            unset($data['id']);

            $existing = PmsProperty::where('pms_property_id', $propertyId)->first();

            if ($existing) {
                // Only update fillable fields to avoid mass-assignment issues
                $existing->update(array_intersect_key($data, array_flip((new PmsProperty())->getFillable())));
                Log::channel('pms_webhooks')->info("TargetedSync: property #{$propertyId} updated.");
            } else {
                // New property — generate SEO fields
                $name    = $data['name'] ?? "property-{$propertyId}";
                $seoSlug = strtolower(str_replace(' ', '-', $name)) . '-' . $propertyId;

                $data['pms_property_id']  = $propertyId;
                $data['seo_url']              = Helper::getSeoUrlGet($seoSlug);
                $data['meta_title']           = substr($name, 0, 60);
                $data['meta_keywords']        = $name;
                $data['meta_description']     = substr($name, 0, 160);

                PmsProperty::create(array_intersect_key($data, array_flip((new PmsProperty())->getFillable())));
                Log::channel('pms_webhooks')->info("TargetedSync: property #{$propertyId} created.");
            }

            // ── Step 4: Sync each room (images, amenities, pricing) ──────────
            foreach ($rooms as $room) {
                $this->syncSingleRoom($propertyId, $room['id']);
            }

            Log::channel('pms_webhooks')->info("TargetedSync: property #{$propertyId} complete.", [
                'rooms_synced' => count($rooms),
            ]);

            return ['success' => true, 'message' => "Property #{$propertyId} synced successfully."];

        } catch (\Throwable $e) {
            Log::channel('pms_webhooks')->error("TargetedSync: exception for property #{$propertyId}", [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Sync a single room (unit) within a property.
     * Fetches high-res images, amenities, and pricing for that room only.
     */
    public function syncSingleRoom(int $propertyId, int $roomId): void
    {
        $res = $this->adapter->listUnits((string) $propertyId);

        // Fall back to direct room endpoint since listUnits returns all rooms
        // Use the existing LodgifyApi helper which already handles room detail correctly
        try {
            $lodgifyApi = app(\LodgifyApi::class);
            $lodgifyApi->getSingleRoomDetail($propertyId, $roomId);

            Log::channel('pms_webhooks')->info("TargetedSync: room #{$roomId} synced for property #{$propertyId}");
        } catch (\Throwable $e) {
            Log::channel('pms_webhooks')->error("TargetedSync: failed to sync room #{$roomId}", [
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
