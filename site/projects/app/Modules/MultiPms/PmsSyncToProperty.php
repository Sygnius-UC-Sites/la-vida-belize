<?php

namespace App\Modules\MultiPms;

use App\Models\Property;
use App\Modules\MultiPms\Models\PmsRoom;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * Syncs a PmsRoom record into the core properties table so the shared
 * existing property detail page (single.blade.php) works unchanged.
 *
 * Only writes columns that the Property model accepts — never overwrites
 * manually edited fields like header_section, footer_section, notes.
 */
class PmsSyncToProperty
{
    /**
     * Upsert a PmsRoom into the core properties table.
     * Returns the Property record.
     */
    public static function sync(PmsRoom $room): Property
    {
        $data = self::map($room);

        $property = Property::where('pms_source', $room->pms_source)
                            ->where('pms_room_id', $room->pms_room_id)
                            ->first();

        if ($property) {
            // Only update auto-synced fields — never clobber manual edits
            $property->update($data);
        } else {
            // New listings are private until an administrator links and publishes them.
            $property = Property::create(array_merge($data, [
                'status'          => 'false',
                'property_status' => 'draft',
            ]));
        }

        return $property;
    }

    private static function map(PmsRoom $room): array
    {
        $seoUrl = $room->seo_url ?: Str::slug($room->name ?? 'property') . '-' . $room->id;

        return [
            'pms_source'          => $room->pms_source,
            'pms_room_id'         => $room->pms_room_id,
            'name'                => $room->name,
            'seo_url'             => $seoUrl,
            'heading'             => $room->heading ?? $room->property_type,
            'short_description'   => Str::limit(strip_tags($room->description ?? ''), 300),
            'long_description'    => $room->description,
            'description'         => $room->description,
            'bedroom'             => $room->bedrooms,
            'bathroom'            => $room->bathrooms,
            'sleeps'              => $room->sleeps ?? $room->max_people,
            'feature_image'       => $room->image_url,
            'banner_image'        => $room->banner_image ?? $room->image_url,
            'cancellation_policy' => $room->cancellation_policy,
            'booking_policy'      => $room->booking_policy,
            'meta_title'          => $room->meta_title ?? $room->name,
            'meta_keywords'       => $room->meta_keywords,
            'meta_description'    => $room->meta_description ?? Str::limit(strip_tags($room->description ?? ''), 160),
            'map'                 => $room->map,
            'checkin'                => null, // set manually in admin
            'checkout'               => null,
            'instant_booking_button' => 'true', // no DB default — must be set explicitly
            'instant_booking_enabled' => 1,
        ];
    }
}
