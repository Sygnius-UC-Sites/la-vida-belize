<?php

namespace App\Helper;

use App\Models\Pms\PmsRoom;
use Illuminate\Support\Facades\Log;

/**
 * FeeValidator — single canonical validator for optional_services.
 *
 * FAIL-CLOSED: when the guest submits fee IDs and canonical fee metadata exists,
 * every ID is validated strictly. Unknown IDs are always rejected.
 * When canonical metadata is NULL (sync has never run), submissions pass through
 * with a warning. When canonical is an empty array (sync ran, property has no fees),
 * all submitted IDs are rejected — the property genuinely has no optional fees.
 *
 * FORMAT: optional_services is always {"provider_fee_id": quantity}.
 * Never convert to a list of display objects. Display data is reconstructed
 * from canonical when needed; it is not stored in the booking.
 *
 * Called BEFORE any PMS API request, not after. Used by:
 *   QuoteController       — before getRawQuote
 *   PageController::dynamicDataCategory — before getRawQuote
 *   PageController::saveBookingData     — before getRawQuote
 *   StripeController      — before refreshQuoteIfExpired
 *   PmsBookingService     — before refetchQuote / createInPms
 *
 * Rules:
 *   1. Unknown ID (not in canonical) → rejected
 *   2. Inactive fee (is_active=false) → rejected
 *   3. Mandatory fee (is_mandatory=true) → rejected (auto-included by PMS)
 *   4. Approval-required fee → separated into $requested, never sent to PMS
 *   5. Quantity clamped to [0, max_qty]
 *   6. No canonical metadata (NULL) → warn and pass through (sync pending)
 *   7. Empty canonical ([]) → reject all (property has no optional fees)
 *
 * Returns:
 *   [
 *     'confirmed'  => ['provider_fee_id' => qty, ...]   — send to PMS
 *     'requested'  => ['provider_fee_id' => qty, ...]   — approval only, never to PMS
 *     'rejected'   => ['provider_fee_id', ...]          — stripped
 *     'pass_through' => bool                            — true when no canonical (warn)
 *   ]
 */
class FeeValidator
{
    public static function validate(array $submitted, PmsRoom $room, bool $logRejections = true): array
    {
        if (empty($submitted)) {
            return ['confirmed' => [], 'requested' => [], 'rejected' => [], 'pass_through' => false];
        }

        // NULL = sync has never run; no canonical metadata to validate against.
        // FAIL-CLOSED: never pass browser-submitted fee IDs to the PMS without
        // canonical validation. An unvalidated ID could be tampered or stale.
        // Operators must run pms:sync-all before optional fees are accepted.
        if ($room->optional_fees_json === null) {
            Log::channel('lodgify')->warning('FeeValidator: optional_fees_json is NULL — sync has not run. All submitted IDs rejected (fail-closed).', [
                'room_id'    => $room->id,
                'pms'        => $room->pms_source,
                'submitted'  => array_keys($submitted),
            ]);
            return ['confirmed' => [], 'requested' => [], 'rejected' => array_keys($submitted), 'pass_through' => false];
        }

        $canonicalFees = json_decode($room->optional_fees_json, true) ?: [];

        // Empty array = sync ran, property has zero optional fees.
        // Reject everything — do not pass browser IDs through unvalidated.
        if (empty($canonicalFees)) {
            Log::channel('lodgify')->info('FeeValidator: canonical empty — no optional fees for this property. All submitted IDs rejected.', [
                'room_id'   => $room->id,
                'pms'       => $room->pms_source,
                'submitted' => array_keys($submitted),
            ]);
            return ['confirmed' => [], 'requested' => [], 'rejected' => array_keys($submitted), 'pass_through' => false];
        }

        $map       = self::buildMap($canonicalFees, $room->pms_source ?? '');
        $confirmed = [];
        $requested = [];
        $rejected  = [];

        foreach ($submitted as $optId => $qty) {
            $optId = (string)$optId;
            $qty   = max(0, (int)$qty);
            $cf    = $map[$optId] ?? null;

            if (!$cf) {
                $rejected[] = $optId;
                continue;
            }
            if (!($cf['is_active'] ?? true)) {
                $rejected[] = $optId;
                continue;
            }
            if ($cf['is_mandatory'] ?? false) {
                $rejected[] = $optId;
                continue;
            }

            $maxQty = (int)($cf['max_qty'] ?? 1);
            $qty    = min($qty, $maxQty);
            if ($qty <= 0) continue;

            if ($cf['requires_approval'] ?? false) {
                $requested[$optId] = $qty;
            } else {
                $confirmed[$optId] = $qty;
            }
        }

        if ($logRejections && !empty($rejected)) {
            Log::channel('lodgify')->info('FeeValidator: rejected IDs', [
                'room_id'   => $room->id,
                'pms'       => $room->pms_source,
                'submitted' => array_keys($submitted),
                'rejected'  => $rejected,
                'confirmed' => array_keys($confirmed),
            ]);
        }

        return compact('confirmed', 'requested', 'rejected') + ['pass_through' => false];
    }

    /** Returns only the confirmed map (for passing to adapters). */
    public static function confirmed(array $submitted, PmsRoom $room): array
    {
        return self::validate($submitted, $room)['confirmed'];
    }

    /**
     * Build lookup map keyed by all ID formats in the codebase.
     * Both "hostaway_123" and "ha_123" map to the same canonical entry.
     */
    public static function buildMap(array $canonicalFees, string $pmsSource = ''): array
    {
        $map = [];
        foreach ($canonicalFees as $cf) {
            if (!is_array($cf) || empty($cf['pms_id'])) continue;
            $src   = $cf['pms_source'] ?? $pmsSource;
            $pmsId = (string)$cf['pms_id'];

            $map["{$src}_{$pmsId}"] = $cf;

            $short = match ($src) {
                'hostfully' => "hf_{$pmsId}",
                'hostaway'  => "ha_{$pmsId}",
                'lodgify'   => "lodgify_{$pmsId}",
                'guesty'    => "guesty_{$pmsId}",
                default     => "{$src}_{$pmsId}",
            };
            $map[$short] = $cf;
        }
        return $map;
    }

    /**
     * Build the components[] array for Hostaway priceDetails.
     *
     * Hostaway priceDetails body:
     *   components[]: [{ listingFeeSettingId: int, name: string, quantity: int }]
     *
     * 'name' is the INTERNAL name from listingFeeSettings (not the display alias).
     * The internal name is stored as 'internal_name' in the canonical fee definition.
     *
     * Pet fees are identified strictly by feeType=petFee (stored as 'fee_type' in
     * canonical). is_per_pet alone is insufficient — it could match any per-pet-priced
     * fee, not specifically the PMS petFee category.
     */
    public static function buildHostawayComponents(
        array $confirmedServices,
        array $canonicalFees,
        int $pets = 0
    ): array {
        $map        = self::buildMap($canonicalFees, 'hostaway');
        $components = [];
        $included   = [];

        // 1. Auto-include pet fees when pets > 0.
        // Strictly identified by fee_type === 'petFee' (raw feeType from Hostaway API).
        // Do NOT use is_per_pet, which is derived from feeAppliedPer and could match
        // non-petFee items with a per-pet pricing structure.
        if ($pets > 0) {
            foreach ($canonicalFees as $cf) {
                if (!is_array($cf) || ($cf['pms_source'] ?? '') !== 'hostaway') continue;
                if (strtolower($cf['fee_type'] ?? '') !== 'petfee') continue; // strict feeType check
                if ($cf['is_mandatory'] ?? false) continue; // mandatory = Hostaway includes automatically
                $feeId = (string)($cf['pms_id'] ?? '');
                if (!$feeId || isset($included[$feeId])) continue;

                $components[] = [
                    'listingFeeSettingId' => (int)$feeId,
                    'name'                => $cf['internal_name'] ?? $cf['name'] ?? 'Pet fee',
                    'quantity'            => $pets,
                ];
                $included[$feeId] = true;
            }
        }

        // 2. Explicitly selected optional fees.
        foreach ($confirmedServices as $optId => $qty) {
            $cf    = $map[(string)$optId] ?? null;
            if (!$cf) continue;
            if ($cf['is_mandatory'] ?? false) continue;
            // Skip petFee — already handled above
            if (strtolower($cf['fee_type'] ?? '') === 'petfee') continue;
            $feeId = (string)($cf['pms_id'] ?? '');
            if (!$feeId || isset($included[$feeId])) continue;

            $components[] = [
                'listingFeeSettingId' => (int)$feeId,
                'name'                => $cf['internal_name'] ?? $cf['name'] ?? 'Optional fee',
                'quantity'            => max(1, (int)$qty),
            ];
            $included[$feeId] = true;
        }

        return $components;
    }

    /**
     * Build the Hostfully overrides payload for optional fees.
     *
     * Hostfully v3.3 quote override format: [{feeUid, enabled: true, quantity}]
     *
     * NOTE: 'quantity' is included because per-pet/per-unit scopes need it.
     * Whether Hostfully uses 'quantity' or 'units' in the override is NOT yet
     * verified against a live account with optional fees configured. The full
     * override request must be logged and compared to the quote response before
     * relying on this in production.
     */
    public static function buildHostfullyOverrides(
        array $confirmedServices,
        array $canonicalFees
    ): array {
        $map       = self::buildMap($canonicalFees, 'hostfully');
        $overrides = [];

        foreach ($confirmedServices as $optId => $qty) {
            $cf = $map[(string)$optId] ?? null;
            if (!$cf) continue;

            // Hostfully matches fee overrides on the NUMERIC feeId — NOT the UUID
            // feeUid. Verified live: a feeUid-only override is silently ignored
            // (total unchanged), while a feeId override applies the fee correctly.
            // Prefer canonical fee_id; fall back to pms_id when it is numeric
            // (older synced data stored the numeric id in pms_id).
            $feeId  = $cf['fee_id'] ?? null;
            $feeUid = (string)($cf['pms_id'] ?? '');
            if ($feeId === null && is_numeric($feeUid)) {
                $feeId = (int)$feeUid;
            }
            if ($feeId === null && $feeUid === '') continue;

            $override = ['enabled' => true, 'quantity' => max(1, (int)$qty)];
            if ($feeId !== null)  $override['feeId']  = (int)$feeId;   // authoritative match key
            if ($feeUid !== '' && !is_numeric($feeUid)) $override['feeUid'] = $feeUid; // keep UUID for reference
            $overrides[] = $override;
        }

        return $overrides;
    }
}
