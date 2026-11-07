<?php

namespace App\Services\Booking;

class AddonSelection
{
    public function __construct(
        // Phase 3: array of selected property_fees IDs (the canonical addon source)
        public array $fees = [],
        // Legacy flags — kept as no-op shims so existing call sites and tests don't break.
        // QuoteCalculator no longer reads these; they were tied to typed addon tables that no longer exist.
        public bool $hottub = false,
        public int $firewood = 0,
        public int $rv_hookup = 0,
        public int $extra_visitors = 0,
    ) {}

    public static function empty(): self
    {
        return new self();
    }

    public static function fromArray(array $a): self
    {
        $fees = [];
        if (isset($a['fees']) && is_array($a['fees'])) {
            $fees = array_values(array_map('intval', $a['fees']));
        }
        return new self(
            fees: $fees,
            hottub: (bool)($a['hottub'] ?? false),
            firewood: (int)($a['firewood'] ?? 0),
            rv_hookup: (int)($a['rv_hookup'] ?? 0),
            extra_visitors: (int)($a['extra_visitors'] ?? 0),
        );
    }
}
