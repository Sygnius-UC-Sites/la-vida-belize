<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Model;

/**
 * PmsCalendar — one row per (pms_source, pms_room_id, date).
 *
 * state: -1 = unknown, 0 = unavailable, 1 = available
 *
 * Use upsert() for bulk writes — the unique key (pms_source, pms_room_id, date)
 * guarantees idempotency and prevents duplicates under concurrent syncs.
 */
class PmsCalendar extends Model
{
    protected $table = 'pms_calendar';

    public $timestamps = false;  // we manage synced_at ourselves via useCurrentOnUpdate

    protected $fillable = [
        'pms_source',
        'pms_property_id',
        'pms_room_id',
        'date',
        'state',
        'inventory',
        'nightly_rate',
        'currency',
        'min_stay',
        'max_stay',
        'closed_to_arrival',
        'closed_to_departure',
        'stop_sell',
        'pms_updated_at',
        'synced_at',
    ];

    protected $casts = [
        'date'               => 'date:Y-m-d',
        'state'              => 'integer',
        'inventory'          => 'integer',
        'nightly_rate'       => 'decimal:4',
        'min_stay'           => 'integer',
        'max_stay'           => 'integer',
        'closed_to_arrival'  => 'boolean',
        'closed_to_departure'=> 'boolean',
        'stop_sell'          => 'boolean',
        'pms_updated_at'     => 'datetime',
        'synced_at'          => 'datetime',
    ];

    // ── State constants ───────────────────────────────────────────────────────
    const STATE_UNKNOWN     = -1;
    const STATE_UNAVAILABLE =  0;
    const STATE_AVAILABLE   =  1;

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeForRoom($query, string $pmsSource, string $roomId)
    {
        return $query->where('pms_source', $pmsSource)->where('pms_room_id', $roomId);
    }

    public function scopeInRange($query, string $from, string $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    public function scopeAvailable($query)
    {
        return $query->where('state', self::STATE_AVAILABLE);
    }

    public function scopeUnavailable($query)
    {
        return $query->where('state', self::STATE_UNAVAILABLE);
    }

    /**
     * Returns true if this date is in an unknown state and must not be booked.
     */
    public function isUnknown(): bool
    {
        return $this->state === self::STATE_UNKNOWN;
    }

    public function isAvailable(): bool
    {
        return $this->state === self::STATE_AVAILABLE;
    }
}
