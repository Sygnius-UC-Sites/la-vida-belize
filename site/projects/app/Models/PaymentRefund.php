<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents one Stripe refund (re_xxx) against a parent Stripe charge.
 *
 * One payment can have multiple PaymentRefund rows (partial refunds).
 * stripe_refund_id has a DB UNIQUE constraint — inserting a duplicate throws
 * QueryException, which callers should catch and treat as "already handled".
 *
 * pms_sync_status flow:
 *   pending → syncing → synced
 *                    ↘ failed (retry_count++)
 *   pending → syncing → not_supported       (Lodgify)
 *   pending → syncing → refund_pending_manual (Hostaway, no confirmed endpoint)
 */
class PaymentRefund extends Model
{
    protected $fillable = [
        'payment_id',
        'stripe_refund_id',
        'amount',
        'currency',
        'pms_sync_status',
        'pms_refund_id',
        'pms_sync_error',
        'retry_count',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'retry_count' => 'integer',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /** Refunds that failed PMS sync and are eligible for retry. */
    public function scopeRetryable($query, int $maxRetries = 3)
    {
        return $query->where('pms_sync_status', 'failed')
                     ->where('retry_count', '<', $maxRetries);
    }

    /** Refunds that have exhausted all retry attempts and need manual action. */
    public function scopeExhausted($query, int $maxRetries = 3)
    {
        return $query->where('pms_sync_status', 'failed')
                     ->where('retry_count', '>=', $maxRetries);
    }
}
