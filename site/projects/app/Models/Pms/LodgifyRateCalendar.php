<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per Lodgify rate band per date.
 * Multiple rows per (property_id, room_type_id, date) are normal — Lodgify
 * supports different prices for different minimum/maximum-stay lengths.
 *
 * Uniqueness key: (property_id, room_type_id, date, is_default, min_stay, max_stay)
 */
class LodgifyRateCalendar extends Model
{
    protected $table = 'lodgify_rate_calendar';
    public $timestamps = false; // synced_at is managed by DB ON UPDATE CURRENT_TIMESTAMP

    protected $fillable = [
        'property_id', 'room_type_id', 'date',
        'is_default', 'min_stay', 'max_stay',
        'price_per_day', 'price_per_additional_guest', 'additional_guests_starts_from',
        'currency_code', 'vat', 'is_vat_exclusive',
        'source_api_version', 'pms_updated_at', 'synced_at',
    ];

    protected $casts = [
        'date'              => 'date:Y-m-d',
        'is_default'        => 'boolean',
        'is_vat_exclusive'  => 'boolean',
        'price_per_day'                => 'decimal:4',
        'price_per_additional_guest'   => 'decimal:4',
        'vat'               => 'decimal:2',
        'pms_updated_at'    => 'datetime',
        'synced_at'         => 'datetime',
    ];

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Return the lowest valid price_per_day for a given date/room across all bands.
     * Used for calendar display ("From $X" before dates are selected).
     */
    public static function lowestForDate(int $propertyId, int $roomTypeId, string $date): ?float
    {
        return static::where('property_id', $propertyId)
            ->where('room_type_id', $roomTypeId)
            ->where('date', $date)
            ->min('price_per_day');
    }

    /**
     * Return the applicable rate band for a specific stay length.
     * Lodgify: choose the band where min_stay <= nights AND (max_stay == 0 OR max_stay >= nights).
     * When multiple bands match, prefer the date-specific (is_default=false) row.
     * Among equals, prefer the highest min_stay (most specific band).
     */
    public static function bandForStay(int $propertyId, int $roomTypeId, string $date, int $nights): ?self
    {
        return static::where('property_id', $propertyId)
            ->where('room_type_id', $roomTypeId)
            ->where('date', $date)
            ->where('min_stay', '<=', $nights)
            ->where(function ($q) use ($nights) {
                $q->where('max_stay', 0)           // 0 = unlimited
                  ->orWhere('max_stay', '>=', $nights);
            })
            ->orderBy('is_default', 'asc')  // date-specific (false) first
            ->orderBy('min_stay', 'desc')   // most specific band first
            ->first();
    }

    /**
     * Return a map of date → lowest price_per_day for a date range.
     * Used by AvailabilityController to inject prices into the response.
     */
    public static function lowestRatesForRange(int $propertyId, int $roomTypeId, string $from, string $to): array
    {
        return static::where('property_id', $propertyId)
            ->where('room_type_id', $roomTypeId)
            ->whereBetween('date', [$from, $to])
            ->selectRaw('date, MIN(price_per_day) as min_price')
            ->groupBy('date')
            ->pluck('min_price', 'date')
            ->toArray();
    }
}
