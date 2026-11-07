<?php

namespace App\Services\Booking;

class Quote
{
    public int $nights = 0;
    /** @var array<int, array{label: string, amount: float, taxable: bool, refundable: bool, category: string}> */
    public array $breakdown = [];
    public ?Discount $discount = null;
    public float $subtotal = 0.0;
    public float $tax = 0.0;
    public float $damage_deposit = 0.0;
    public float $total = 0.0;
    /** @var array<int, array{due_date: string, amount: float, sequence: int}> */
    public array $installments = [];
    /** @var array<int, string> */
    public array $errors = [];
    public bool $valid = true;

    // Phase 1 additions:
    /** Sum of all "nights" line items before LOS discount and any post-aggregation adjustments. */
    public float $subtotal_nights_only = 0.0;

    /** LOS discount summary; null when none applied. */
    public ?array $los_discount = null;
    // Shape: ['label' => 'Weekly stay discount', 'percent' => 10, 'amount' => 45.00]

    /** Coupon discount summary; null when none applied. */
    public ?array $coupon_discount = null;
    // Shape: ['code' => 'SUMMER10', 'amount' => 30.00, 'label' => 'Coupon SUMMER10 (-10%)']

    public function toArray(): array
    {
        return [
            'nights' => $this->nights,
            'breakdown' => $this->breakdown,
            'discount' => $this->discount?->toArray(),
            'subtotal' => $this->subtotal,
            'tax' => $this->tax,
            'damage_deposit' => $this->damage_deposit,
            'total' => $this->total,
            'installments' => $this->installments,
            'errors' => $this->errors,
            'valid' => $this->valid,
            // Phase 1 additions:
            'subtotal_nights_only' => $this->subtotal_nights_only,
            'los_discount' => $this->los_discount,
            'coupon_discount' => $this->coupon_discount,
        ];
    }
}

class Discount
{
    public function __construct(
        public string $label,
        public float $amount,
    ) {}

    public function toArray(): array
    {
        return ['label' => $this->label, 'amount' => $this->amount];
    }
}
