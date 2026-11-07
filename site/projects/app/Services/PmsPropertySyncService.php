<?php

namespace App\Services;

use App\Models\Pms\PmsImage;
use App\Models\Pms\PmsProperty;
use App\Models\Pms\PmsRoom;
use App\Modules\VacationRental\Pms\PmsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Helper;

/**
 * Persists one PMS property using the same canonical fields consumed by quotes.
 *
 * Webhooks and scheduled reconciliation both use this service so pet policy,
 * optional fees, listing content, and payment policy cannot drift between paths.
 */
class PmsPropertySyncService
{
    public function __construct(private PmsManager $pmsManager)
    {
    }

    public function sync(string $provider, string $propertyId, array $overrides = []): array
    {
        try {
            $adapter = $this->pmsManager->driver($provider);
            $adapter->fetchAndSaveRawData($propertyId);

            $frontendData = $adapter->getFrontendData($propertyId);
            if (empty($frontendData) || ($frontendData['title'] ?? null) === 'Property Not Found') {
                throw new \RuntimeException("{$provider} returned no frontend data for property {$propertyId}");
            }

            if ($provider === 'hostaway' && method_exists($adapter, 'getAutoPaymentRules')) {
                $policy = $adapter->parseAutoPaymentRules($adapter->getAutoPaymentRules());
                $overrides = array_merge([
                    'deposit_percent' => $policy['deposit_percent'] ?? null,
                    'balance_due_days' => $policy['balance_due_days'] ?? null,
                ], $overrides);
            }

            $frontendData = array_replace($frontendData, $overrides);

            $room = DB::transaction(function () use ($provider, $propertyId, $frontendData) {
                $this->persistProperty($provider, $propertyId, $frontendData);
                return $this->persistRoom($provider, $propertyId, $frontendData);
            });

            return [
                'success' => true,
                'message' => "Property {$propertyId} synchronized",
                'room_id' => $room->pms_room_id,
                'property_id' => $room->pms_property_id,
            ];
        } catch (\Throwable $e) {
            Log::channel('pms_webhooks')->error('PmsPropertySyncService failed', [
                'pms' => $provider,
                'property_id' => $propertyId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'room_id' => null,
                'property_id' => $propertyId,
            ];
        }
    }

    private function persistProperty(string $provider, string $propertyId, array $data): void
    {
        $name = $this->string($data['title'] ?? "property-{$propertyId}");
        $seoSlug = (\Illuminate\Support\Str::slug($name) ?: 'listing') . '-' . $propertyId;
        $images = is_array($data['images'] ?? null) ? array_values(array_filter($data['images'])) : [];
        $description = $this->string($data['description'] ?? '');
        $existing = PmsProperty::where('pms_property_id', $propertyId)
            ->where('pms_source', $provider)
            ->first();
        $status = $existing?->status ?? 'false';
        $address = is_array($data['address'] ?? null) ? $data['address'] : [];
        $pricing = is_array($data['pricing'] ?? null) ? $data['pricing'] : [];

        PmsProperty::updateOrCreate(
            ['pms_property_id' => $propertyId, 'pms_source' => $provider],
            [
                'name' => $name,
                'pms_source' => $provider,
                'seo_url' => Helper::getSeoUrlGet($seoSlug),
                'meta_title' => $this->metaText($name, 60),
                'meta_keywords' => $name,
                'meta_description' => $this->metaText($description ?: $name, 160),
                'status' => $status,
                'latitude' => $this->number($address['lat'] ?? $address['latitude'] ?? null),
                'longitude' => $this->number($address['lng'] ?? $address['longitude'] ?? null),
                'address' => $this->nullableString($address['address'] ?? $address['street'] ?? null),
                'zip' => $this->nullableString($address['zip'] ?? $address['zipcode'] ?? null),
                'city' => $this->nullableString($address['city'] ?? null),
                'country' => $this->nullableString($address['country'] ?? $address['countryCode'] ?? null),
                'image_url' => $images[0] ?? null,
                'min_price' => $this->number($pricing['basePrice'] ?? null),
                'currency' => $this->nullableString($pricing['currency'] ?? null),
            ]
        );
    }

    private function persistRoom(string $provider, string $propertyId, array $data): PmsRoom
    {
        $roomId = $this->string($data['room_type_id'] ?? $propertyId);
        $roomName = $this->string($data['title'] ?? "Room {$roomId}");
        $roomSeoSlug = (\Illuminate\Support\Str::slug($roomName) ?: 'listing') . '-' . $roomId;
        $images = is_array($data['images'] ?? null) ? array_values(array_filter($data['images'])) : [];
        $amenities = is_array($data['amenities'] ?? null) ? array_values(array_filter($data['amenities'])) : [];
        $description = $this->string($data['description'] ?? '');
        $existingRoom = PmsRoom::where('pms_room_id', $roomId)
            ->where('pms_source', $provider)
            ->first();
        $status = $existingRoom?->status ?? 'false';
        $address = is_array($data['address'] ?? null) ? $data['address'] : [];
        $pricing = is_array($data['pricing'] ?? null) ? $data['pricing'] : [];
        $keyFacts = is_array($data['key_facts'] ?? null) ? $data['key_facts'] : [];
        $bookingRules = $data['booking_rules'] ?? [];
        $cancellation = $data['cancellation_policy'] ?? '';

        $existingPropertySeoUrl = null;
        if ($roomId === $propertyId) {
            $existingPropertySeoUrl = PmsProperty::where('pms_property_id', $propertyId)
                ->where('pms_source', $provider)
                ->value('seo_url');
        }

        $values = [
            'pms_property_id' => $propertyId,
            'name' => $roomName,
            'description' => $description,
            'max_people' => $this->integer($data['guests'] ?? 2, 2),
            'bedrooms' => $this->integer($data['bedrooms'] ?? 1, 1),
            'bathrooms' => $this->integer($data['bathrooms'] ?? 1, 1),
            'area' => $this->number($data['area'] ?? null),
            'area_unit' => $this->string($data['area_unit'] ?? ''),
            'currency' => $this->string($pricing['currency'] ?? 'USD'),
            'min_price' => $this->number($pricing['basePrice'] ?? null),
            'api_pms' => $provider,
            'pms_source' => $provider,
            'seo_url' => $existingPropertySeoUrl ?? Helper::getSeoUrlGet($roomSeoSlug),
            'status' => $status,
            'active' => $existingRoom ? (int) $existingRoom->active : 0,
            'image_url' => $images[0] ?? null,
            'images_json' => json_encode($images),
            'amenities_json' => json_encode($amenities),
            'house_rules' => $this->string($data['house_rules'] ?? ''),
            'cancellation_policy' => is_array($cancellation) ? json_encode($cancellation) : (string) $cancellation,
            'booking_policy' => is_array($bookingRules) ? json_encode($bookingRules) : (string) $bookingRules,
            'key_facts_json' => json_encode($keyFacts),
            'pricing_json' => json_encode($data['pricing_normalized'] ?? []),
            'meta_title' => $this->metaText($roomName, 60),
            'meta_description' => $this->metaText($description ?: $roomName, 160),
            'address' => $this->nullableString($address['address'] ?? $address['street'] ?? null),
            'city' => $this->nullableString($address['city'] ?? null),
            'state' => $this->nullableString($address['state'] ?? null),
            'country' => $this->nullableString($address['country'] ?? $address['countryCode'] ?? null),
            'zip' => $this->nullableString($address['zip'] ?? $address['zipcode'] ?? null),
            'lat' => $this->number($address['lat'] ?? $address['latitude'] ?? null),
            'lng' => $this->number($address['lng'] ?? $address['longitude'] ?? null),
            'property_type' => $this->nullableString($data['property_type'] ?? null),
            'bed_config_json' => !empty($data['bed_config']) ? json_encode($data['bed_config']) : null,
            'last_synced_at' => now(),
        ];

        if (!empty($data['deposit_percent'])) {
            $values['deposit_percent'] = (int) $data['deposit_percent'];
        }
        if (array_key_exists('balance_due_days', $data)) {
            $values['balance_due_days'] = $data['balance_due_days'];
        }
        if (array_key_exists('pets_allowed', $keyFacts) && $keyFacts['pets_allowed'] !== null) {
            $values['pets_allowed'] = $keyFacts['pets_allowed'] ? 1 : 0;
        }
        if (array_key_exists('max_pets', $data) && $data['max_pets'] !== null) {
            $values['max_pets'] = (int) $data['max_pets'];
        }
        // null means the fee endpoint failed or has never been verified. Preserve
        // the last known definitions. An empty array means a successful fetch
        // confirmed that the property currently has no configurable fees.
        if (isset($data['canonical_fees']) && is_array($data['canonical_fees'])) {
            $values['optional_fees_json'] = json_encode($data['canonical_fees']);
        }

        // NEVER overwrite an existing seo_url. The slug is the public URL of
        // the property (copied from the host site at install so old links and
        // the site's own page keep working) - regenerating it from the PMS
        // listing title breaks every existing link and the widget-mode lookup.
        if ($existingRoom && !empty($existingRoom->seo_url)) {
            $values['seo_url'] = $existingRoom->seo_url;
        }

        $room = PmsRoom::updateOrCreate(
            ['pms_room_id' => $roomId, 'pms_source' => $provider],
            $values
        );

        PmsImage::where('pms_room_id', $roomId)
            ->where('pms_property_id', $propertyId)
            ->delete();

        foreach ($images as $index => $url) {
            PmsImage::create([
                'pms_property_id' => $propertyId,
                'pms_room_id' => $roomId,
                'text' => $roomName . ' - Photo ' . ($index + 1),
                'url' => $url,
            ]);
        }

        return $room;
    }

    private function string(mixed $value): string
    {
        $string = is_array($value)
            ? implode("\n\n", array_filter(array_map('strval', $value)))
            : (string) ($value ?? '');

        return $this->scrubUtf8($string);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = $this->string($value);
        return $value === '' ? null : $value;
    }

    private function metaText(mixed $value, int $limit): string
    {
        $text = trim(strip_tags($this->string($value)));

        return Str::limit($text, $limit, '');
    }

    private function scrubUtf8(string $value): string
    {
        $value = str_replace("\0", '', $value);
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_scrub')) {
            return mb_scrub($value, 'UTF-8');
        }

        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function integer(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }
}
