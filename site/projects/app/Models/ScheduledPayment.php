<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledPayment extends Model
{
    protected $fillable = [
        'booking_request_id',
        'pms_source',
        'pms_booking_id',
        'stripe_customer_id',
        'stripe_payment_method_id',
        'amount',
        'currency',
        'due_date',
        'status',
        'attempts',
        'charged_at',
        'stripe_charge_id',
        'error_message',
        'pms_notified',
        'pms_notify_error',
        'pms_payment_id',   // PMS-returned ID for this specific installment
        'reminder_sent',
    ];

    protected $casts = [
        'due_date'      => 'date',
        'charged_at'    => 'datetime',
        'amount'        => 'decimal:2',
        'pms_notified'  => 'boolean',
        'reminder_sent' => 'boolean',
    ];

    public function bookingRequest(): BelongsTo
    {
        return $this->belongsTo(BookingRequest::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    /**
     * Payments due today or earlier that are still pending.
     * Used by the ChargeScheduledPayments command.
     */
    public function scopeDue($query)
    {
        return $query->where('status', 'pending')
                     ->where('due_date', '<=', now()->toDateString());
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Installments charged by Stripe but not yet recorded in the PMS.
     * Used by RetryPmsPaymentSync command.
     */
    public function scopePmsUnsynced($query)
    {
        return $query->where('status', 'charged')
                     ->where('pms_notified', false)
                     ->whereNotNull('pms_notify_error'); // only ones that were attempted and failed
    }
}
