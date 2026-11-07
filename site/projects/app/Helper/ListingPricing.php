<?php

namespace App\Helper;

/**
 * Normalizes pricing and fee data from any PMS into one canonical shape
 * that the front-end quote calculator can consume without per-PMS logic.
 *
 * Canonical shape (all monetary values in the listing's currency):
 *   currency         string       ISO code, e.g. "USD"
 *   nightly_rate     float|null   base nightly rate
 *   cleaning_fee     float|null
 *   tax_rate         float|null   percentage 0–100  (12.75 means 12.75 %)
 *   security_deposit float|null   refundable damage hold
 *   extra_guest_fee  float|null   surcharge per guest above base occupancy
 *   custom_fees      array        [{name, amount, type:'fixed'|'pct', scope:'stay'|'night', optional:bool}]
 */
class ListingPricing
{
    /**
     * @param  array  $pricing    PMS-specific pricing data passed from the adapter
     * @param  array  $fees       PMS-specific fees data passed from the adapter
     * @param  string $pmsSource  'hostfully' | 'hostaway' | 'guesty' | 'lodgify' | …
     */
    public static function normalize(array $pricing, array $fees, string $pmsSource): array
    {
        return match ($pmsSource) {
            'hostfully' => self::fromHostfully($pricing, $fees),
            'hostaway'  => self::fromHostaway($pricing, $fees),
            'guesty'    => self::fromGuesty($pricing),
            default     => self::fromGeneric($pricing),
        };
    }

    // ── Hostfully ─────────────────────────────────────────────────────────────
    // $pricing = property.pricing sub-object:
    //   {currency, dailyRate, taxRate (0–1 decimal), securityDeposit,
    //    cleaningFee, cleaningFeeTaxRate, extraGuestFee, …}
    // $fees = top-level fees array:
    //   [{name, amount, amountType:'TAX'|'AMOUNT', scope:'PER_STAY'|'PER_NIGHT', optional, taxationRate}]
    private static function fromHostfully(array $p, array $fees): array
    {
        // taxRate is stored as a decimal fraction (e.g. 0.1275) → convert to percent
        $taxRate = isset($p['taxRate']) ? round((float) $p['taxRate'] * 100, 4) : null;

        $custom = [];
        foreach ($fees as $fee) {
            $name = trim((string) ($fee['name'] ?? ''));
            if ($name === '') continue;
            // amountType 'TAX' means the amount is a percentage; 'AMOUNT' is a flat dollar figure
            $type  = strtoupper($fee['amountType'] ?? '') === 'TAX' ? 'pct' : 'fixed';
            $scope = strtoupper($fee['scope'] ?? '') === 'PER_NIGHT' ? 'night' : 'stay';
            $custom[] = [
                'name'     => $name,
                'amount'   => (float) ($fee['amount'] ?? 0),
                'type'     => $type,
                'scope'    => $scope,
                'optional' => (bool) ($fee['optional'] ?? false),
            ];
        }

        return [
            'currency'         => $p['currency'] ?? 'USD',
            'nightly_rate'     => isset($p['dailyRate'])       ? (float) $p['dailyRate']       : null,
            'cleaning_fee'     => isset($p['cleaningFee'])     ? (float) $p['cleaningFee']     : null,
            'tax_rate'         => $taxRate,
            'security_deposit' => isset($p['securityDeposit']) ? (float) $p['securityDeposit'] : null,
            'extra_guest_fee'  => isset($p['extraGuestFee'])   ? (float) $p['extraGuestFee']   : null,
            'custom_fees'      => $custom,
        ];
    }

    // ── Hostaway ──────────────────────────────────────────────────────────────
    // $pricing = {currency, basePrice}
    // $fees    = {cleaning, checkin, deposit}  (already extracted from flat listing)
    private static function fromHostaway(array $p, array $fees): array
    {
        $custom = [];
        if (!empty($fees['checkin'])) {
            $custom[] = [
                'name'     => 'Check-in fee',
                'amount'   => (float) $fees['checkin'],
                'type'     => 'fixed',
                'scope'    => 'stay',
                'optional' => false,
            ];
        }

        return [
            'currency'         => $p['currency'] ?? 'USD',
            'nightly_rate'     => isset($p['basePrice'])   ? (float) $p['basePrice']   : null,
            'cleaning_fee'     => isset($fees['cleaning']) ? (float) $fees['cleaning'] : null,
            'tax_rate'         => null,
            'security_deposit' => isset($fees['deposit'])  ? (float) $fees['deposit']  : null,
            'extra_guest_fee'  => null,
            'custom_fees'      => $custom,
        ];
    }

    // ── Guesty ────────────────────────────────────────────────────────────────
    // $pricing = listing.prices object — field names vary by Guesty plan
    private static function fromGuesty(array $p): array
    {
        return [
            'currency'         => $p['currency'] ?? 'USD',
            'nightly_rate'     => isset($p['basePrice'])       ? (float) $p['basePrice']
                                : (isset($p['price'])          ? (float) $p['price'] : null),
            'cleaning_fee'     => isset($p['cleaningFee'])     ? (float) $p['cleaningFee']     : null,
            'tax_rate'         => null,
            'security_deposit' => isset($p['securityDeposit']) ? (float) $p['securityDeposit'] : null,
            'extra_guest_fee'  => null,
            'custom_fees'      => [],
        ];
    }

    // ── Generic fallback (Lodgify + unknown PMS) ──────────────────────────────
    private static function fromGeneric(array $p): array
    {
        return [
            'currency'         => $p['currency'] ?? 'USD',
            'nightly_rate'     => isset($p['basePrice']) ? (float) $p['basePrice'] : null,
            'cleaning_fee'     => null,
            'tax_rate'         => null,
            'security_deposit' => null,
            'extra_guest_fee'  => null,
            'custom_fees'      => [],
        ];
    }
}
