<?php

namespace App\Helper;

/**
 * Converts raw PMS fee/add-on objects into one canonical shape used by:
 *   - pms_rooms.optional_fees_json  (stored per property on sync)
 *   - QuoteNormalizer optional_items  (per quote, subset of the above)
 *   - Blade fee controls  (driven entirely by this shape, never fee names)
 *
 * Canonical shape (all fields always present, null when unknown):
 * {
 *   "pms_id":              string   — PMS-native fee/add-on ID (UUID, int, etc.)
 *   "name":                string   — display label only; never used for logic
 *   "is_mandatory":        bool     — true = auto-include, no selector shown
 *   "is_optional":         bool     — true = guest can add/remove
 *   "requires_approval":   bool     — true = "Request this service", do not charge
 *   "is_active":           bool     — false = hide completely
 *   "scope":               string   — per_stay|per_night|per_pet|per_guest|
 *                                     per_guest_per_night|per_pet_per_night|
 *                                     per_unit|percentage_of_total|percentage_of_rent
 *   "amount":              float    — unit price in major currency units; null for %
 *   "percentage":          float    — percentage value (e.g. 10.0 for 10%); null for flat
 *   "tax_rate":            float    — tax rate applied on top of amount (0 = none)
 *   "is_quantity_selectable": bool  — true = stepper; false = yes/no toggle
 *   "min_qty":             int      — minimum quantity (usually 0 or 1)
 *   "max_qty":             int      — maximum quantity (1 = toggle, N = stepper)
 *   "is_per_pet":          bool     — connect to pet selector, not a separate control
 *   "is_per_guest":        bool     — auto-use current guest count
 *   "pms_source":          string   — "hostfully"|"hostaway"|"lodgify"|"guesty"
 * }
 *
 * UI RULES (applied in the blade, not here):
 *   is_mandatory=true                 → show in breakdown only, no selector
 *   is_optional, !is_quantity_selectable → toggle
 *   is_optional, is_quantity_selectable  → stepper (min_qty to max_qty)
 *   is_per_pet=true                   → link to pet stepper, no separate control
 *   is_per_guest=true                 → auto-apply, no selector
 *   percentage != null                → show toggle, NEVER compute JS amount
 *   requires_approval=true            → show "Request this service"
 *   is_active=false                   → hidden entirely
 */
class CanonicalFee
{
    // ── Hostfully ─────────────────────────────────────────────────────────────
    // Source: GET /api/v3.3/fees (stored in HostfullyData.raw_property_json['fees'])
    // Also: quote response overrides.fees.otherFees[] for optional fees with live pricing.
    //
    // Hostfully fee fields (v3.3):
    //   uid, name, type (scope enum), netPrice, amount, taxationRate,
    //   isOptional, isActive (assumed true if missing), scope (matches type)
    //
    // Scope enum: PER_STAY, PER_NIGHT, PER_GUEST, PER_GUEST_PER_NIGHT,
    //             PER_PET, PER_PET_PER_NIGHT, PERCENTAGE_OF_TOTAL, PERCENTAGE_OF_RENT
    public static function fromHostfully(array $fee): ?array
    {
        $uid = (string)($fee['uid'] ?? $fee['feeUid'] ?? $fee['feeId'] ?? '');
        if (!$uid) return null;

        $rawScope = strtoupper($fee['type'] ?? $fee['scope'] ?? 'PER_STAY');
        $scope    = self::hostfullyScope($rawScope);
        $isPercentage = str_starts_with($rawScope, 'PERCENTAGE');

        $amount     = $isPercentage ? null : (float)($fee['netPrice'] ?? $fee['amount'] ?? 0);
        $percentage = $isPercentage ? (float)($fee['amount'] ?? 0) : null;

        $isOptional  = (bool)($fee['isOptional'] ?? false);
        $isMandatory = !$isOptional;
        $isActive    = (bool)($fee['isActive'] ?? true);

        $isPerPet   = in_array($rawScope, ['PER_PET', 'PER_PET_PER_NIGHT']);
        $isPerGuest = in_array($rawScope, ['PER_GUEST', 'PER_GUEST_PER_NIGHT']);

        // Hostfully: quantity-selectable for per-pet and per-unit scopes when optional
        $isQtySelectable = $isOptional && ($isPerPet || $rawScope === 'PER_UNIT');
        $maxQty = match (true) {
            $isPerPet   => 5,
            $isPerGuest => 10,
            default     => 1,
        };

        return [
            'pms_id'               => $uid,
            // Numeric Hostfully feeId. REQUIRED for quote/booking fee overrides —
            // Hostfully matches overrides on feeId, NOT the UUID feeUid (verified
            // live: a feeUid-only override is silently ignored). pms_id keeps the
            // UUID for display/mapping; fee_id carries the numeric id for overrides.
            'fee_id'               => isset($fee['feeId']) ? (int)$fee['feeId'] : null,
            'name'                 => (string)($fee['name'] ?? 'Fee'),
            'is_mandatory'         => $isMandatory,
            'is_optional'          => $isOptional,
            'requires_approval'    => false, // Hostfully v3.3 does not expose this
            'is_active'            => $isActive,
            'scope'                => $scope,
            'amount'               => $amount,
            'percentage'           => $percentage,
            'tax_rate'             => (float)($fee['taxationRate'] ?? 0),
            'is_quantity_selectable' => $isQtySelectable,
            'min_qty'              => 0,
            'max_qty'              => $maxQty,
            'is_per_pet'           => $isPerPet,
            'is_per_guest'         => $isPerGuest,
            'pms_source'           => 'hostfully',
        ];
    }

    // ── Hostaway ──────────────────────────────────────────────────────────────
    // Source: GET /v1/listingFeeSettings/{listingId}
    //
    // Hostaway fee fields:
    //   id, listingFeeSettingId, alias (display name), name, type (internal),
    //   feeType (category), feeAppliedPer (scope), amountType (flat|percentage),
    //   amount, isMandatory, isQuantitySelectable, quantity (max units)
    //
    // feeAppliedPer values: reservation, night, person, person_per_night,
    //                       flat, percentage (varies by API version)
    public static function fromHostaway(array $fee): ?array
    {
        $id = (string)($fee['id'] ?? $fee['listingFeeSettingId'] ?? '');
        if (!$id) return null;

        $feeType       = (string)($fee['feeType'] ?? '');          // raw Hostaway feeType e.g. "petFee"
        $feeAppliedPer = strtolower($fee['feeAppliedPer'] ?? 'reservation');
        $amountType    = strtolower($fee['amountType'] ?? 'flat');
        $scope         = self::hostawayScope($feeAppliedPer);
        $isPercentage  = ($amountType === 'percentage');

        $amount     = $isPercentage ? null : (float)($fee['amount'] ?? 0);
        $percentage = $isPercentage ? (float)($fee['amount'] ?? 0) : null;

        $isMandatory     = (bool)($fee['isMandatory'] ?? false);
        $isQtySelectable = (bool)($fee['isQuantitySelectable'] ?? false);
        $maxQty          = $isQtySelectable ? max(1, (int)($fee['quantity'] ?? 1)) : 1;

        // Pet fee: use feeType=petFee strictly, NOT feeAppliedPer text-matching.
        // is_per_pet is derived from feeAppliedPer and could inadvertently match
        // other per-pet-priced fees that are not the PMS-designated pet fee.
        $isPerPet   = (strtolower($feeType) === 'petfee');
        $isPerGuest = str_contains($feeAppliedPer, 'person');

        return [
            'pms_id'               => $id,
            // internal_name: raw 'name' field from listingFeeSettings.
            // Required for the 'name' field in Hostaway priceDetails components[].
            // This is NOT the display name — it is the internal identifier Hostaway expects.
            'internal_name'        => (string)($fee['name'] ?? ''),
            // display label for guest-facing UI
            'name'                 => (string)($fee['alias'] ?? $fee['name'] ?? 'Fee'),
            // fee_type: raw Hostaway feeType value (e.g. petFee, cleaningFee).
            // Preserved for strict feeType-based decisions — never use is_per_pet
            // alone when feeType=petFee detection is required.
            'fee_type'             => $feeType,
            'is_mandatory'         => $isMandatory,
            'is_optional'          => !$isMandatory,
            'requires_approval'    => false,
            'is_active'            => !(bool)($fee['isDeleted'] ?? false),
            'scope'                => $scope,
            'amount'               => $amount,
            'percentage'           => $percentage,
            'tax_rate'             => 0,
            'is_quantity_selectable' => $isQtySelectable,
            'min_qty'              => 0,
            'max_qty'              => $maxQty,
            'is_per_pet'           => $isPerPet,
            'is_per_guest'         => $isPerGuest,
            'pms_source'           => 'hostaway',
        ];
    }

    // ── Lodgify ───────────────────────────────────────────────────────────────
    // Source: GET /v1/properties/{id}/rates/addons
    //
    // Response shape not yet verified against live account.
    // Logging added in LodgifyAdapter::getPropertyAddons to capture shape.
    // Assumed fields based on Lodgify API docs:
    //   id, name, description, price (per unit), type (optional|mandatory),
    //   quantity_type (per_stay|per_night|per_guest|per_unit)
    //
    // STATUS: Treat as best-effort; verify against logged response.
    public static function fromLodgify(array $addon): ?array
    {
        $id = (string)($addon['id'] ?? '');
        if (!$id) return null;

        $type    = strtolower($addon['type'] ?? 'optional');
        $isMand  = ($type === 'mandatory' || $type === 'required');
        $qtyType = strtolower($addon['quantity_type'] ?? $addon['priceType'] ?? 'per_stay');
        $scope   = self::lodgifyScope($qtyType);

        $amount      = (float)($addon['price'] ?? $addon['amount'] ?? $addon['value'] ?? 0);
        $isQtySel    = !in_array($scope, ['per_stay', 'percentage_of_total', 'percentage_of_rent']);
        $isPerPet    = str_contains($qtyType, 'pet');
        $isPerGuest  = str_contains($qtyType, 'guest') || str_contains($qtyType, 'person');

        return [
            'pms_id'               => $id,
            'name'                 => (string)($addon['name'] ?? $addon['title'] ?? 'Add-on'),
            'is_mandatory'         => $isMand,
            'is_optional'          => !$isMand,
            'requires_approval'    => false,
            'is_active'            => !(bool)($addon['is_deleted'] ?? false),
            'scope'                => $scope,
            'amount'               => $amount,
            'percentage'           => null,
            'tax_rate'             => 0,
            'is_quantity_selectable' => $isQtySel,
            'min_qty'              => 0,
            'max_qty'              => $isQtySel ? 10 : 1,
            'is_per_pet'           => $isPerPet,
            'is_per_guest'         => $isPerGuest,
            'pms_source'           => 'lodgify',
        ];
    }

    // ── Scope normalizers ─────────────────────────────────────────────────────

    private static function hostfullyScope(string $s): string
    {
        return match ($s) {
            'PER_NIGHT'              => 'per_night',
            'PER_GUEST'              => 'per_guest',
            'PER_GUEST_PER_NIGHT'    => 'per_guest_per_night',
            'PER_PET'                => 'per_pet',
            'PER_PET_PER_NIGHT'      => 'per_pet_per_night',
            'PERCENTAGE_OF_TOTAL'    => 'percentage_of_total',
            'PERCENTAGE_OF_RENT'     => 'percentage_of_rent',
            'PER_UNIT'               => 'per_unit',
            default                  => 'per_stay',
        };
    }

    private static function hostawayScope(string $s): string
    {
        return match (true) {
            str_contains($s, 'person') && str_contains($s, 'night') => 'per_guest_per_night',
            str_contains($s, 'person')  => 'per_guest',
            str_contains($s, 'night')   => 'per_night',
            str_contains($s, 'pet')     => 'per_pet',
            str_contains($s, 'percent') => 'percentage_of_total',
            default                     => 'per_stay',
        };
    }

    private static function lodgifyScope(string $s): string
    {
        return match (true) {
            str_contains($s, 'night') && str_contains($s, 'guest')  => 'per_guest_per_night',
            str_contains($s, 'night') && str_contains($s, 'pet')    => 'per_pet_per_night',
            str_contains($s, 'night')   => 'per_night',
            str_contains($s, 'guest')   => 'per_guest',
            str_contains($s, 'person')  => 'per_guest',
            str_contains($s, 'pet')     => 'per_pet',
            str_contains($s, 'unit')    => 'per_unit',
            str_contains($s, 'percent') => 'percentage_of_total',
            default                     => 'per_stay',
        };
    }

    /**
     * Build the optional_items array that QuoteNormalizer returns,
     * given a canonical fee and the current quote context (nights, guest count).
     *
     * This is a READ-ONLY operation — amounts are display-only hints.
     * Never use these amounts for charging. Always re-quote through the PMS.
     */
    public static function toOptionalItem(array $cf, int $nights = 1, int $guests = 1, int $pets = 0): array
    {
        $maxQty = match (true) {
            $cf['is_per_pet']   => max($pets, $cf['max_qty']),
            $cf['is_per_guest'] => max($guests, $cf['max_qty']),
            default             => $cf['max_qty'],
        };

        return [
            'id'                   => $cf['pms_source'] . '_' . $cf['pms_id'],
            'pms_id'               => $cf['pms_id'],
            'fee_uid'              => $cf['pms_id'],
            'label'                => $cf['name'],
            'amount'               => $cf['amount'] ?? 0,
            'percentage'           => $cf['percentage'],
            'tax_rate'             => $cf['tax_rate'],
            'enabled'              => false,
            'scope'                => $cf['scope'],
            'per_unit'             => self::perUnitLabel($cf['scope']),
            'max_qty'              => max(1, $maxQty),
            'min_qty'              => $cf['min_qty'],
            'is_per_pet'           => $cf['is_per_pet'],
            'is_per_guest'         => $cf['is_per_guest'],
            'is_quantity_selectable' => $cf['is_quantity_selectable'],
            'requires_approval'    => $cf['requires_approval'],
            'pms_source'           => $cf['pms_source'],
        ];
    }

    private static function perUnitLabel(string $scope): string
    {
        return match ($scope) {
            'per_night'              => '/night',
            'per_pet'                => '/pet',
            'per_pet_per_night'      => '/pet/night',
            'per_guest'              => '/guest',
            'per_guest_per_night'    => '/guest/night',
            'percentage_of_total'    => '% of total',
            'percentage_of_rent'     => '% of rent',
            'per_unit'               => '/unit',
            default                  => '/stay',
        };
    }
}
