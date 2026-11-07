<?php

namespace App\Modules\MultiPms\Helper;

/**
 * Maps the raw quote API response from any PMS into one canonical QuoteResult shape.
 *
 * Canonical shape:
 *   ok               bool
 *   currency         string   ISO code ("USD")
 *   nights           int
 *   night_breakdown  array    [{date, amount}]  — only when PMS returns per-night pricing
 *   line_items       array    [{label, amount, type}]
 *                    types: accommodation | cleaning | tax | fee | deposit
 *   total            float    authoritative total from PMS (may include deposit)
 *   optional_items   array    [{id, label, amount, tax_rate, enabled}]  — shown as checkboxes
 *   error            string   present only when ok=false
 */
class QuoteNormalizer
{
    public static function normalize(array $rawResponse, string $pmsSource, array $input = []): array
    {
        try {
            return match ($pmsSource) {
                'hostfully' => self::fromHostfully($rawResponse, $input),
                'hostaway'  => self::fromHostaway($rawResponse, $input),
                'guesty'    => self::fromGuesty($rawResponse, $input),
                'lodgify'   => self::fromLodgify($rawResponse, $input),
                default     => self::error("Unsupported PMS: {$pmsSource}"),
            };
        } catch (\Throwable $e) {
            \Log::channel('lodgify')->error("QuoteNormalizer [{$pmsSource}]", ['error' => $e->getMessage()]);
            return self::error('Quote calculation failed');
        }
    }

    // ── Hostfully ─────────────────────────────────────────────────────────────
    // Spec-complete normalizer. All known fields from the Hostfully v3.3 API.
    // quote object: rent | fees | services | adjustmentItems | securityDeposit
    //               includeSecurityDepositInTotal | totalAmount | totalWithoutDiscount
    //               currency | overrides
    private static function fromHostfully(array $raw, array $input): array
    {
        $status = $raw['status'] ?? 0;
        if ($status < 200 || $status >= 300) {
            return self::error($raw['message'] ?? $raw['error'] ?? 'Hostfully quote failed');
        }

        $q    = $raw['data']['quote'] ?? $raw['quote'] ?? [];
        $rent = $q['rent']  ?? [];
        $fees = $q['fees']  ?? [];
        $over = $q['overrides']['fees']['otherFees'] ?? [];

        $currency = $q['currency'] ?? 'USD';

        // ── Night breakdown (variable nightly pricing) ─────────────────────
        $nightBreakdown = [];
        foreach ($rent['rentBreakdowns'] ?? [] as $n) {
            $nightBreakdown[] = ['date' => $n['nightlyDate'], 'amount' => (float)$n['nightlyAmount']];
        }
        $nights = count($nightBreakdown) ?: self::nightCount($input['checkin'] ?? '', $input['checkout'] ?? '');

        // ── Line items ─────────────────────────────────────────────────────
        $items = [];

        // Accommodation: rent.netPrice = base nightly rent + extraGuestsNetPrice (combined)
        // Show extra-guest portion separately when the property charges per extra person
        $baseRent   = (float)($rent['rentNetPrice'] ?? $rent['netPrice'] ?? 0);
        $extraGuest = (float)($rent['extraGuestsNetPrice'] ?? 0);
        $accommodation = $baseRent - $extraGuest; // base rent without extra-guest portion
        if ($accommodation != 0) {
            $items[] = ['label' => $nights . ' night' . ($nights !== 1 ? 's' : ''), 'amount' => $accommodation, 'type' => 'accommodation'];
        } elseif ((float)($rent['netPrice'] ?? 0) > 0) {
            // Fallback: use netPrice as combined if breakdown not possible
            $items[] = ['label' => $nights . ' night' . ($nights !== 1 ? 's' : ''), 'amount' => (float)$rent['netPrice'], 'type' => 'accommodation'];
        }
        if ($extraGuest > 0) {
            $items[] = ['label' => 'Extra guest fee', 'amount' => $extraGuest, 'type' => 'fee'];
        }

        // Promo code discount — totalWithoutDiscount shows pre-discount total;
        // difference is the discount amount. Show it as a negative line item.
        $totalWithoutDiscount = (float)($q['totalWithoutDiscount'] ?? 0);
        $totalAmount          = (float)($q['totalAmount'] ?? $q['totalPrice'] ?? 0);
        if ($totalWithoutDiscount > 0 && $totalWithoutDiscount > $totalAmount) {
            $discountAmt  = $totalAmount - $totalWithoutDiscount; // negative
            $promoCode    = $q['rent']['discount']['code'] ?? null;
            $discountLabel = $promoCode ? "Promo code ({$promoCode})" : 'Discount';
            $items[] = ['label' => $discountLabel, 'amount' => $discountAmt, 'type' => 'discount'];
        }

        // Cleaning fee (net, before its own tax)
        $cleaning    = $fees['cleaningFee'] ?? [];
        $cleaningNet = (float)($cleaning['netPrice'] ?? 0);
        if ($cleaningNet > 0) {
            $items[] = ['label' => 'Cleaning fee', 'amount' => $cleaningNet, 'type' => 'cleaning'];
        }

        // Taxes — combine rent tax + cleaning fee tax; also add taxes from otherFees
        $rentTax     = (float)($rent['taxAmount'] ?? 0);
        $cleaningTax = (float)($cleaning['taxAmount'] ?? 0);
        $otherTax    = 0.0;
        foreach ($fees['otherFees'] ?? [] as $fee) {
            if (($fee['type'] ?? '') === 'TAX') {
                $otherTax += (float)($fee['grossPrice'] ?? $fee['netPrice'] ?? 0);
            }
        }
        $totalTax = $rentTax + $cleaningTax + $otherTax;
        $taxRate  = (float)($rent['taxationRate'] ?? 0);
        if ($totalTax > 0) {
            $taxLabel = $taxRate > 0 ? "Taxes ({$taxRate}%)" : 'Taxes';
            $items[] = ['label' => $taxLabel, 'amount' => $totalTax, 'type' => 'tax'];
        }

        // Required fees (non-tax) from fees.otherFees[]
        foreach ($fees['otherFees'] ?? [] as $fee) {
            if (($fee['type'] ?? '') === 'TAX') continue; // already handled above
            $net = (float)($fee['netPrice'] ?? 0);
            if ($net != 0 && !empty($fee['name'])) {
                $items[] = ['label' => $fee['name'], 'amount' => $net, 'type' => 'fee'];
            }
        }

        // Additional services on the order (services[])
        foreach ($q['services'] ?? [] as $service) {
            $gross = (float)($service['grossPrice'] ?? 0);
            if ($gross != 0 && !empty($service['title'])) {
                $items[] = ['label' => $service['title'], 'amount' => $gross, 'type' => 'fee'];
            }
        }

        // Adjustment items — CHARGE and REFUND adjustments applied to quote
        foreach ($q['adjustmentItems'] ?? [] as $adj) {
            $amt = (float)($adj['amount'] ?? 0);
            if ($amt == 0) continue;
            $transType = strtoupper($adj['transactionType'] ?? 'CHARGE');
            $adjType   = strtolower($adj['adjustmentType'] ?? 'fee');
            $note      = $adj['note'] ?? ucfirst($adjType) . ' adjustment';
            if ($transType === 'REFUND') $amt = -abs($amt);
            $items[] = ['label' => $note, 'amount' => $amt, 'type' => $adjType === 'tax' ? 'tax' : 'fee'];
        }

        // Security deposit — check includeSecurityDepositInTotal to avoid misrepresentation.
        // If true: deposit is already inside totalAmount — show as info but flag it.
        // If false (default): deposit is separate — show as its own line and add to display.
        $deposit            = (float)($q['securityDeposit'] ?? 0);
        $depositInTotal     = (bool)($q['includeSecurityDepositInTotal'] ?? false);
        if ($deposit > 0) {
            $depositLabel = 'Security deposit (refundable' . ($depositInTotal ? ', included in total' : '') . ')';
            $items[] = ['label' => $depositLabel, 'amount' => $deposit, 'type' => 'deposit'];
        }

        // ── Optional items ────────────────────────────────────────────────
        // Full scope enum from Hostfully v3.3 API spec:
        //   PER_STAY             → flat once per booking → max_qty 1 (toggle)
        //   PER_NIGHT            → flat per night (JS: amount × nights) → max_qty 1
        //   PER_GUEST            → per guest → max_qty = guest count (default 10)
        //   PER_GUEST_PER_NIGHT  → per guest per night (JS: amount × guests × nights)
        //   PER_PET              → per pet → max_qty 5 (stepper)
        //   PER_PET_PER_NIGHT    → per pet per night → max_qty 5 (stepper)
        //   PERCENTAGE_OF_TOTAL  → % of total; amount is the % value — use as info, not qty
        //   PERCENTAGE_OF_RENT   → % of rent only
        $guestCount = (int)($input['adults'] ?? 1) + (int)($input['children'] ?? 0);
        $optional = [];
        foreach ($over as $fee) {
            if (!empty($fee['isOptional'])) {
                $scope  = strtoupper($fee['scope'] ?? 'PER_STAY');
                $maxQty = match ($scope) {
                    'PER_PET', 'PER_PET_PER_NIGHT'             => 5,
                    'PER_GUEST', 'PER_GUEST_PER_NIGHT'          => max($guestCount, 1),
                    'PERCENTAGE_OF_TOTAL', 'PERCENTAGE_OF_RENT' => 1, // on/off; JS shows as %
                    default                                      => 1,
                };
                $optional[] = [
                    'id'       => 'hf_' . ($fee['feeId'] ?? $fee['feeUid'] ?? uniqid()),
                    'fee_uid'  => $fee['feeUid'] ?? null,
                    'label'    => $fee['name'] ?? 'Optional fee',
                    'amount'   => (float)($fee['amount'] ?? 0),
                    'tax_rate' => (float)($fee['taxationRate'] ?? 0),
                    'enabled'  => (bool)($fee['enabled'] ?? false),
                    'scope'    => strtolower($scope),
                    'per_unit' => self::scopeLabel($scope),
                    'max_qty'  => $maxQty,
                ];
            }
        }

        return [
            'ok'               => true,
            'currency'         => $currency,
            'nights'           => $nights,
            'night_breakdown'  => $nightBreakdown,
            'line_items'       => $items,
            'security_deposit' => $deposit > 0 ? $deposit : null,
            'deposit_in_total' => $depositInTotal,
            'total'            => $totalAmount,
            'optional_items'   => $optional,
        ];
    }

    // ── Hostaway ──────────────────────────────────────────────────────────────
    // Spec-complete normalizer based on Hostaway priceDetails API.
    // component types: accommodation | fee | tax | discount (negative value)
    // Three possible sources (set by getRawQuote via _source key):
    //   'priceDetails'    → data.result: { totalPrice, components[], extraComponents[], selectedComponents[] }
    //   'calculate-price' → data.result: { subtotal, cleaningFee, taxAmount, totalPrice }
    //   'calendar'        → _calendar: [{date, price, isAvailable}], _listing: listing object
    private static function fromHostaway(array $raw, array $input): array
    {
        $status = $raw['status'] ?? 0;
        if ($status !== 200) {
            return self::error($raw['message'] ?? 'Hostaway quote failed');
        }

        $source   = $raw['_source'] ?? 'calculate-price';
        $checkin  = $input['checkin']  ?? '';
        $checkout = $input['checkout'] ?? '';

        // ── priceDetails (authoritative) ──────────────────────────────────────
        if ($source === 'priceDetails') {
            // request() wraps response as {status:200, data:{status:'success', result:{...}}}
            $result     = $raw['data']['result'] ?? [];
            $components = $result['components'] ?? [];
            $nights     = self::nightCount($checkin, $checkout);
            $items      = [];
            $deposit    = 0.0;

            $accommodation = 0.0;
            $fees = $discounts = $taxes = [];

            foreach ($components as $c) {
                // isIncludedInTotalPrice=0: OTA-remitted taxes, channel-handled items.
                // Still show them as informational lines so guests see the full picture.
                $inTotal = (bool)($c['isIncludedInTotalPrice'] ?? 1);
                $amount  = (float)($c['total'] ?? $c['value'] ?? 0);
                $title   = $c['alias'] ?? $c['title'] ?? $c['name'] ?? 'Fee';
                $type    = strtolower($c['type'] ?? '');
                $name    = strtolower($c['name'] ?? '');

                // type: accommodation → nightly rate total
                // type: discount      → negative amount (weekly/monthly/coupon discounts)
                // type: tax           → government tax (use title as-is — multiple tax types)
                // type: fee           → all other fees; deposit detected by name (no deposit type)
                if ($type === 'accommodation') {
                    $accommodation += $amount;
                } elseif ($type === 'discount') {
                    // Discounts have negative total; show with original title
                    $discounts[] = ['label' => $title, 'amount' => $amount, 'type' => 'discount'];
                } elseif ($type === 'tax') {
                    $extra = $inTotal ? '' : ' (remitted by channel)';
                    $taxes[] = ['label' => $title . $extra, 'amount' => $amount, 'type' => 'tax'];
                } elseif ($name === 'securitydeposit' || $name === 'refundabledamagedeposit' || $name === 'damagedeposit') {
                    $deposit = $amount;
                } elseif ($type === 'fee' && $amount != 0) {
                    $feeType = ($name === 'cleaningfee') ? 'cleaning' : 'fee';
                    $fees[] = ['label' => $title, 'amount' => $amount, 'type' => $feeType];
                }
            }

            if ($accommodation > 0) {
                $items[] = ['label' => $nights . ' night' . ($nights !== 1 ? 's' : ''), 'amount' => $accommodation, 'type' => 'accommodation'];
            }
            foreach ($discounts as $d) $items[] = $d;
            foreach ($fees      as $f) $items[] = $f;
            foreach ($taxes     as $t) $items[] = $t;
            if ($deposit > 0) {
                $items[] = ['label' => 'Security deposit (refundable)', 'amount' => $deposit, 'type' => 'deposit'];
            }

            // Optional extras from extraComponents[] and selectedComponents[].
            // Hostaway doesn't return a scope field — use isQuantitySelectable to determine
            // whether to show a stepper (max_qty from quantity field, default 1).
            $optional = [];
            foreach (array_merge($result['extraComponents'] ?? [], $result['selectedComponents'] ?? []) as $c) {
                if (($c['isDeleted'] ?? 0)) continue;
                // Show all non-deleted extras; isQuantitySelectable drives whether it's a stepper
                $isSelectable = (bool)($c['isQuantitySelectable'] ?? 0);
                $maxQty       = $isSelectable ? (int)($c['quantity'] ?? 1) : 1;
                $maxQty       = max($maxQty, 1);
                $optional[] = [
                    'id'       => 'ha_' . ($c['listingFeeSettingId'] ?? uniqid()),
                    'label'    => $c['alias'] ?? $c['title'] ?? $c['name'] ?? 'Optional',
                    'amount'   => (float)($c['value'] ?? 0),
                    'tax_rate' => 0,
                    'enabled'  => false,
                    'scope'    => $isSelectable ? 'per_unit' : 'per_stay',
                    'per_unit' => $isSelectable ? '/unit' : '/stay',
                    'max_qty'  => $maxQty,
                ];
            }

            return [
                'ok'               => true,
                'currency'         => $result['currency'] ?? 'USD',
                'nights'           => $nights,
                'night_breakdown'  => [],
                'line_items'       => $items,
                'security_deposit' => $deposit ?: null,
                'total'            => (float)($result['totalPrice'] ?? 0),
                'optional_items'   => $optional,
            ];
        }

        // ── calculate-price (kept as secondary fallback) ───────────────────────
        if ($source === 'calculate-price') {
            $calc     = $raw['data']['result'] ?? $raw['result'] ?? [];
            $currency = $calc['currency'] ?? 'USD';
            $nights   = self::nightCount($checkin, $checkout);
            $items    = [];

            $accommodation = (float)($calc['subtotal'] ?? 0);
            if ($accommodation > 0) {
                $items[] = ['label' => $nights . ' night' . ($nights !== 1 ? 's' : ''), 'amount' => $accommodation, 'type' => 'accommodation'];
            }
            $cleaning = (float)($calc['cleaningFee'] ?? 0);
            if ($cleaning > 0) {
                $items[] = ['label' => 'Cleaning fee', 'amount' => $cleaning, 'type' => 'cleaning'];
            }
            $tax = (float)($calc['taxAmount'] ?? 0);
            if ($tax > 0) {
                $items[] = ['label' => 'Taxes', 'amount' => $tax, 'type' => 'tax'];
            }
            $deposit = (float)($calc['damageDeposit'] ?? $calc['refundableDamageDeposit'] ?? 0);
            if ($deposit > 0) {
                $items[] = ['label' => 'Security deposit (refundable)', 'amount' => $deposit, 'type' => 'deposit'];
            }

            return [
                'ok' => true, 'currency' => $currency, 'nights' => $nights,
                'night_breakdown' => [], 'line_items' => $items,
                'security_deposit' => $deposit ?: null,
                'total' => (float)($calc['totalPrice'] ?? 0),
                'optional_items' => [],
            ];
        }

        // ── Calendar-based fallback ──────────────────────────────────────────
        $calendar = $raw['_calendar'] ?? [];
        $listing  = $raw['_listing']  ?? [];
        $currency = $listing['currency'] ?? $listing['currencyCode'] ?? 'USD';

        // Sum nightly prices for dates in range [checkin, checkout)
        $nightBreakdown = [];
        $accommodation  = 0.0;
        foreach ($calendar as $day) {
            $d = $day['date'] ?? '';
            if ($d >= $checkin && $d < $checkout && ($day['isAvailable'] ?? 0)) {
                $price = (float)($day['price'] ?? 0);
                $nightBreakdown[] = ['date' => $d, 'amount' => $price];
                $accommodation   += $price;
            }
        }
        $nights = count($nightBreakdown) ?: self::nightCount($checkin, $checkout);

        $items = [];
        if ($accommodation > 0) {
            $items[] = ['label' => $nights . ' night' . ($nights !== 1 ? 's' : ''), 'amount' => $accommodation, 'type' => 'accommodation'];
        }

        $cleaning = (float)($listing['cleaningFee'] ?? 0);
        if ($cleaning > 0) {
            $items[] = ['label' => 'Cleaning fee', 'amount' => $cleaning, 'type' => 'cleaning'];
        }

        $checkinFee = (float)($listing['checkinFee'] ?? 0);
        if ($checkinFee > 0) {
            $items[] = ['label' => 'Check-in fee', 'amount' => $checkinFee, 'type' => 'fee'];
        }

        $deposit = (float)($listing['refundableDamageDeposit'] ?? 0);
        if ($deposit > 0) {
            $items[] = ['label' => 'Security deposit (refundable)', 'amount' => $deposit, 'type' => 'deposit'];
        }

        $total = $accommodation + $cleaning + $checkinFee;

        return [
            'ok'               => true,
            'currency'         => $currency,
            'nights'           => $nights,
            'night_breakdown'  => $nightBreakdown,
            'line_items'       => $items,
            'security_deposit' => $deposit ?: null,
            'total'            => $total,
            'optional_items'   => [],
            // Flag so the frontend can warn the guest that taxes are not included
            // because the authoritative priceDetails endpoint was unavailable.
            'approximate'      => true,
            'approximate_note' => 'Taxes not included — final price confirmed at booking',
        ];
    }

    // ── Guesty ────────────────────────────────────────────────────────────────
    // Spec-complete normalizer. All normalType values from Guesty Open API v3 spec.
    // money.invoiceItems[]: type | normalType | secondIdentifier | amount (can be negative)
    // Discount normalTypes: AFD, AFA, AFWD, AFMD, CO, GCD, LOSD, PRO (all negative amounts)
    // Tax normalTypes: LT, ST, CT, RT, HT, GST, VAT, HST, OCT, TOT, TT, TAX, etc.
    // Fee normalType AFE uses secondIdentifier for sub-type (CLEANING, PET, PARKING, etc.)
    // Security deposit: normalType SDC
    private static function fromGuesty(array $raw, array $input): array
    {
        $status = $raw['status'] ?? 0;
        if ($status !== 200) {
            return self::error($raw['message'] ?? 'Guesty quote failed');
        }

        // v1/quotes returns data.money directly (not nested under rates.ratePlans)
        // Support both response shapes:
        //   Shape A (v1/quotes):        data.money.invoiceItems
        //   Shape B (older BEAPI):      data.rates.ratePlans[0].money.money.invoiceItems
        $dataRoot = $raw['data'] ?? [];

        if (!empty($dataRoot['money'])) {
            // Shape A — v1/quotes (current)
            $money = $dataRoot['money'];
        } elseif (!empty($dataRoot['rates']['ratePlans'][0]['money']['money'])) {
            // Shape B — older BEAPI
            $money = $dataRoot['rates']['ratePlans'][0]['money']['money'];
        } else {
            // Neither shape — log and return graceful error
            \Log::channel('lodgify')->error('Guesty quote: unexpected response shape', [
                'keys' => array_keys($dataRoot),
            ]);
            return self::error('Guesty quote: unexpected response format');
        }

        $invoiceItems = $money['invoiceItems'] ?? [];
        $nightlyItems = $money['nightlyRateInvoiceItems'] ?? [];

        // currency can be a plain string ("USD") or an object {from, to} — handle both
        $currencyRaw = $money['currency'] ?? 'USD';
        $currency    = is_array($currencyRaw)
            ? ($currencyRaw['from'] ?? $currencyRaw['to'] ?? 'USD')
            : (string)$currencyRaw;

        // Night breakdown: per-night AF items (or from ratePlan.days[] on BEAPI)
        $nightBreakdown = [];
        foreach ($nightlyItems as $nItem) {
            if (($nItem['normalType'] ?? '') === 'AF') {
                foreach ($nItem['nightsBreakdown'] ?? [] as $nb) {
                    $nightBreakdown[] = ['date' => $nb['date'], 'amount' => (float)$nb['basePrice']];
                }
                break;
            }
        }
        // Also check ratePlan.days[] (BEAPI response shape)
        if (empty($nightBreakdown)) {
            foreach ($ratePlan['days'] ?? [] as $day) {
                if (!empty($day['date'])) {
                    $nightBreakdown[] = ['date' => $day['date'], 'amount' => (float)($day['price'] ?? $day['basePrice'] ?? 0)];
                }
            }
        }
        $nights = count($nightBreakdown) ?: self::nightCount($input['checkin'] ?? '', $input['checkout'] ?? '');

        // Discount normalTypes (all produce negative amounts)
        $discountTypes = ['AFD', 'AFA', 'AFWD', 'AFMD', 'CO', 'GCD', 'LOSD', 'PRO', 'MARD'];
        // Tax normalTypes
        $taxTypes = ['LT', 'ST', 'CT', 'RT', 'HT', 'GST', 'VAT', 'HST', 'OCT', 'TOT',
                     'TT', 'TAX', 'AGST', 'AST', 'ATT', 'COT', 'HSHAT', 'LGT', 'MAT',
                     'TAF', 'TTH', 'TRT', 'TAXD'];

        $items         = [];
        $deposit       = 0.0;
        // fareAccommodation: prefer money field, fall back to fare.accommodation (v1/quotes shape A)
        $accommodation = (float)($money['fareAccommodation']
            ?? $dataRoot['fare']['accommodation']
            ?? 0);
        if ($accommodation > 0) {
            $items[] = ['label' => $nights . ' night' . ($nights !== 1 ? 's' : ''), 'amount' => $accommodation, 'type' => 'accommodation'];
        }

        foreach ($invoiceItems as $item) {
            $type       = $item['type']       ?? '';
            $normalType = $item['normalType'] ?? '';
            $amount     = (float)($item['amount'] ?? 0);
            $title      = $item['title'] ?? '';

            if ($type === 'ACCOMMODATION_FARE' || $normalType === 'AF') continue; // in fareAccommodation

            // ── Discounts (negative amounts) ────────────────────────────────
            if (in_array($normalType, $discountTypes) || ($amount < 0 && in_array($type, ['ADDITIONAL']))) {
                $items[] = ['label' => ($title ?: 'Discount'), 'amount' => $amount, 'type' => 'discount'];

            // ── Cleaning fee ─────────────────────────────────────────────────
            } elseif ($type === 'CLEANING_FEE' || $normalType === 'CF') {
                $items[] = ['label' => ($title ?: 'Cleaning fee'), 'amount' => $amount, 'type' => 'cleaning'];

            // ── Security deposit ─────────────────────────────────────────────
            } elseif ($normalType === 'SDC') {
                $deposit = $amount;

            // ── Extra person fee ─────────────────────────────────────────────
            } elseif ($normalType === 'EPF') {
                if ($amount != 0) {
                    $items[] = ['label' => ($title ?: 'Extra guest fee'), 'amount' => $amount, 'type' => 'fee'];
                }

            // ── Taxes ────────────────────────────────────────────────────────
            } elseif ($type === 'TAX' || in_array($normalType, $taxTypes)) {
                if ($amount != 0) {
                    $items[] = ['label' => ($title ?: 'Taxes'), 'amount' => $amount, 'type' => 'tax'];
                }

            // ── All other fees (AFE, markups, etc.) ──────────────────────────
            } elseif ($amount != 0) {
                $items[] = ['label' => ($title ?: 'Fee'), 'amount' => $amount, 'type' => 'fee'];
            }
        }

        if ($deposit > 0) {
            $items[] = ['label' => 'Security deposit (refundable)', 'amount' => $deposit, 'type' => 'deposit'];
        }

        // Total = fareAccommodation + fareCleaning + totalFees + totalTaxes
        // (equals hostPayout on direct bookings; hostServiceFee is 0 for direct)
        $total = (float)($money['fareAccommodation'] ?? 0)
               + (float)($money['fareCleaning']      ?? 0)
               + (float)($money['totalFees']          ?? 0)
               + (float)($money['totalTaxes']         ?? 0);

        return [
            'ok'               => true,
            'currency'         => $currency,
            'nights'           => $nights,
            'night_breakdown'  => $nightBreakdown,
            'line_items'       => $items,
            'security_deposit' => $deposit > 0 ? $deposit : null,
            'total'            => $total,
            'optional_items'   => [],
        ];
    }

    // ── Lodgify ───────────────────────────────────────────────────────────────
    // Actual response: data[0] with:
    //   currency_code, total_excluding_vat, total_vat, amount_gross
    //   room_types[0].price_types: [{type:0=rate, type:1=discount, type:2=fees, type:4=taxes, prices:[]}]
    //   add_ons: [{name, value}]
    //   scheduled_damage_protection: [{type:"Authorization", amount}]  — security deposit
    //   scheduled_payments: [{type:"Payment", date_due, amount}]       — installments
    //   cancellation_policy_text, security_deposit_text
    private static function fromLodgify(array $raw, array $input): array
    {
        $status = $raw['status'] ?? 0;
        $data   = $raw['data'] ?? $raw;
        if (empty($data) || !is_array($data)) {
            return self::error('Lodgify quote unavailable');
        }

        $entry    = is_array($data[0] ?? null) ? $data[0] : $data;
        $currency = $entry['currency_code'] ?? 'USD';
        $nights   = self::nightCount($input['checkin'] ?? '', $input['checkout'] ?? '');
        $items    = [];

        // price_types by type code (confirmed from Lodgify API spec):
        //   0 = room rate         (prices[] has per-night breakdown with date_from/date_to/amount)
        //   1 = discount/promo    (subtotal is negative; description names the promotion)
        //   2 = fees              (prices[] has individual fee line items)
        //   4 = taxes             (use top-level total_vat for accuracy)
        //   3, 5+ = not documented; skip gracefully
        $roomType      = $entry['room_types'][0] ?? [];
        $priceTypes    = $roomType['price_types'] ?? [];
        $nightBreakdown = [];
        $accommodation  = 0.0;

        foreach ($priceTypes as $pt) {
            $subtotal = (float)($pt['subtotal'] ?? 0);
            switch ((int)($pt['type'] ?? -1)) {
                case 0: // Room rate — extract per-night breakdown from prices[] when available
                    $accommodation = $subtotal;
                    foreach ($pt['prices'] ?? [] as $price) {
                        // price entries have: date_from, date_to, amount, description
                        if (!empty($price['date_from']) && isset($price['amount'])) {
                            $nightBreakdown[] = [
                                'date'   => $price['date_from'],
                                'amount' => (float)$price['amount'],
                            ];
                        }
                    }
                    break;
                case 1: // Promotion / discount (subtotal is negative)
                    if ($subtotal != 0) {
                        $items[] = ['label' => $pt['description'] ?? 'Discount', 'amount' => $subtotal, 'type' => 'discount'];
                    }
                    break;
                case 2: // Fees — iterate prices[] for individual line items
                    foreach ($pt['prices'] ?? [] as $price) {
                        $amt = (float)($price['amount'] ?? 0);
                        if ($amt != 0) {
                            $items[] = ['label' => $price['description'] ?? ($pt['description'] ?? 'Fee'), 'amount' => $amt, 'type' => 'cleaning'];
                        }
                    }
                    if (empty($pt['prices']) && $subtotal != 0) {
                        $items[] = ['label' => $pt['description'] ?? 'Fees', 'amount' => $subtotal, 'type' => 'cleaning'];
                    }
                    break;
                case 4: // Taxes — use top-level total_vat (more reliable than subtotal)
                    $taxTotal = (float)($entry['total_vat'] ?? $subtotal);
                    if ($taxTotal != 0) {
                        $taxLabel = $pt['description'] ?? 'Taxes';
                        $items[] = ['label' => $taxLabel, 'amount' => $taxTotal, 'type' => 'tax'];
                    }
                    break;
                // case 3, 5+ — not documented; silently skip
            }
        }

        // Prepend accommodation as first line item
        if ($accommodation != 0) {
            array_unshift($items, ['label' => $nights . ' night' . ($nights !== 1 ? 's' : ''), 'amount' => $accommodation, 'type' => 'accommodation']);
        }

        // Add-ons included in this quote (auto-applied or explicitly requested).
        // Note: truly optional add-ons require GET /v1/properties/{id}/addons — not in quote response.
        foreach ($entry['add_ons'] ?? [] as $addon) {
            $val = (float)($addon['value'] ?? $addon['amount'] ?? 0);
            if ($val != 0) {
                $label = $addon['name'] ?? 'Add-on';
                if (!empty($addon['details'])) $label .= ' — ' . $addon['details'];
                $items[] = ['label' => $label, 'amount' => $val, 'type' => 'fee'];
            }
        }

        // Security deposit — scheduled_damage_protection[].type can be:
        //   "Authorization" — card hold (most common; not charged)
        //   "Charge"        — upfront charge (theoretically possible)
        $deposit = 0.0;
        $depositIsCharge = false;
        foreach ($entry['scheduled_damage_protection'] ?? [] as $sdp) {
            $sdpType = strtolower($sdp['type'] ?? '');
            if ($sdpType === 'authorization' || $sdpType === 'charge') {
                $deposit         = (float)($sdp['amount'] ?? 0);
                $depositIsCharge = ($sdpType === 'charge');
                break;
            }
        }
        if ($deposit > 0) {
            $depositText = $entry['security_deposit_text']
                ?? ($depositIsCharge ? 'Security deposit (charged upfront)' : 'Security deposit (refundable pre-authorization)');
            $items[] = ['label' => $depositText, 'amount' => $deposit, 'type' => 'deposit'];
        }

        // Total is always amount_gross (inclusive of all fees, taxes, add-ons).
        // Deposit pre-auth is NOT part of amount_gross — shown separately.
        $total = (float)($entry['amount_gross'] ?? 0);

        // Manual collection note — when total_to_collect_manually > 0, part of the
        // payment is outside Lodgify. Show to guest so they aren't surprised.
        $manualAmount = (float)($entry['total_to_collect_manually'] ?? 0);

        return [
            'ok'                     => true,
            'currency'               => $currency,
            'nights'                 => $nights,
            'night_breakdown'        => $nightBreakdown,
            'line_items'             => $items,
            'security_deposit'       => $deposit ?: null,
            'total'                  => $total,
            'optional_items'         => [],
            // Lodgify-specific fields (booking flow + display)
            'cancellation_policy'    => $entry['cancellation_policy_text'] ?? null,
            'scheduled_payments'     => $entry['scheduled_payments'] ?? [],
            'manual_collection'      => $manualAmount > 0 ? $manualAmount : null,
            'requires_verification'  => (bool)($entry['is_verification'] ?? false),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function nightCount(string $checkin, string $checkout): int
    {
        if (!$checkin || !$checkout) return 0;
        try {
            return (int)(new \DateTime($checkout))->diff(new \DateTime($checkin))->days;
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function scopeLabel(string $scope): string
    {
        return match (strtoupper($scope)) {
            'PER_NIGHT'              => '/night',
            'PER_PET'                => '/pet',
            'PER_PET_PER_NIGHT'      => '/pet/night',
            'PER_GUEST'              => '/guest',
            'PER_GUEST_PER_NIGHT'    => '/guest/night',
            'PERCENTAGE_OF_TOTAL'    => '% of total',
            'PERCENTAGE_OF_RENT'     => '% of rent',
            'PER_UNIT'               => '/unit',
            default                  => '/stay',   // PER_STAY
        };
    }

    private static function error(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
