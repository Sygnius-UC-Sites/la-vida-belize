<?php

namespace App\Services\Booking;

class QuoteInput
{
    public function __construct(
        public int $property_id,
        public string $check_in,    // 'YYYY-MM-DD'
        public string $check_out,   // 'YYYY-MM-DD'
        public int $adults,
        public int $children = 0,
        public int $pets = 0,
        public ?AddonSelection $addons = null,
        public ?string $coupon_code = null,
        public ?string $customer_email = null,
    ) {
        $this->addons ??= AddonSelection::empty();
    }

    public static function fromArray(array $a): self
    {
        return new self(
            property_id: (int)$a['property_id'],
            check_in: (string)$a['check_in'],
            check_out: (string)$a['check_out'],
            adults: (int)$a['adults'],
            children: (int)($a['children'] ?? 0),
            pets: (int)($a['pets'] ?? 0),
            addons: isset($a['addons']) ? AddonSelection::fromArray($a['addons']) : AddonSelection::empty(),
            coupon_code: $a['coupon_code'] ?? null,
            customer_email: $a['customer_email'] ?? null,
        );
    }
}
