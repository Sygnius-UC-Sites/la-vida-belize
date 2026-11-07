<?php

namespace App\Services;

use App\Helper\StripeHelper;
use App\Helper\QuoteNormalizer;

/**
 * Pure-PHP post-reservation verification.
 *
 * No Eloquent, no HTTP, no Stripe — every method is unit-testable without a
 * database or network.  PmsBookingService calls verify() after the adapter
 * returns a reservation response; recovery is handled by the caller.
 *
 * Contract
 * --------
 * Returns ['ok' => bool, 'discrepancy' => array].
 * discrepancy is empty on success; on failure it contains:
 *   - reason           : human-readable string
 *   - expected_total   : float (what guest accepted)
 *   - verified_total   : float|null (what PMS returned; null = missing)
 *   - missing_fees     : string[] (fee names not found in PMS response)
 *   - unresolved_fees  : string[] (selected fee IDs not in canonical map)
 *
 * Fee verification principles
 * ---------------------------
 *   1. Snapshot: caller should pass fees extracted from the accepted quote
 *      (ReservationVerifier::extractQuoteFees) rather than the current
 *      optional_fees_json, which may have changed since booking.
 *   2. Fail-closed on unresolved IDs: if a selected fee ID cannot be found
 *      in the canonical map, verification fails rather than passing silently.
 *   3. Consume on match: each matched PMS line item is removed from the pool
 *      so two expected fees at the same amount cannot both match a single
 *      PMS line.
 */
class ReservationVerifier
{
    /**
     * Verify a PMS reservation response against the accepted booking values.
     *
     * @param string $pms              pms_source (guesty | hostfully | …)
     * @param array  $response         raw adapter createReservation() response
     * @param float  $expectedTotal    booking->total_amount (guest-accepted)
     * @param string $currency         booking->currency
     * @param array  $selectedServices booking->optional_services decoded
     * @param array  $canonicalFees    fee definitions from the accepted quote
     *                                 (see extractQuoteFees)
     */
    public static function verify(
        string $pms,
        array  $response,
        float  $expectedTotal,
        string $currency,
        array  $selectedServices = [],
        array  $canonicalFees    = []
    ): array {
        return match ($pms) {
            'guesty'    => self::verifyGuesty($response, $expectedTotal, $currency, $selectedServices, $canonicalFees),
            'hostfully' => self::verifyHostfully($response, $expectedTotal, $currency, $selectedServices, $canonicalFees),
            default     => ['ok' => true, 'discrepancy' => []],
        };
    }

    // ── Quote fee snapshot extraction ─────────────────────────────────────────

    /**
     * Extract optional fee definitions from a raw PMS quote response.
     *
     * Returns an array in canonical-fee format (pms_id, name, amount) built from
     * the QuoteNormalizer's optional_items.  Call this in createInPms() to get
     * the snapshot of fee amounts the guest actually accepted, rather than reading
     * the current optional_fees_json (which may have changed since booking).
     *
     * Returns [] when the quote cannot be parsed or has no optional items —
     * the caller should fall back to optional_fees_json in that case.
     */
    public static function extractQuoteFees(string $pms, array $rawQuote): array
    {
        if (empty($rawQuote)) {
            return [];
        }

        $normalized = QuoteNormalizer::normalize($rawQuote, $pms, []);
        if (!($normalized['ok'] ?? false)) {
            return [];
        }

        $result = [];
        foreach ($normalized['optional_items'] ?? [] as $fee) {
            $pmsId = $fee['pms_id'] ?? null;
            if (empty($pmsId)) {
                continue;
            }
            $amount = $fee['amount'] ?? null;
            if ($amount === null) {
                continue; // percentage-based fees have no fixed amount — skip
            }
            $result[] = [
                'pms_id'            => (string)$pmsId,
                'provider_fee_id'    => (string)($fee['provider_fee_id'] ?? $pmsId),
                'name'              => $fee['label'] ?? $fee['name'] ?? (string)$pmsId,
                'amount'            => (float)$amount,
                'type'              => $fee['type'] ?? null,
                'normal_type'       => $fee['normal_type'] ?? $fee['normalType'] ?? null,
                'second_identifier' => $fee['second_identifier'] ?? $fee['secondIdentifier'] ?? null,
            ];
        }
        return $result;
    }

    // ── Guesty ────────────────────────────────────────────────────────────────

    private static function verifyGuesty(
        array  $response,
        float  $expectedTotal,
        string $currency,
        array  $selectedServices,
        array  $canonicalFees
    ): array {
        $verification = $response['_verification'] ?? null;

        // _verification must be present — absence means the re-fetch failed entirely
        if (empty($verification)) {
            return self::fail(
                'Guesty re-fetch _verification block missing from response',
                $expectedTotal, null
            );
        }

        // fare_total is required; null means the reservation was not priced
        $verifiedTotal = $verification['fare_total'] ?? null;
        if ($verifiedTotal === null) {
            return self::fail(
                'Guesty fare_total missing in re-fetch response',
                $expectedTotal, null
            );
        }

        $expectedMinor = StripeHelper::toStripeAmount($expectedTotal, $currency);
        $verifiedMinor = StripeHelper::toStripeAmount((float)$verifiedTotal, $currency);
        if ($verifiedMinor !== $expectedMinor) {
            return self::fail(
                'Guesty fare_total does not match accepted total',
                $expectedTotal, (float)$verifiedTotal,
                [], $expectedMinor, $verifiedMinor
            );
        }

        // Fee line item check when optional services were selected
        if (!empty($selectedServices)) {
            $feeCheck = self::checkGuestyFees(
                $verification['invoice_items'] ?? [],
                $selectedServices,
                $canonicalFees,
                $currency
            );
            if (!$feeCheck['ok']) {
                return $feeCheck;
            }
        }

        return ['ok' => true, 'discrepancy' => []];
    }

    private static function checkGuestyFees(
        array  $invoiceItems,
        array  $selectedServices,
        array  $canonicalFees,
        string $currency
    ): array {
        if (empty($canonicalFees)) {
            // No canonical data — cannot resolve selected services.  Fail closed.
            return ['ok' => false, 'discrepancy' => [
                'reason'          => 'Cannot verify optional fees: no canonical fee definitions available',
                'missing_fees'    => [],
                'unresolved_fees' => array_keys($selectedServices),
            ]];
        }

        $cfMap    = self::buildCanonicalMap($canonicalFees);
        $resolved = self::resolveExpectedFees($selectedServices, $cfMap);

        if (!$resolved['ok']) {
            return ['ok' => false, 'discrepancy' => [
                'reason'          => 'Selected optional fees could not be resolved from the accepted quote snapshot',
                'unresolved_fees' => $resolved['unresolved'],
            ]];
        }

        $expectedFees = $resolved['fees'];
        if (empty($expectedFees)) {
            return ['ok' => true, 'discrepancy' => []];
        }

        // Build a pool containing the Guesty identity fields that may connect an
        // additional-fee definition to the final reservation invoice.
        $invoicePool = [];
        foreach ($invoiceItems as $item) {
            $normalType       = strtoupper(trim((string)($item['normalType'] ?? '')));
            $type             = strtoupper(trim((string)($item['type'] ?? '')));
            $secondIdentifier = strtoupper(trim((string)($item['secondIdentifier'] ?? '')));
            $providerIds      = array_values(array_unique(array_filter(array_map(
                fn($value) => trim((string)$value),
                [
                    $item['additionalFeeId'] ?? null,
                    $item['feeId'] ?? null,
                    $item['providerFeeId'] ?? null,
                    $item['refId'] ?? null,
                    $item['id'] ?? null,
                    $item['_id'] ?? null,
                ]
            ))));

            $isFeeItem = in_array($normalType, ['CF', 'AFE', 'ADDON', 'FEE'], true)
                || in_array($type, ['CLEANING_FEE', 'ADDITIONAL', 'ADDON', 'FEE'], true)
                || $secondIdentifier !== ''
                || !empty($providerIds);

            if (!$isFeeItem) {
                continue;
            }

            $invoicePool[] = [
                'normal_type'       => $normalType,
                'second_identifier' => $secondIdentifier,
                'name'              => self::normalizeIdentity(
                    $item['title'] ?? $item['name'] ?? $item['description'] ?? ''
                ),
                'provider_ids'      => $providerIds,
                'minor'             => StripeHelper::toStripeAmount((float)($item['amount'] ?? 0), $currency),
            ];
        }

        $missing = [];
        foreach ($expectedFees as $fee) {
            $minor            = StripeHelper::toStripeAmount($fee['amount'], $currency);
            $providerFeeId    = trim((string)($fee['provider_fee_id'] ?? ''));
            $feeName          = self::normalizeIdentity($fee['name'] ?? '');
            $normalType       = strtoupper(trim((string)($fee['normal_type'] ?? '')));
            $secondIdentifier = strtoupper(trim((string)($fee['second_identifier'] ?? '')));
            $found            = false;

            foreach ($invoicePool as $k => $item) {
                if ($item['minor'] !== $minor) {
                    continue;
                }

                $idMatches = $providerFeeId !== ''
                    && in_array($providerFeeId, $item['provider_ids'], true);
                $nameMatches = $feeName !== ''
                    && $item['name'] !== ''
                    && $feeName === $item['name'];
                $normalTypeMatches = $normalType !== ''
                    && $normalType === $item['normal_type']
                    && ($secondIdentifier === '' || $secondIdentifier === $item['second_identifier']);
                $secondIdentifierMatches = $secondIdentifier !== ''
                    && $secondIdentifier === $item['second_identifier'];

                // Amount is necessary but never sufficient. At least one stable
                // identity field must also match the selected canonical fee.
                if (!$idMatches && !$nameMatches && !$normalTypeMatches && !$secondIdentifierMatches) {
                    continue;
                }

                unset($invoicePool[$k]); // consume — prevents double-matching
                $found = true;
                break;
            }

            if (!$found) {
                $missing[] = $fee['name'] . ' ($' . number_format($fee['amount'], 2) . ')';
            }
        }

        if (!empty($missing)) {
            return ['ok' => false, 'discrepancy' => [
                'reason'       => 'Selected optional fees not found in Guesty invoice_items',
                'missing_fees' => $missing,
            ]];
        }

        return ['ok' => true, 'discrepancy' => []];
    }

    // ── Hostfully ─────────────────────────────────────────────────────────────

    private static function verifyHostfully(
        array  $response,
        float  $expectedTotal,
        string $currency,
        array  $selectedServices,
        array  $canonicalFees
    ): array {
        $leadTotal = $response['data']['lead']['totalPrice']
                  ?? $response['data']['totalPrice']
                  ?? null;

        // Hostfully may omit totalPrice from a successful create/mark-as-booked
        // lead response. The booking service already re-fetched a live quote and
        // blocked material price changes before createReservation(), so do not
        // cancel an otherwise successful no-addons booking solely because this
        // response shape lacks totalPrice.
        if ($leadTotal === null) {
            $leadUid = $response['data']['lead']['uid']
                ?? $response['data']['uid']
                ?? null;
            $leadStatus = strtoupper((string) ($response['data']['lead']['status'] ?? $response['data']['status'] ?? ''));
            if ($leadUid && in_array($leadStatus, ['BOOKED', 'CONFIRMED', 'NEW'], true) && empty($selectedServices)) {
                return ['ok' => true, 'discrepancy' => []];
            }

            return self::fail(
                'Hostfully lead.totalPrice missing in reservation response',
                $expectedTotal, null
            );
        }

        $expectedMinor = StripeHelper::toStripeAmount($expectedTotal, $currency);
        $leadMinor     = StripeHelper::toStripeAmount((float)$leadTotal, $currency);
        if ($leadMinor !== $expectedMinor) {
            return self::fail(
                'Hostfully lead.totalPrice does not match accepted total',
                $expectedTotal, (float)$leadTotal,
                [], $expectedMinor, $leadMinor
            );
        }

        // Fee line item check when optional services were selected
        if (!empty($selectedServices)) {
            $leadFees = $response['data']['lead']['fees'] ?? [];
            $feeCheck = self::checkHostfullyFees(
                $leadFees,
                $selectedServices,
                $canonicalFees,
                $currency
            );
            if (!$feeCheck['ok']) {
                return $feeCheck;
            }
        }

        return ['ok' => true, 'discrepancy' => []];
    }

    private static function checkHostfullyFees(
        array  $leadFees,
        array  $selectedServices,
        array  $canonicalFees,
        string $currency
    ): array {
        if (empty($canonicalFees)) {
            return ['ok' => false, 'discrepancy' => [
                'reason'          => 'Cannot verify optional fees: no canonical fee definitions available',
                'missing_fees'    => [],
                'unresolved_fees' => array_keys($selectedServices),
            ]];
        }

        $cfMap    = self::buildCanonicalMap($canonicalFees);
        $resolved = self::resolveExpectedFees($selectedServices, $cfMap);

        if (!$resolved['ok']) {
            return ['ok' => false, 'discrepancy' => [
                'reason'          => 'Selected optional fees could not be resolved from the accepted quote snapshot',
                'unresolved_fees' => $resolved['unresolved'],
            ]];
        }

        $expectedFees = $resolved['fees'];
        if (empty($expectedFees)) {
            return ['ok' => true, 'discrepancy' => []];
        }

        // Build a pool of lead fees keyed by [lowercase-name, minor-amount].
        // Matching uses BOTH identity (lowercase name) AND amount so two fees at
        // the same price with different names cannot cross-match.
        $leadPool = [];
        foreach ($leadFees as $lf) {
            $leadPool[] = [
                'name'  => strtolower(trim($lf['name'] ?? '')),
                'minor' => StripeHelper::toStripeAmount((float)($lf['amount'] ?? 0), $currency),
            ];
        }

        $missing = [];
        foreach ($expectedFees as $fee) {
            $minor    = StripeHelper::toStripeAmount($fee['amount'], $currency);
            $feeName  = strtolower(trim($fee['name'] ?? ''));
            $found    = false;

            foreach ($leadPool as $k => $item) {
                if ($item['minor'] !== $minor) continue;
                // When both sides have a non-empty name, require an exact match —
                // prevents a $75 "Pet Fee" from satisfying a $75 "Pool Heating" fee.
                if ($feeName && $item['name'] && $item['name'] !== $feeName) continue;
                unset($leadPool[$k]); // consume
                $found = true;
                break;
            }

            if (!$found) {
                $missing[] = $fee['name'] . ' ($' . number_format($fee['amount'], 2) . ')';
            }
        }

        if (!empty($missing)) {
            return ['ok' => false, 'discrepancy' => [
                'reason'       => 'Selected optional fees not found in Hostfully lead.fees',
                'missing_fees' => $missing,
            ]];
        }

        return ['ok' => true, 'discrepancy' => []];
    }

    // ── Shared helpers ────────────────────────────────────────────────────────

    /**
     * Build a map of fee_id => canonical_fee from optional_fees_json entries.
     * UI values may use a PMS prefix, so index both raw and exact prefixed forms.
     */
    private static function buildCanonicalMap(array $canonicalFees): array
    {
        $map = [];
        foreach ($canonicalFees as $cf) {
            $id = (string)($cf['pms_id'] ?? $cf['provider_fee_id'] ?? '');
            if ($id !== '') {
                foreach (self::canonicalKeys($id, (string)($cf['pms_source'] ?? '')) as $key) {
                    $map[$key] = $cf;
                }
            }
        }
        return $map;
    }

    /**
     * Resolve selected service IDs to expected fee amounts from the canonical map.
     *
     * Returns:
     *   ['ok' => true,  'fees' => [...], 'unresolved' => []]
     *   ['ok' => false, 'fees' => [...], 'unresolved' => ['id1', ...]]
     *
     * Fails closed: if any selected fee ID is not found in $cfMap or has no
     * resolvable amount, it is recorded in 'unresolved' and ok=false is returned.
     * The caller is responsible for deciding how to surface the failure.
     */
    private static function resolveExpectedFees(array $selectedServices, array $cfMap): array
    {
        $fees       = [];
        $unresolved = [];

        foreach ($selectedServices as $rawId => $qty) {
            $rawId = (string)$rawId;
            $cf    = $cfMap[$rawId] ?? null;

            if (!$cf) {
                foreach (self::stripKnownPrefixes($rawId) as $candidate) {
                    if (isset($cfMap[$candidate])) {
                        $cf = $cfMap[$candidate];
                        break;
                    }
                }
            }

            if (!$cf || !isset($cf['amount']) || $cf['amount'] === null || $cf['amount'] === '') {
                $unresolved[] = $rawId;
                continue;
            }

            $legacyType = strtoupper(trim((string)($cf['type'] ?? '')));
            $normalType = strtoupper(trim((string)($cf['normal_type'] ?? '')));
            if ($normalType === '' && in_array($legacyType, ['CF', 'AFE', 'ADDON', 'FEE'], true)) {
                $normalType = $legacyType;
            }

            $fees[] = [
                'name'              => $cf['name'] ?? $cf['label'] ?? $rawId,
                'amount'            => round((float)$cf['amount'] * max(1, (int)$qty), 2),
                'provider_fee_id'   => (string)($cf['provider_fee_id'] ?? $cf['pms_id'] ?? ''),
                'normal_type'       => $normalType,
                'second_identifier' => strtoupper(trim((string)($cf['second_identifier'] ?? ''))),
            ];
        }

        return [
            'ok'         => empty($unresolved),
            'fees'       => $fees,
            'unresolved' => $unresolved,
        ];
    }

    private static function canonicalKeys(string $id, string $pmsSource): array
    {
        $keys   = [$id];
        $source = strtolower(trim($pmsSource));

        if ($source !== '') {
            $keys[] = $source . '_' . $id;
        }

        if ($source === 'hostfully') $keys[] = 'hf_' . $id;
        if ($source === 'hostaway')  $keys[] = 'ha_' . $id;

        foreach (self::stripKnownPrefixes($id) as $stripped) {
            $keys[] = $stripped;
        }

        return array_values(array_unique($keys));
    }

    private static function stripKnownPrefixes(string $id): array
    {
        $results = [];
        foreach (['hostfully_', 'hostaway_', 'lodgify_', 'guesty_', 'hf_', 'ha_'] as $prefix) {
            if (str_starts_with($id, $prefix)) {
                $results[] = substr($id, strlen($prefix));
            }
        }
        return $results;
    }

    private static function normalizeIdentity(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower(trim((string)$value))) ?? '';
    }

    private static function fail(
        string $reason,
        float  $expectedTotal,
        ?float $verifiedTotal,
        array  $missingFees   = [],
        ?int   $expectedMinor = null,
        ?int   $verifiedMinor = null
    ): array {
        $d = [
            'reason'         => $reason,
            'expected_total' => $expectedTotal,
            'verified_total' => $verifiedTotal,
        ];
        if (!empty($missingFees))    $d['missing_fees']   = $missingFees;
        if ($expectedMinor !== null) $d['expected_minor'] = $expectedMinor;
        if ($verifiedMinor !== null) $d['verified_minor'] = $verifiedMinor;
        return ['ok' => false, 'discrepancy' => $d];
    }
}
