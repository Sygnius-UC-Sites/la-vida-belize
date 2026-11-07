<?php

namespace App\Helper;

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
            $quote = match ($pmsSource) {
                'hostfully' => self::fromHostfully($rawResponse, $input),
                'hostaway'  => self::fromHostaway($rawResponse, $input),
                'guesty'    => self::fromGuesty($rawResponse, $input),
                'lodgify'   => self::fromLodgify($rawResponse, $input),
                default     => self::error("Unsupported PMS: {$pmsSource}"),
            };
            // Run reconciliation after every successful parse so discrepancies are
            // logged immediately and exposed as reconciliation_gap in the result.
            return self::reconcile($quote, $pmsSource);
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

        // LOG: capture structure so we can diagnose payment schedule issues
        \Log::channel('lodgify')->info('Hostfully quote keys', [
            'quote_keys'        => array_keys($q),
            'data_keys'         => array_keys($raw['data'] ?? []),
            // paymentSchedule is at data level, not inside quote
            'payment_schedule'  => $raw['data']['paymentSchedule'] ?? $q['paymentSchedule'] ?? null,
            'deposit_amount'    => $q['depositAmount']  ?? $q['deposit_amount']  ?? null,
            'deposit_percent'   => $q['depositPercent'] ?? $q['deposit_percent'] ?? null,
            'balance_due_date'  => $q['balanceDueDate'] ?? $q['balance_due_date'] ?? null,
        ]);

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
                // Hostfully returns unit price in either 'netPrice' or 'amount'.
                // 'netPrice' is preferred (it's the actual per-unit net charge);
                // 'amount' is a fallback for older API responses.
                $unitPrice = (float)($fee['netPrice'] ?? $fee['amount'] ?? 0);

                $isPerPet   = in_array($scope, ['PER_PET', 'PER_PET_PER_NIGHT']);
                $isPerGuest = in_array($scope, ['PER_GUEST', 'PER_GUEST_PER_NIGHT']);
                $isPct      = in_array($scope, ['PERCENTAGE_OF_TOTAL', 'PERCENTAGE_OF_RENT']);

                $optional[] = [
                    'id'                   => 'hf_' . ($fee['feeId'] ?? $fee['feeUid'] ?? uniqid()),
                    'pms_id'               => $fee['feeUid'] ?? $fee['feeId'] ?? null,
                    'fee_uid'              => $fee['feeUid'] ?? null,
                    'label'                => $fee['name'] ?? 'Optional fee',
                    'amount'               => $isPct ? null : $unitPrice,
                    'percentage'           => $isPct ? $unitPrice : null, // amount IS the % for percentage scopes
                    'tax_rate'             => (float)($fee['taxationRate'] ?? 0),
                    'enabled'              => (bool)($fee['enabled'] ?? false),
                    'scope'                => strtolower($scope),
                    'per_unit'             => self::scopeLabel($scope),
                    'max_qty'              => $maxQty,
                    'min_qty'              => 0,
                    'is_per_pet'           => $isPerPet,
                    'is_per_guest'         => $isPerGuest,
                    'is_quantity_selectable' => ($isPerPet || $scope === 'PER_UNIT'),
                    'requires_approval'    => false, // Hostfully v3.3 does not expose approval status
                    'pms_source'           => 'hostfully',
                ];
            }
        }

        // ── Hostfully payment schedule ─────────────────────────────────────
        // IMPORTANT: paymentSchedule lives at $raw['data'] level (sibling of 'quote'),
        // NOT inside the 'quote' sub-object. $q only contains the quote sub-object,
        // so $q['paymentSchedule'] is always empty — look at $raw['data'] first.
        // Shape: [{amount, dueDate, type:"DEPOSIT"|"BALANCE", description}]
        $scheduleRaw = $raw['data']['paymentSchedule']
                    ?? $q['paymentSchedule']
                    ?? [];

        $scheduledPayments = [];
        foreach ($scheduleRaw as $pmt) {
            $amt = (float)($pmt['amount'] ?? 0);
            if ($amt <= 0) continue;
            $dueDate = $pmt['dueDate'] ?? null;
            $scheduledPayments[] = [
                'type'       => strtolower($pmt['type'] ?? 'payment'),
                'amount'     => $amt,
                'date_due'   => $dueDate,
                'label'      => $pmt['description'] ?? ucfirst(strtolower($pmt['type'] ?? 'Payment')),
                // is_current: true if payment is due today or has no date (due on booking).
                // Preserved so saveBookingData and acceptPrice() can sum the correct rows.
                'is_current' => empty($dueDate) || $dueDate <= date('Y-m-d'),
            ];
        }

        // Fallback: build schedule from depositAmount / depositPercent inside the quote
        // when the account does not return a paymentSchedule array.
        if (empty($scheduledPayments)) {
            $depositAmt     = (float)($q['depositAmount'] ?? $q['deposit_amount'] ?? 0);
            $depositPct     = (float)($q['depositPercent'] ?? $q['deposit_percent'] ?? 0);
            $depositDueDate = $q['depositDueDate'] ?? $q['deposit_due_date'] ?? null;
            $balanceDueDate = $q['balanceDueDate'] ?? $q['balance_due_date'] ?? null;

            if ($depositPct > 0 && $totalAmount > 0) {
                $depositAmt = round($totalAmount * $depositPct / 100, 2);
            }

            if ($depositAmt > 0 && $depositAmt < $totalAmount) {
                $balanceAmt = round($totalAmount - $depositAmt, 2);
                $pctLabel   = $depositPct > 0 ? ' (' . (int)$depositPct . '%)' : '';
                $scheduledPayments = [
                    [
                        'type'       => 'deposit',
                        'amount'     => $depositAmt,
                        'date_due'   => $depositDueDate ?? date('Y-m-d'),
                        'label'      => "Deposit payment{$pctLabel} due today",
                        'is_current' => true,
                    ],
                    [
                        'type'       => 'payment',
                        'amount'     => $balanceAmt,
                        'date_due'   => $balanceDueDate ?? null,
                        'label'      => 'Balance payment (due before check-in)',
                        'is_current' => !empty($balanceDueDate) && $balanceDueDate <= date('Y-m-d'),
                    ],
                ];
            }
        }

        return [
            'ok'                 => true,
            'currency'           => $currency,
            'nights'             => $nights,
            'night_breakdown'    => $nightBreakdown,
            'line_items'         => $items,
            'security_deposit'   => $deposit > 0 ? $deposit : null,
            'deposit_in_total'   => $depositInTotal,
            'deposit_is_preauth' => !$depositInTotal, // Hostfully: not a pre-auth when in total
            'total'              => $totalAmount,
            'optional_items'     => $optional,
            'scheduled_payments' => $scheduledPayments,
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

        // LOG: find payment schedule fields in Hostaway quote response
        $haResult = $raw['data']['result'] ?? [];
        \Log::channel('lodgify')->info('Hostaway quote keys', [
            'source'           => $source,
            'result_keys'      => array_keys($haResult),
            'payment_schedule' => $haResult['paymentSchedule'] ?? $haResult['payment_schedule'] ?? 'MISSING',
            'deposit_amount'   => $haResult['depositAmount']   ?? $haResult['deposit_amount']   ?? null,
            'deposit_percent'  => $haResult['depositPercent']  ?? null,
            'balance_due_date' => $haResult['balanceDueDate']  ?? null,
        ]);

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
            // Hostaway priceDetails does NOT return scope/feeAppliedPer — only
            // isQuantitySelectable and isMandatory. Scope and per-pet/per-guest flags
            // are enriched from canonical_fees (listingFeeSettings) in QuoteController.
            $optional = [];
            foreach (array_merge($result['extraComponents'] ?? [], $result['selectedComponents'] ?? []) as $c) {
                if (($c['isDeleted'] ?? 0)) continue;
                $isMandatory  = (bool)($c['isMandatory'] ?? 0);
                if ($isMandatory) continue; // mandatory fees already in components[], not selectable
                $isSelectable = (bool)($c['isQuantitySelectable'] ?? 0);
                $maxQty       = $isSelectable ? max(1, (int)($c['quantity'] ?? 1)) : 1;
                $feeSettingId = $c['listingFeeSettingId'] ?? null;
                $optional[] = [
                    'id'                   => 'ha_' . ($feeSettingId ?? uniqid()),
                    'pms_id'               => (string)($feeSettingId ?? ''),
                    'fee_uid'              => (string)($feeSettingId ?? ''),
                    'label'                => $c['alias'] ?? $c['title'] ?? $c['name'] ?? 'Optional',
                    'amount'               => (float)($c['value'] ?? 0),
                    'percentage'           => null,
                    'tax_rate'             => 0,
                    'enabled'              => false,
                    // Scope unknown from priceDetails — enriched from canonical_fees in QuoteController
                    'scope'                => $isSelectable ? 'per_unit' : 'per_stay',
                    'per_unit'             => $isSelectable ? '/unit' : '/stay',
                    'max_qty'              => $maxQty,
                    'min_qty'              => 0,
                    // is_per_pet / is_per_guest: unknown here — QuoteController enriches from canonical
                    'is_per_pet'           => false,
                    'is_per_guest'         => false,
                    'is_quantity_selectable' => $isSelectable,
                    'requires_approval'    => false,
                    'pms_source'           => 'hostaway',
                ];
            }

            // ── Payment schedule ────────────────────────────────────────────
            // Hostaway may return paymentSchedule[] directly, or depositPercent +
            // balanceDueDate that we can use to construct two installments.
            $scheduledPayments = [];
            $total = (float)($result['totalPrice'] ?? 0);

            if (!empty($result['paymentSchedule']) && is_array($result['paymentSchedule'])) {
                // Native payment schedule from Hostaway
                $count = count($result['paymentSchedule']);
                foreach ($result['paymentSchedule'] as $i => $pmt) {
                    $amt     = (float)($pmt['amount'] ?? 0);
                    if ($amt <= 0) continue;
                    $dueDate = $pmt['dueDate'] ?? $pmt['date'] ?? null;
                    $scheduledPayments[] = [
                        'type'       => 'payment',
                        'amount'     => $amt,
                        'date_due'   => $dueDate,
                        'label'      => $count === 1 ? 'Full payment' : ($i === 0 ? 'Deposit' : ($i === $count - 1 ? 'Balance' : 'Payment ' . ($i + 1) . ' of ' . $count)),
                        'is_current' => empty($dueDate) || $dueDate <= date('Y-m-d'),
                    ];
                }
            } elseif (!empty($result['depositPercent']) && $total > 0) {
                // Construct from depositPercent + balanceDueDate
                $depositPct    = (float)$result['depositPercent'];
                $depositAmt    = round($total * $depositPct / 100, 2);
                $balanceAmt    = round($total - $depositAmt, 2);
                $balanceDue    = $result['balanceDueDate'] ?? null;
                if ($depositAmt > 0) {
                    $scheduledPayments[] = ['type' => 'payment', 'amount' => $depositAmt, 'date_due' => date('Y-m-d'), 'label' => 'Deposit', 'is_current' => true];
                }
                if ($balanceAmt > 0) {
                    $scheduledPayments[] = ['type' => 'payment', 'amount' => $balanceAmt, 'date_due' => $balanceDue, 'label' => 'Balance', 'is_current' => !empty($balanceDue) && $balanceDue <= date('Y-m-d')];
                }
            }

            return [
                'ok'                 => true,
                'currency'           => $result['currency'] ?? 'USD',
                'nights'             => $nights,
                'night_breakdown'    => [],
                'line_items'         => $items,
                'security_deposit'   => $deposit ?: null,
                'total'              => $total,
                'optional_items'     => $optional,
                'scheduled_payments' => $scheduledPayments,
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
                'ok'                 => true,
                'currency'           => $currency,
                'nights'             => $nights,
                'night_breakdown'    => [],
                'line_items'         => $items,
                'security_deposit'   => $deposit ?: null,
                'total'              => (float)($calc['totalPrice'] ?? 0),
                'optional_items'     => [],
                'scheduled_payments' => [], // calculate-price fallback has no payment schedule
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
            // Try to extract Guesty's own error message (e.g. invalid coupon, blocked dates)
            $errData = $raw['error'] ?? [];
            if (is_string($errData)) {
                $decoded = json_decode($errData, true);
                $errData = is_array($decoded) ? $decoded : [];
            }
            $guestyMsg = $errData['error']['message']
                ?? $errData['message']
                ?? (is_string($raw['error'] ?? null) ? $raw['error'] : null)
                ?? $raw['message']
                ?? null;

            // Surface coupon-specific errors clearly
            if ($guestyMsg && stripos($guestyMsg, 'coupon') !== false) {
                return self::error('Invalid or expired promo code.');
            }

            return self::error($guestyMsg ?? 'Guesty quote failed');
        }

        // v1/quotes returns data.money directly (not nested under rates.ratePlans)
        // Support both response shapes:
        //   Shape A (v1/quotes):        data.money.invoiceItems
        //   Shape B (older BEAPI):      data.rates.ratePlans[0].money.money.invoiceItems
        $dataRoot = $raw['data'] ?? [];

        // LOG: find payment schedule fields in Guesty quote response
        \Log::channel('lodgify')->info('Guesty quote keys', [
            'data_keys'          => array_keys($dataRoot),
            'money_keys'         => isset($dataRoot['money']) ? array_keys($dataRoot['money']) : null,
            'fare_keys'          => isset($dataRoot['fare'])  ? array_keys($dataRoot['fare'])  : null,
            'payment_schedule'   => $dataRoot['paymentSchedule']  ?? $dataRoot['payment_schedule']  ?? null,
            'due_date'           => $dataRoot['confirmationDueDate'] ?? $dataRoot['balanceDueDate'] ?? null,
            'deposit'            => $dataRoot['money']['securityDeposit'] ?? null,
        ]);

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

        // Guesty's fare.total is the authoritative guest-facing total (accommodation +
        // cleaning + other fees + taxes, excluding refundable deposit).
        // Do NOT use fareAccommodation + fareCleaning + totalFees + totalTaxes because
        // money.totalFees already includes fareCleaning — adding both double-counts it.
        $fareTotal = (float)($dataRoot['fare']['total'] ?? 0);
        if ($fareTotal > 0) {
            $total = $fareTotal;
        } else {
            // Fallback: sum from money fields, but use totalFees only (skip fareCleaning
            // since it is already contained within totalFees in all known response shapes).
            $total = (float)($money['fareAccommodation'] ?? 0)
                   + (float)($money['totalFees']          ?? 0)
                   + (float)($money['totalTaxes']         ?? 0);
        }

        // ── Payment schedule from Guesty paymentWorkflow (includePaymentsTemplate=true) ──
        // Shape A: data.money.paymentWorkflow.workflow.actionTemplates
        // Shape B: data.rates.ratePlans[0].money.paymentWorkflow.workflow.actionTemplates
        $scheduledPayments = [];
        $paymentWorkflow   = $dataRoot['money']['paymentWorkflow']
            ?? $dataRoot['rates']['ratePlans'][0]['money']['paymentWorkflow']
            ?? [];
        $actionTemplates   = $paymentWorkflow['workflow']['actionTemplates'] ?? [];
        if (!empty($actionTemplates) && $total > 0) {
            $remaining = $total;
            foreach ($actionTemplates as $tpl) {
                if (($tpl['type'] ?? '') !== 'PAYMENT') continue;

                $chargeType = $tpl['details']['chargeType'] ?? '';
                $context    = $tpl['when']['context']       ?? 'confirmedAt';
                $offsetDays = (int)($tpl['when']['value']   ?? 0);

                // Compute due date from context + offset
                $dueDate = match ($context) {
                    'checkOut' => $input['checkout'] ?? date('Y-m-d'),
                    'checkIn'  => $input['checkin']  ?? date('Y-m-d'),
                    default    => date('Y-m-d', strtotime("+{$offsetDays} days")), // confirmedAt
                };
                if ($offsetDays > 0 && $context === 'checkIn') {
                    $dueDate = date('Y-m-d', strtotime($input['checkin'] . " -{$offsetDays} days"));
                } elseif ($offsetDays > 0 && $context === 'checkOut') {
                    $dueDate = date('Y-m-d', strtotime($input['checkout'] . " -{$offsetDays} days"));
                }

                $amount = match ($chargeType) {
                    'PERCENTAGE'      => round($total * ((float)($tpl['details']['amount'] ?? 0)) / 100, 2),
                    'FIXED'           => (float)($tpl['details']['amount'] ?? 0),
                    'REST_OF_PAYMENT' => max(0, round($remaining, 2)),
                    default           => 0,
                };

                if ($amount <= 0) continue;
                $remaining -= $amount;

                $scheduledPayments[] = [
                    'type'     => 'payment',
                    'amount'   => $amount,
                    'date_due' => $dueDate,
                    '_index'   => count($scheduledPayments), // used for label below
                ];
            }
        }

        // Label first payment "Deposit", last "Balance", middle ones "Payment X of N"
        $total_pmts = count($scheduledPayments);
        foreach ($scheduledPayments as $i => &$pmt) {
            unset($pmt['_index']);
            if ($total_pmts === 1) {
                $pmt['label'] = 'Full payment';
            } elseif ($i === 0) {
                $pmt['label'] = 'Deposit';
            } elseif ($i === $total_pmts - 1) {
                $pmt['label'] = 'Balance';
            } else {
                $pmt['label'] = 'Payment ' . ($i + 1) . ' of ' . $total_pmts;
            }
        }
        unset($pmt);

        return [
            'ok'                => true,
            'currency'          => $currency,
            'nights'            => $nights,
            'night_breakdown'   => $nightBreakdown,
            'line_items'        => $items,
            'security_deposit'  => $deposit > 0 ? $deposit : null,
            'total'             => $total,
            'optional_items'    => [],
            'scheduled_payments'=> $scheduledPayments,
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

        // Non-2xx → surface a real error. Without this check an error response
        // (e.g. 400 "The Period is closed", code 666) fell through to the parser
        // below, which found no amount_gross and returned ok=true with total=0 —
        // the guest saw a bookable $0.00 quote.
        if ($status < 200 || $status >= 300) {
            $err    = $raw['error'] ?? [];
            if (is_string($err)) {
                $err = json_decode($err, true) ?: ['message' => $err];
            }
            $msg  = (string)($err['message'] ?? '');
            $code = (int)($err['code'] ?? 0);
            // Code 666 / "Period is closed": the host has closed these dates in
            // Lodgify (not a booking — an owner-closed period). Friendly wording.
            if ($code === 666 || stripos($msg, 'period is closed') !== false) {
                return self::error('Those dates are not open for booking. Please choose different dates.');
            }
            return self::error($msg !== '' ? $msg : 'Pricing unavailable for those dates. Please try again.');
        }

        $data = $raw['data'] ?? $raw;
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
            // For Lodgify: scheduled_damage_protection is ALWAYS a pre-authorization hold,
            // never included in amount_gross. So deposit_in_total is always false.
            'deposit_in_total'       => false,
            'deposit_is_preauth'     => !$depositIsCharge, // true = hold only, not charged
            'total'                  => $total,
            'optional_items'         => [],
            // Lodgify-specific fields (booking flow + display)
            'cancellation_policy'    => $entry['cancellation_policy_text'] ?? null,
            'scheduled_payments'     => self::normalizeLodgifyPayments($entry['scheduled_payments'] ?? []),
            'manual_collection'      => $manualAmount > 0 ? $manualAmount : null,
            'requires_verification'  => (bool)($entry['is_verification'] ?? false),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Normalize Lodgify's raw scheduled_payments array into our canonical shape.
     *
     * Lodgify shape:
     *   [{type:"Payment", date_due:"On agreement"|"YYYY-MM-DD", amount, is_current, status}]
     *
     * Canonical:
     *   [{type:"payment", amount, date_due, label, is_current}]
     *
     * is_current=true  → charge due NOW (date_due is often "On agreement")
     * is_current=false → future installment with a real date
     *
     * deposit_due_amount MUST be computed from is_current=true rows, NOT array[0].
     */
    private static function normalizeLodgifyPayments(array $raw): array
    {
        $out   = [];
        $count = count($raw);
        foreach ($raw as $i => $pmt) {
            $amt       = (float)($pmt['amount'] ?? 0);
            if ($amt <= 0) continue;

            $dateDue   = $pmt['date_due'] ?? null;
            $isCurrent = (bool)($pmt['is_current'] ?? ($i === 0)); // fallback: treat first as current
            $isDate    = $dateDue && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateDue);

            // Build a human-friendly label:
            //   is_current=true  → "Deposit (due today)" — shown at booking
            //   is_current=false with a date → "Balance — due [date]"
            //   single payment   → "Full payment"
            if ($count === 1) {
                $label = 'Full payment';
            } elseif ($isCurrent) {
                $label = 'Deposit (due today)';
            } elseif ($i === $count - 1) {
                $label = $isDate
                    ? 'Balance — due ' . date('M j, Y', strtotime($dateDue))
                    : 'Balance';
            } else {
                $label = $isDate
                    ? 'Payment ' . ($i + 1) . ' of ' . $count . ' — due ' . date('M j, Y', strtotime($dateDue))
                    : 'Payment ' . ($i + 1) . ' of ' . $count;
            }

            $out[] = [
                'type'       => 'payment',
                'amount'     => $amt,
                'date_due'   => $dateDue,
                'label'      => $label,
                'is_current' => $isCurrent,
            ];
        }
        return $out;
    }

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

    // ── Universal reconciliation ──────────────────────────────────────────────
    //
    // After normalizing any PMS quote, call this to verify:
    //   sum(included line items) ≈ PMS-authoritative total
    //
    // "Included" means:
    //   - accommodation, cleaning, fee, tax, discount lines (all counted)
    //   - deposit lines EXCLUDED if deposit_in_total=false (pre-auth, not in total)
    //   - deposit lines INCLUDED if deposit_in_total=true (charged as part of total)
    //
    // A gap > $tolerance is logged as a warning and exposed as 'reconciliation_gap'
    // in the normalized quote so templates can show an "Other charges" line.
    // The total is NEVER silently corrected — the PMS total is always authoritative.
    //
    // Currency-aware tolerance:
    //   Zero-decimal currencies (JPY, KRW …): 1 unit
    //   All others: $0.10 (covers rounding in individual line items)

    private static function reconcile(array $quote, string $pms): array
    {
        if (!($quote['ok'] ?? false)) return $quote;

        $total       = (float)($quote['total'] ?? 0);
        $depositIn   = (bool)($quote['deposit_in_total'] ?? false);
        $currency    = strtoupper($quote['currency'] ?? 'USD');

        $zeroDecimal = ['BIF','CLP','DJF','GNF','JPY','KMF','KRW','MGA','PYG','RWF','VND','VUV','XAF','XOF','XPF'];
        $tolerance   = in_array($currency, $zeroDecimal) ? 1.0 : 0.10;

        $lineSum = 0.0;
        foreach ($quote['line_items'] ?? [] as $item) {
            $type = strtolower($item['type'] ?? '');
            // Exclude deposit when it is a pre-auth (not part of the charged total)
            if ($type === 'deposit' && !$depositIn) continue;
            $lineSum += (float)($item['amount'] ?? 0);
        }

        $gap = round($total - $lineSum, 4);

        if ($total > 0 && abs($gap) > $tolerance) {
            \Log::channel('lodgify')->warning("QuoteNormalizer [{$pms}]: reconciliation gap", [
                'pms_total'  => $total,
                'line_sum'   => round($lineSum, 4),
                'gap'        => $gap,
                'currency'   => $currency,
                'line_types' => array_count_values(array_column($quote['line_items'] ?? [], 'type')),
            ]);
            // Expose gap as a named line so the frontend can show "Other charges"
            // rather than display a total that doesn't match the sum of visible lines.
            if (abs($gap) > $tolerance) {
                $quote['reconciliation_gap'] = $gap;
            }
        }

        return $quote;
    }

    private static function error(string $message): array
    {
        return ['ok' => false, 'error' => $message];
    }
}
