<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\VacationRental\Pms\PmsManager;
use App\Models\Pms\PmsProperty;
use App\Models\Pms\PmsRoom;
use App\Models\Pms\PmsBooking;
use App\Models\Pms\PmsImage;
use App\Services\PmsPropertySyncService;
use Helper;

class SyncAllPmsData extends Command
{
    protected $signature = 'pms:sync-all {--provider= : Sync only a specific provider (lodgify, hostfully, guesty, hostaway)}';
    protected $description = 'Sync properties, rooms, and bookings from all configured PMS providers (Lodgify, Hostfully, Guesty, Hostaway)';

    public function handle(PmsManager $pmsManager, PmsPropertySyncService $propertySync)
    {
        $requestedProvider = $this->option('provider');
        $providers = $requestedProvider ? [$requestedProvider] : ['lodgify', 'hostfully', 'guesty', 'hostaway'];

        foreach ($providers as $provider) {
            $this->info("Starting sync for provider: {$provider}");

            try {
                $adapter = $pmsManager->driver($provider);
            } catch (\Exception $e) {
                $this->error("Failed to load adapter for {$provider}: " . $e->getMessage());
                continue;
            }

            // Incremental sync: find the oldest last_synced_at for this provider's rooms.
            // If any room has never been synced (null), do a full sync. Otherwise only
            // fetch listings updated since that timestamp (Guesty + Hostaway support this).
            $incrementalProviders = ['guesty', 'hostaway'];
            $updatedSince = null;
            if (in_array($provider, $incrementalProviders)) {
                $oldestSync = PmsRoom::where('pms_source', $provider)->min('last_synced_at');
                if ($oldestSync !== null) {
                    $updatedSince = $oldestSync;
                    $this->info("Incremental sync for {$provider} — fetching changes since {$updatedSince}");
                } else {
                    $this->info("Full sync for {$provider} — no last_synced_at found (first run)");
                }
            }

            // Sync Properties
            $this->info("Fetching properties from {$provider}...");
            $propertiesRes = $adapter->listProperties($updatedSince ? ['updated_since' => $updatedSince] : []);

            if (($propertiesRes['status'] ?? 0) !== 200) {
                $errorMsg = is_array($propertiesRes['error'] ?? null) ? json_encode($propertiesRes['error']) : ($propertiesRes['error'] ?? 'Unknown Error');
                $this->error("Failed to fetch properties from {$provider}: " . $errorMsg);
                continue;
            }

            $propertiesData = $propertiesRes['data'] ?? [];
            $propertiesList = [];
            if ($provider === 'lodgify') {
                $propertiesList = $propertiesData;
            } elseif ($provider === 'hostfully') {
                $propertiesList = $propertiesData['properties'] ?? [];
            } elseif ($provider === 'guesty') {
                $propertiesList = $propertiesData['results'] ?? [];
            } elseif ($provider === 'hostaway') {
                $propertiesList = $propertiesData['result'] ?? [];
            } else {
                $propertiesList = $propertiesData;
            }

            $this->info("Found " . count($propertiesList) . " properties.");

            // Hostaway: fetch account-level auto-payment rules ONCE before the property loop.
            // Rules are account-wide (not per-listing), so we apply the same
            // deposit_percent + balance_due_days to all Hostaway properties.
            $hostaway_payment_policy = ['deposit_percent' => null, 'balance_due_days' => null];
            if ($provider === 'hostaway' && method_exists($adapter, 'getAutoPaymentRules')) {
                $rulesData = $adapter->getAutoPaymentRules();
                $hostaway_payment_policy = $adapter->parseAutoPaymentRules($rulesData);
                $this->info("  Hostaway auto-payment rules: deposit={$hostaway_payment_policy['deposit_percent']}%, balance_due_days={$hostaway_payment_policy['balance_due_days']}");
            }

            $seenPropertyIds = [];
            foreach ($propertiesList as $propData) {
                $propertyId = null;
                if ($provider === 'hostfully') {
                    $propertyId = $propData['uid'] ?? null;
                } elseif ($provider === 'guesty') {
                    $propertyId = $propData['_id'] ?? null;
                } else {
                    $propertyId = $propData['id'] ?? null;
                }

                if (!$propertyId) continue;
                $seenPropertyIds[] = (string) $propertyId;

                $overrides = $provider === 'hostaway' ? $hostaway_payment_policy : [];
                $result = $propertySync->sync($provider, (string) $propertyId, $overrides);
                if (!$result['success']) {
                    $this->error("  - Property {$propertyId} failed: {$result['message']}");
                } else {
                    $this->info("  - Synced property {$propertyId}");
                }
            }

            // Listings the provider no longer returns are marked inactive —
            // never deleted (the host may have unlisted temporarily, and
            // bookings/history must survive). Only on FULL syncs: an
            // incremental (updated_since) response omits unchanged listings,
            // which says nothing about whether they still exist.
            if (!$updatedSince && !empty($seenPropertyIds)) {
                $stale = PmsRoom::where('pms_source', $provider)
                    ->whereNotIn('pms_property_id', $seenPropertyIds)
                    ->where('status', 'true')
                    ->get();
                if ($stale->count()) {
                    PmsRoom::whereIn('id', $stale->pluck('id'))->update(['status' => 'false']);
                    \App\Models\Pms\PmsProperty::where('pms_source', $provider)
                        ->whereNotIn('pms_property_id', $seenPropertyIds)
                        ->update(['status' => 'false']);
                    foreach ($stale as $s) {
                        $this->warn("  ! '{$s->name}' ({$s->pms_property_id}) is no longer in the {$provider} account — marked inactive (kept, not deleted).");
                    }
                }
            }

            // Sync Bookings
            $this->info("Fetching bookings from {$provider}...");
            $bookingsRes = $adapter->listReservations();
            
            if (($bookingsRes['status'] ?? 0) === 200) {
                $bookingsData = $bookingsRes['data'] ?? [];
                
                $bookingsList = [];
                if ($provider === 'lodgify') {
                    $bookingsList = $bookingsData['items'] ?? [];
                } elseif ($provider === 'hostfully') {
                    $bookingsList = $bookingsData['leads'] ?? [];
                } elseif ($provider === 'guesty') {
                    $bookingsList = $bookingsData['results'] ?? [];
                } elseif ($provider === 'hostaway') {
                    $bookingsList = $bookingsData['result'] ?? [];
                } else {
                    $bookingsList = $bookingsData;
                }

                $this->info("Found " . count($bookingsList) . " bookings.");
                foreach ($bookingsList as $bookingData) {
                    $this->syncBooking($provider, $bookingData);
                }
            } else {
                $errorMsg = is_array($bookingsRes['error'] ?? null) ? json_encode($bookingsRes['error']) : ($bookingsRes['error'] ?? 'Unknown Error');
                $this->error("Failed to fetch bookings from {$provider}: " . $errorMsg);
            }
        }

        // [PMS-MODULE] keep the site's legacy listing table fresh so homepage/
        // listing sections (whatever their per-site design) show PMS data.
        try { $this->call('pms:sync-calendars'); } catch (\Throwable $e) { $this->warn('Calendar sync skipped: '.$e->getMessage()); }
        try { $this->call('pms:backfill-legacy'); } catch (\Throwable $e) { $this->warn('Legacy backfill skipped: '.$e->getMessage()); }

        $this->info("PMS Sync Complete!");
        return self::SUCCESS;
    }

    protected function syncProperty($provider, $propertyId, $frontendData)
    {
        $name = $frontendData['title'] ?? "property-{$propertyId}";
        $seoSlug = strtolower(str_replace(' ', '-', $name)) . '-' . $propertyId;

        $existing = PmsProperty::where('pms_property_id', (string) $propertyId)
            ->where('pms_source', $provider)
            ->first();
        // Synchronization imports data; only an explicit admin action publishes it.
        $status = $existing?->status ?? 'false';

        PmsProperty::updateOrCreate(
            ['pms_property_id' => (string)$propertyId, 'pms_source' => $provider],
            [
                'name'             => $name,
                'pms_source'       => $provider,
                'seo_url'          => Helper::getSeoUrlGet($seoSlug),
                'meta_title'       => substr($name, 0, 60),
                'meta_keywords'    => $name,
                'meta_description' => substr(trim(strip_tags((string)($frontendData['description'] ?? $name))), 0, 160),
                'status'           => $status,
                'latitude'         => $frontendData['address']['lat'] ?? null,
                'longitude'        => $frontendData['address']['lng'] ?? null,
                'address'          => $frontendData['address']['address'] ?? null,
                'zip'              => $frontendData['address']['zip'] ?? null,
                'city'             => $frontendData['address']['city'] ?? null,
                'country'          => $frontendData['address']['country'] ?? null,
                'image_url'        => $frontendData['images'][0] ?? null,
                'min_price'        => $frontendData['pricing']['basePrice'] ?? null,
                'currency'         => $frontendData['pricing']['currency'] ?? null
            ]
        );

        if ($status === 'false') {
            $this->warn("  - Hidden (no images/description in PMS): {$name} ({$propertyId})");
        } else {
            $this->info("  - Synced property: {$name} ({$propertyId})");
        }
    }

    protected function syncRoomsFromFrontendData($provider, $propertyId, $frontendData)
    {
        // For simplicity, we assume one "Room" per "Property" for most PMS in this redesign,
        // or we just use the main frontend data for the canonical room index.
        // Use adapter-provided room_type_id when available (e.g. Lodgify rooms[0].id ≠ property id)
        // ── Bulletproof type helpers — prevent "Array to string" from any adapter ──
        // Any field from any PMS adapter may unexpectedly return an array; cast everything
        // to its expected scalar type before touching strings or the database.
        $str  = fn($v) => is_array($v) ? implode("\n\n", array_filter(array_map('strval', $v))) : (string)($v ?? '');
        $num  = fn($v, $def = null) => is_numeric($v) ? (float)$v : $def;
        $intv = fn($v, $def = 1) => is_numeric($v) ? (int)$v : $def;

        $roomId   = $str($frontendData['room_type_id'] ?? $propertyId);
        $roomName = $str($frontendData['title'] ?? "Room {$roomId}");
        $roomSeoSlug = strtolower(str_replace(' ', '-', $roomName)) . '-' . $roomId;

        // When roomId == propertyId (Guesty, Hostaway, Hostfully — 1:1 mapping),
        // reuse the seo_url already stored in pms_properties so both tables stay in sync.
        // Lodgify has different room_type_id vs property_id so it falls through to the generated slug.
        $existingPropertySeoUrl = null;
        if ((string)$roomId === (string)$propertyId) {
            $existingPropertySeoUrl = \App\Models\Pms\PmsProperty::where('pms_property_id', (string)$propertyId)
                ->where('pms_source', $provider)
                ->value('seo_url');
        }

        $images    = isset($frontendData['images']) && is_array($frontendData['images']) ? array_values(array_filter($frontendData['images'])) : [];
        $amenities = isset($frontendData['amenities']) && is_array($frontendData['amenities']) ? array_values(array_filter($frontendData['amenities'])) : [];
        $existingRoom = PmsRoom::where('pms_room_id', (string) $roomId)
            ->where('pms_source', $provider)
            ->first();
        $status = $existingRoom?->status ?? 'false';

        $cancellation  = $frontendData['cancellation_policy'] ?? '';
        $bookingRules  = $frontendData['booking_rules'] ?? [];
        $bookingPolicy = is_array($bookingRules) ? json_encode($bookingRules) : (string)$bookingRules;

        // Address — all adapters return an 'address' array
        $addr    = is_array($frontendData['address'] ?? null) ? $frontendData['address'] : [];
        $lat     = $num($addr['lat']  ?? ($addr['latitude']  ?? null));
        $lng     = $num($addr['lng']  ?? ($addr['longitude'] ?? null));
        $address = $str($addr['address']  ?? $addr['street']  ?? '');
        $city    = $str($addr['city']     ?? '');
        $state   = $str($addr['state']    ?? '');
        $country = $str($addr['country']  ?? $addr['countryCode'] ?? '');
        $zip     = $str($addr['zip']      ?? $addr['zipcode'] ?? '');

        $propertyType  = $str($frontendData['property_type'] ?? '') ?: null;
        $bedConfig     = $frontendData['bed_config'] ?? null;

        $room = PmsRoom::updateOrCreate(
            ['pms_room_id' => (string)$roomId, 'pms_source' => $provider],
            [
                'pms_property_id'     => (string)$propertyId,
                'name'                => $str($roomName),
                'description'         => $str($frontendData['description'] ?? ''),
                'max_people'          => $intv($frontendData['guests'] ?? 2, 2),
                'bedrooms'            => $intv($frontendData['bedrooms'] ?? 1, 1),
                'bathrooms'           => $intv($frontendData['bathrooms'] ?? 1, 1),
                'area'                => $num($frontendData['area'] ?? null),
                'area_unit'           => $str($frontendData['area_unit'] ?? ''),
                'currency'            => $str($frontendData['pricing']['currency'] ?? 'USD'),
                'min_price'           => $num($frontendData['pricing']['basePrice'] ?? null),
                'api_pms'             => $provider,
                'pms_source'          => $provider,
                'seo_url'             => $existingPropertySeoUrl ?? Helper::getSeoUrlGet($roomSeoSlug),
                'status'              => $status,
                'active'              => $existingRoom ? (int) $existingRoom->active : 0,
                'image_url'           => $images[0] ?? null,
                'images_json'         => json_encode($images),
                'amenities_json'      => json_encode($amenities),
                'house_rules'         => $str($frontendData['house_rules'] ?? ''),
                'cancellation_policy' => is_array($cancellation) ? json_encode($cancellation) : (string)$cancellation,
                'booking_policy'      => $bookingPolicy,
                'key_facts_json'      => json_encode($frontendData['key_facts'] ?? []),
                'pricing_json'        => json_encode($frontendData['pricing_normalized'] ?? []),
                'meta_title'          => substr($str($roomName), 0, 60),
                'meta_description'    => substr(trim(strip_tags($str($frontendData['description'] ?? $roomName))), 0, 160),
                // Location fields
                'address'             => $address ?: null,
                'city'                => $city    ?: null,
                'state'               => $state   ?: null,
                'country'             => $country ?: null,
                'zip'                 => $zip     ?: null,
                'lat'                 => $lat,
                'lng'                 => $lng,
                // Property classification
                'property_type'       => $propertyType,
                'bed_config_json'     => $bedConfig ? json_encode($bedConfig) : null,
                // Deposit percent from PMS — only overwrite when the adapter provides a value,
                // so admin manual overrides set in the panel are not erased on sync.
                ...( isset($frontendData['deposit_percent']) && $frontendData['deposit_percent'] > 0
                    ? ['deposit_percent' => (int)$frontendData['deposit_percent']]
                    : [] ),
                ...( array_key_exists('balance_due_days', $frontendData)
                    ? ['balance_due_days' => $frontendData['balance_due_days']]
                    : [] ),

                // ── Pet policy ──────────────────────────────────────────────
                // pets_allowed: from key_facts (PMS-authoritative when available,
                //   otherwise inferred from amenity/house-rules text by ListingFacts).
                //   null = unknown (don't assume allowed or not allowed)
                //   1    = allowed (show selector)
                //   0    = not allowed (hide selector / show "No pets")
                // Only overwrite when the PMS returns a definitive value; a null from
                // one sync must not erase a manually set admin value.
                ...( array_key_exists('pets_allowed', $frontendData['key_facts'] ?? [])
                    && $frontendData['key_facts']['pets_allowed'] !== null
                    ? ['pets_allowed' => $frontendData['key_facts']['pets_allowed'] ? 1 : 0]
                    : [] ),

                // max_pets: from PMS where exposed (Hostaway: maxPetsAllowed).
                // null = no known limit. 0 = not allowed (same as pets_allowed=0).
                ...( array_key_exists('max_pets', $frontendData)
                    && $frontendData['max_pets'] !== null
                    ? ['max_pets' => (int)$frontendData['max_pets']]
                    : [] ),

                // ── Fee definitions ─────────────────────────────────────────
                // Canonical fee/add-on definitions for this property.
                // Built by each adapter from its fee API; null until first sync.
                ...( array_key_exists('canonical_fees', $frontendData)
                    ? ['optional_fees_json' => json_encode($frontendData['canonical_fees'])]
                    : [] ),

                'last_synced_at'      => now(),
            ]
        );

        // Refresh structured gallery rows so the front-end gallery always matches the latest sync.
        PmsImage::where('pms_room_id', (string)$roomId)->delete();
        foreach ($images as $idx => $url) {
            PmsImage::create([
                'pms_property_id' => (string)$propertyId,
                'pms_room_id'     => (string)$roomId,
                'text'            => $roomName . ' - Photo ' . ($idx + 1),
                'url'             => $url,
            ]);
        }

        $this->line("    - Synced room {$roomId}: " . count($images) . " photos, " . count($amenities) . " amenities" . ($status === 'true' ? '' : ' [HIDDEN: empty]'));
    }

    /**
     * Normalize and store a single booking record.
     *
     * Each PMS uses different field names for booking ID, arrival, and departure.
     * This method resolves the correct field names per provider before persisting.
     *
     * Field map by provider:
     *   lodgify   : id          | date_arrival          | date_departure
     *   hostfully : uid         | checkInDate           | checkOutDate
     *   guesty    : _id         | checkInDateLocalized  | checkOutDateLocalized
     *   hostaway  : id          | arrivalDate           | departureDate
     */
    protected function syncBooking($provider, $data)
    {
        // ── Resolve booking ID field ───────────────────────────────────────────
        $bookingId = match ($provider) {
            'hostfully' => $data['uid'] ?? null,
            'guesty'    => $data['_id'] ?? null,
            default     => $data['id'] ?? null,  // lodgify, hostaway
        };

        if (!$bookingId) return;

        // ── Resolve arrival / departure field names per provider ───────────────
        $arrival = match ($provider) {
            'lodgify'   => $data['date_arrival'] ?? $data['arrival'] ?? null,
            'hostfully' => $data['checkInLocalDateTime'] ?? $data['checkInDate'] ?? $data['checkInLocalDate'] ?? $data['arrival'] ?? null,
            'guesty'    => $data['checkInDateLocalized'] ?? $data['checkIn'] ?? $data['arrival'] ?? null,
            'hostaway'  => $data['arrivalDate'] ?? $data['arrival'] ?? null,
            default     => $data['arrival'] ?? null,
        };

        $departure = match ($provider) {
            'lodgify'   => $data['date_departure'] ?? $data['departure'] ?? null,
            'hostfully' => $data['checkOutLocalDateTime'] ?? $data['checkOutDate'] ?? $data['checkOutLocalDate'] ?? $data['departure'] ?? null,
            'guesty'    => $data['checkOutDateLocalized'] ?? $data['checkOut'] ?? $data['departure'] ?? null,
            'hostaway'  => $data['departureDate'] ?? $data['departure'] ?? null,
            default     => $data['departure'] ?? null,
        };

        // ── Resolve property ID field ──────────────────────────────────────────
        $propertyId = match ($provider) {
            'hostfully' => $data['propertyUid'] ?? $data['property_id'] ?? null,
            'guesty'    => $data['listingId'] ?? $data['property_id'] ?? null,
            'hostaway'  => $data['listingId'] ?? $data['listingMapId'] ?? $data['property_id'] ?? null,
            default     => $data['property_id'] ?? null,  // lodgify
        };

        // ── Resolve status field ───────────────────────────────────────────────
        $status = match ($provider) {
            'hostaway'  => $data['status'] ?? $data['reservationStatus'] ?? 'unknown',
            'guesty'    => $data['status'] ?? 'Booked', // Default to Booked if missing
            default     => $data['status'] ?? 'unknown',
        };

        PmsBooking::updateOrCreate(
            ['pms_booking_id' => (string)$bookingId],
            [
                'pms_source'   => $provider,
                'booking_type' => $provider,
                'property_id'  => $propertyId,
                'arrival'      => $this->normalizeDate($arrival),
                'departure'    => $this->normalizeDate($departure),
                'status'       => $status,
                'guest'        => isset($data['guest']) ? json_encode($data['guest']) : null,
                'type'         => $data['type'] ?? null,
                'total_amount' => $data['totalPrice'] ?? $data['amount'] ?? $data['total_amount'] ?? 0,
            ]
        );
    }

    /**
     * Normalize a date value to MySQL DATE format (YYYY-MM-DD).
     *
     * Handles:
     *   - ISO 8601 datetime strings: '2026-06-04T20:00:00.000Z' → '2026-06-04'
     *   - Timestamp integers: 1749081600 → '2026-06-04'
     *   - Already formatted dates: '2026-06-04' → '2026-06-04'
     *   - null → null
     */
    protected function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // ISO 8601 datetime string with T separator
        if (is_string($value) && str_contains($value, 'T')) {
            return substr($value, 0, 10);
        }

        // Unix timestamp integer
        if (is_numeric($value) && strlen((string)$value) >= 9) {
            return date('Y-m-d', (int)$value);
        }

        // Already a date string or parseable format
        try {
            $dt = new \DateTime($value);
            return $dt->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }
}
