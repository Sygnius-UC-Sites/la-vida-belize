<?php

namespace App\Services\Booking;

use App\Models\Property;

class QuoteCalculator
{
    public function calculate(QuoteInput $input): Quote
    {
        $quote = new Quote();
        $property = Property::find($input->property_id);
        if (!$property) {
            $quote->valid = false;
            $quote->errors[] = 'Property not found';
            return $quote;
        }

        $checkIn = \Carbon\Carbon::parse($input->check_in)->startOfDay();
        $checkOut = \Carbon\Carbon::parse($input->check_out)->startOfDay();
        $today = \Carbon\Carbon::today();

        if ($checkOut->lte($checkIn)) {
            $quote->valid = false;
            $quote->errors[] = 'Check-out must be after check-in';
            return $quote;
        }
        if ($checkIn->lt($today)) {
            $quote->valid = false;
            $quote->errors[] = 'Check-in cannot be in the past';
            return $quote;
        }

        $quote->nights = (int)$checkIn->diffInDays($checkOut);

        // Only paid/confirmed bookings block dates. A bare enquiry (`pending-approval`)
        // must NOT block — multiple guests may enquire for the same dates and the host
        // chooses one. `booking-accepted` (host approved, awaiting payment) also doesn't
        // block until the magic-link payment lands and flips status to `booked`. This
        // matches LiveCart::iCalDataCheckInCheckOutCheckinCheckout — same convention,
        // single source of truth.
        $conflictExists = \DB::table('booking_requests')
            ->where('property_id', $input->property_id)
            ->whereIn('booking_status', ['booked','booking-confirmed','confirmed','reconciliation-needed','payment-captured-pms-failed'])
            ->where(function ($q) use ($checkIn, $checkOut) {
                // Overlap: existing.checkin < newCheckOut AND existing.checkout > newCheckIn
                $q->where('checkin', '<', $checkOut->toDateString())
                  ->where('checkout', '>', $checkIn->toDateString());
            })
            ->exists();

        // Blocking iCal events (Airbnb / VRBO / etc. external bookings sync'd in).
        $icalConflict = \DB::table('ical_events')
            ->where('event_pid', $input->property_id)
            ->where(function ($q) use ($checkIn, $checkOut) {
                $q->where('start_date', '<', $checkOut->toDateString())
                  ->where('end_date', '>', $checkIn->toDateString());
            })
            ->exists();

        // Manual single-day blocks set by admin in /admin/properties/{id}/calendar.
        // Stored on property_rates.single_date with is_available='0'. Any day in
        // [checkIn, checkOut) marked unavailable kills the quote. Without this the
        // picker would offer dates the host has explicitly closed.
        $manualBlock = \DB::table('property_rates')
            ->where('property_id', $input->property_id)
            ->whereIn('is_available', ['0', 'false', 'no', 0])
            ->where('single_date', '>=', $checkIn->toDateString())
            ->where('single_date', '<', $checkOut->toDateString())
            ->exists();

        if ($conflictExists || $icalConflict || $manualBlock) {
            $quote->valid = false;
            $quote->errors[] = 'Selected dates include unavailable nights';
            return $quote;
        }

        // Effective minimum stay = the most restrictive of the property-level setting and any
        // rate rule covering the CHECK-IN night. FIX (Jun 18 2026): previously only the
        // property-level min_stay was checked, so a min-stay set on a seasonal rate group or a
        // per-date row was silently ignored (a 1-night booking slipped past a 2-night minimum).
        $minStay = (int)($property->min_stay ?? 0);
        $ciDate  = $checkIn->toDateString();
        $ciPerDay = \DB::table('property_rates')
            ->where('property_id', $input->property_id)->where('single_date', $ciDate)
            ->orderByDesc('id')->first();
        if ($ciPerDay && (int)($ciPerDay->min_stay ?? 0) > $minStay) {
            $minStay = (int)$ciPerDay->min_stay;
        }
        $ciGroup = \DB::table('properties_rates_group')
            ->where('property_id', $input->property_id)
            ->where('start_date', '<=', $ciDate)->where('end_date', '>=', $ciDate)
            ->orderByDesc('id')->first();
        if ($ciGroup && (int)($ciGroup->min_stay ?? 0) > $minStay) {
            $minStay = (int)$ciGroup->min_stay;
        }
        if ($minStay > 0 && $quote->nights < $minStay) {
            $quote->valid = false;
            $quote->errors[] = "Minimum stay is {$minStay} nights";
            return $quote;
        }

        $maxStay = $property->max_stay !== null ? (int)$property->max_stay : null;
        if ($maxStay !== null && $quote->nights > $maxStay) {
            $quote->valid = false;
            $quote->errors[] = "Maximum stay is {$maxStay} nights";
            return $quote;
        }

        // Hard guest-capacity cap. `properties.sleeps` is the true maximum the
        // property accommodates; `no_of_guest` is the *base* count that the
        // standard rate covers, with `guest_fee` charging extras up to `sleeps`.
        // Reject submissions that exceed `sleeps` (or `no_of_guest` if `sleeps`
        // isn't set) so 999-adult bookings can't slip through.
        $totalRequested = (int) $input->adults + (int) $input->children;
        $hardCap = (int) ($property->sleeps ?? $property->no_of_guest ?? 0);
        if ($hardCap > 0 && $totalRequested > $hardCap) {
            $quote->valid = false;
            $quote->errors[] = "This property accommodates a maximum of {$hardCap} guest" . ($hardCap === 1 ? '' : 's') . " — you requested {$totalRequested}.";
            return $quote;
        }

        // Check-in / check-out day-of-week enforcement.
        // Both property-level and rate-row-level rules can constrain which weekday
        // a guest can check in or out. Day-of-week values stored as '0' (Sunday)
        // through '6' (Saturday); legacy Helper used the same convention.
        $dayNames = ['0' => 'Sunday', '1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday',
                     '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday'];
        $startDow = (string) $checkIn->dayOfWeek;
        // For a booking with check-out date X, the GUEST checks out on the morning of X
        // (last night = X - 1 day). The legacy code used the morning-of-checkout day-of-week.
        $endDow = (string) $checkOut->dayOfWeek;

        // Property-level rules
        if (isset($property->checkin_day) && in_array((string)$property->checkin_day, ['0','1','2','3','4','5','6'], true)) {
            if ((string)$property->checkin_day !== $startDow) {
                $quote->valid = false;
                $quote->errors[] = "Check-in for this property must be on " . $dayNames[(string)$property->checkin_day];
                return $quote;
            }
        }
        if (isset($property->checkout_day) && in_array((string)$property->checkout_day, ['0','1','2','3','4','5','6'], true)) {
            if ((string)$property->checkout_day !== $endDow) {
                $quote->valid = false;
                $quote->errors[] = "Check-out for this property must be on " . $dayNames[(string)$property->checkout_day];
                return $quote;
            }
        }

        // Rate-row-level rules — if a covering seasonal rule has check-in/out day set, it overrides
        $coveringGroup = \DB::table('properties_rates_group')
            ->where('property_id', $input->property_id)
            ->where('start_date', '<=', $checkIn->toDateString())
            ->where('end_date', '>=', $checkOut->copy()->subDay()->toDateString())
            ->orderByDesc('id')
            ->first();
        if ($coveringGroup) {
            if (isset($coveringGroup->checkin_day) && in_array((string)$coveringGroup->checkin_day, ['0','1','2','3','4','5','6'], true)) {
                if ((string)$coveringGroup->checkin_day !== $startDow) {
                    $quote->valid = false;
                    $quote->errors[] = "For these dates, check-in must be on " . $dayNames[(string)$coveringGroup->checkin_day];
                    return $quote;
                }
            }
            if (isset($coveringGroup->checkout_day) && in_array((string)$coveringGroup->checkout_day, ['0','1','2','3','4','5','6'], true)) {
                if ((string)$coveringGroup->checkout_day !== $endDow) {
                    $quote->valid = false;
                    $quote->errors[] = "For these dates, check-out must be on " . $dayNames[(string)$coveringGroup->checkout_day];
                    return $quote;
                }
            }
        }

        $nightly = $this->resolveNightlyRates($input->property_id, $checkIn, $checkOut, $property);
        $nightsTotal = (float)array_sum($nightly);

        // A9a — never produce a $0 booking. If the property has no rate rules and
        // standard_rate is 0/null (e.g. half-set-up or former-PMS listing), refuse
        // rather than quote a free stay.
        if ($nightsTotal <= 0) {
            $quote->valid = false;
            $quote->errors[] = 'Rates are not set for these dates yet — please contact us to book.';
            return $quote;
        }

        // Build the nights label. When every night is the same price keep the simple
        // "N nights × $X" form; when per-date/seasonal rates make nights differ, itemise by
        // rate (e.g. "3 nights (1 × $200.00 + 2 × $100.00)") so the averaged unit price isn't
        // shown as if it were the real per-night rate.
        $priceCounts = [];
        foreach ($nightly as $p) {
            $key = number_format((float) $p, 2, '.', '');
            $priceCounts[$key] = ($priceCounts[$key] ?? 0) + 1;
        }
        if (count($priceCounts) <= 1) {
            $nightsLabel = "{$quote->nights} nights × " . ($quote->nights > 0 ? '$' . number_format($nightsTotal / $quote->nights, 2) : '$0.00');
        } else {
            $segs = [];
            foreach ($priceCounts as $priceStr => $cnt) {
                $segs[] = $cnt . ' × $' . number_format((float) $priceStr, 2);
            }
            $nightsLabel = "{$quote->nights} nights (" . implode(' + ', $segs) . ')';
        }

        $quote->breakdown[] = [
            'label' => $nightsLabel,
            'amount' => $nightsTotal,
            'taxable' => true,    // resolved properly in tax step (Task 19)
            'refundable' => false,
            'category' => 'nights',
        ];
        $quote->subtotal_nights_only = round($nightsTotal, 2);

        // Extra guest fee
        $totalGuests = $input->adults + $input->children;
        $threshold = (int)($property->no_of_guest ?? 0);
        $guestFee = (float)($property->guest_fee ?? 0);
        if ($threshold > 0 && $totalGuests > $threshold && $guestFee > 0) {
            $extraGuests = $totalGuests - $threshold;
            $amount = $extraGuests * $guestFee * $quote->nights;
            $quote->breakdown[] = [
                'label' => "Extra guests: {$extraGuests} × \${$guestFee}/night × {$quote->nights} nights",
                'amount' => $amount,
                'taxable' => true,
                'refundable' => false,
                'category' => 'extra_guest_fee',
            ];
        }

        // Pet fee — single per-pet rate × interval-multiplier (Phase 3)
        if ($input->pets > 0) {
            $petRate = (float)($property->pet_fee ?? 0);
            if ($petRate > 0) {
                $interval = strtolower((string)($property->pet_fee_interval ?? 'one_time'));
                $petMultiplier = ($interval === 'per_day' || str_contains($interval, 'night')) ? $quote->nights : 1;
                $petAmount = $petRate * $input->pets * $petMultiplier;
                $quote->breakdown[] = [
                    'label' => "Pet fee ({$input->pets} × " . number_format($petRate, 2) . ($petMultiplier > 1 ? " × {$quote->nights} nights" : '') . ")",
                    'amount' => $petAmount,
                    'taxable' => (bool)($property->tax_applies_to_pet_fee ?? false),
                    'refundable' => false,
                    'category' => 'pet_fee',
                ];
            }
        }

        // Add-ons — generic fees from property_fees (Phase 3)
        // Mandatory fees are always charged regardless of addon selection so that
        // the property-detail widget's minimal price total still includes things
        // like Damage Insurance even before the guest reaches checkout.
        $selectedFeeIds = is_array($input->addons->fees ?? null) ? $input->addons->fees : [];
        $mandatoryFeeIds = \App\Models\PropertyFee::active()
            ->where('property_id', $input->property_id)
            ->where('is_mandatory', true)
            ->pluck('id')
            ->all();
        $effectiveFeeIds = array_values(array_unique(array_merge($selectedFeeIds, $mandatoryFeeIds)));
        if (!empty($effectiveFeeIds)) {
            $fees = \App\Models\PropertyFee::active()
                ->where('property_id', $input->property_id)
                ->whereIn('id', $effectiveFeeIds)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($fees as $fee) {
                $multiplier = $fee->billing_type === 'per_day' ? $quote->nights : 1;
                $amount = (float)$fee->rate * $multiplier;
                $quote->breakdown[] = [
                    'label' => $fee->name . ($multiplier > 1 ? " × {$quote->nights} nights" : ''),
                    'amount' => $amount,
                    'taxable' => (bool)$fee->taxable,
                    'applies_discount' => (bool)$fee->applies_discount,
                    'refundable' => false,
                    'category' => 'addon_fee',
                ];
            }
        }

        // Cleaning fee (flat)
        $cleaningFee = (float)($property->cleaning_fee ?? 0);
        if ($cleaningFee > 0) {
            $quote->breakdown[] = [
                'label' => 'Cleaning fee',
                'amount' => $cleaningFee,
                'taxable' => true,
                'refundable' => false,
                'category' => 'cleaning_fee',
            ];
        }

        // Apply LOS discount
        $losPct = $this->findLOSDiscountPct($input->property_id, $checkIn, $checkOut, $quote->nights);
        if ($losPct > 0) {
            $discountBase = $this->resolveDiscountBase($property);
            $discountableSum = 0.0;
            foreach ($quote->breakdown as $line) {
                // Per-fee `applies_discount` flag wins for addon_fee lines (Phase 3).
                // Other categories use the property-level / global resolveDiscountBase lookup.
                $applies = array_key_exists($line['category'], $discountBase)
                    ? $discountBase[$line['category']]
                    : (bool)($line['applies_discount'] ?? false);
                if ($applies) {
                    $discountableSum += $line['amount'];
                }
            }
            $discountAmt = round($discountableSum * $losPct / 100, 2);
            if ($discountAmt > 0) {
                $isMonthly = $quote->nights >= 28;
                $label = $isMonthly ? 'Monthly stay discount' : 'Weekly stay discount';
                $quote->breakdown[] = [
                    'label' => $label . " ({$losPct}%)",
                    'amount' => -1 * $discountAmt,
                    'taxable' => false,    // discount itself is not taxable; tax computed against original taxable amounts minus this share later
                    'refundable' => false,
                    'category' => 'los_discount',
                ];
                $quote->los_discount = [
                    'label'   => $label,
                    'percent' => (int) $losPct,
                    'amount'  => $discountAmt,    // positive amount; visitor display renders the minus sign
                ];
            }
        }

        // Subtotal — sum of all breakdown lines (including LOS which is negative)
        $quote->subtotal = round((float)array_sum(array_column($quote->breakdown, 'amount')), 2);

        // Coupon application
        if ($input->coupon_code !== null && $input->coupon_code !== '') {
            $coupon = \DB::table('coupons')->where('code', $input->coupon_code)->first();
            if (!$coupon) {
                $quote->errors[] = 'Coupon code not found';
            } else {
                $today = \Carbon\Carbon::today();
                $valid = true;
                $reason = null;

                if ($coupon->property_id !== null && (int)$coupon->property_id !== $input->property_id) {
                    $valid = false; $reason = 'Coupon not valid for this property';
                } elseif ($coupon->start_date && $today->lt(\Carbon\Carbon::parse($coupon->start_date))) {
                    $valid = false; $reason = 'Coupon not yet active';
                } elseif ($coupon->end_date && $today->gt(\Carbon\Carbon::parse($coupon->end_date))) {
                    $valid = false; $reason = 'Coupon expired';
                } elseif ($coupon->min_stay && $quote->nights < (int)$coupon->min_stay) {
                    $valid = false; $reason = "Coupon requires minimum stay of {$coupon->min_stay} nights";
                } elseif ($coupon->min_order_amount !== null && $quote->subtotal < (float)$coupon->min_order_amount) {
                    $valid = false; $reason = "Coupon requires minimum order of \${$coupon->min_order_amount}";
                } elseif ($coupon->max_uses !== null) {
                    $uses = \DB::table('booking_requests')
                        ->where('discount_coupon', $coupon->code)
                        ->where('booking_status', '!=', 'cancelled')
                        ->count();
                    if ($uses >= (int)$coupon->max_uses) {
                        $valid = false; $reason = 'Coupon redemption limit reached';
                    }
                }

                if ($valid && $coupon->max_uses_per_customer !== null && $input->customer_email) {
                    $perCust = \DB::table('booking_requests')
                        ->where('discount_coupon', $coupon->code)
                        ->where('email', $input->customer_email)
                        ->where('booking_status', '!=', 'cancelled')
                        ->count();
                    if ($perCust >= (int)$coupon->max_uses_per_customer) {
                        $valid = false; $reason = 'Coupon already used by this customer';
                    }
                }

                if ($valid) {
                    $type = strtolower((string)$coupon->type);
                    // FIX (Jun 18 2026): admin saves percentage coupons as type "per" (option label "%"),
                    // fixed-amount as "excat". The old check `=== 'percent'` never matched "per", so every
                    // percentage coupon was wrongly applied as a flat amount. Recognise all percent spellings.
                    $isPercent = in_array($type, ['per', 'percent', 'percentage', '%'], true);
                    $rawAmount = (float)$coupon->amount;
                    $discountAmount = $isPercent
                        ? round($quote->subtotal * $rawAmount / 100, 2)
                        : $rawAmount;
                    $couponLabel = "Coupon {$coupon->code}" . ($isPercent ? " (-{$rawAmount}%)" : " (-\${$rawAmount})");
                    $quote->discount = new Discount(
                        label: $couponLabel,
                        amount: -1 * $discountAmount,
                    );
                    $quote->coupon_discount = [
                        'code'   => (string) $coupon->code,
                        'amount' => $discountAmount,
                        'label'  => $couponLabel,
                    ];
                    $quote->subtotal = round($quote->subtotal - $discountAmount, 2);
                } else {
                    $quote->errors[] = $reason;
                }
            }
        }

        // Tax — applied only to taxable categories
        $taxRate = (float)($property->tax ?? 0);
        if ($taxRate > 0) {
            $taxBase = $this->resolveTaxBase($property);
            $taxableSum = 0.0;
            foreach ($quote->breakdown as $line) {
                if ($line['category'] === 'los_discount') continue;
                // Per-line `taxable` flag wins for addon_fee lines (Phase 3 — set from
                // property_fees.taxable). Other categories use the property/global tax base lookup.
                $applies = array_key_exists($line['category'], $taxBase)
                    ? $taxBase[$line['category']]
                    : (bool)($line['taxable'] ?? false);
                if ($applies) {
                    $taxableSum += $line['amount'];
                }
            }
            // If LOS discount applied, prorate it across the taxable subset proportionally
            $losLine = collect($quote->breakdown)->firstWhere('category', 'los_discount');
            if ($losLine) {
                $totalDiscountable = collect($quote->breakdown)
                    ->filter(fn($l) => $l['category'] !== 'los_discount')
                    ->sum('amount');
                if ($totalDiscountable > 0) {
                    $taxableSum += $losLine['amount'] * ($taxableSum / $totalDiscountable);
                }
            }
            $quote->tax = round($taxableSum * $taxRate / 100, 2);
        }

        // Refundable security deposit (line item, refundable, NEVER taxable).
        // Only charged when the owner has explicitly opted in via
        // `security_deposit_charge_at_booking`; otherwise it's informational
        // only and surfaced in agreements/quotes without hitting the total.
        if ((bool)($property->security_deposit_charge_at_booking ?? false)) {
            $deposit = (float)($property->security_deposit_amount ?? 0);
            if ($deposit > 0) {
                $quote->breakdown[] = [
                    'label' => 'Refundable security deposit',
                    'amount' => $deposit,
                    'taxable' => false,
                    'refundable' => true,
                    'category' => 'damage_deposit',
                ];
                $quote->damage_deposit = $deposit;
            }
        }

        // Total
        $quote->total = round($quote->subtotal + $quote->tax + $quote->damage_deposit, 2);

        // Installment schedule
        $cfg = $this->resolveInstallmentConfig($property);
        $daysUntilCheckin = (int)$today->diffInDays($checkIn);

        $count = 1;
        if ($cfg['plan'] === 'three' && $daysUntilCheckin >= $cfg['i3_min_lead']) {
            $count = 3;
        } elseif (in_array($cfg['plan'], ['two', 'three'], true) && $daysUntilCheckin >= $cfg['i2_min_lead']) {
            $count = 2;
        }

        if ($count === 1) {
            $quote->installments = [['due_date' => $today->toDateString(), 'amount' => $quote->total, 'sequence' => 1]];
        } elseif ($count === 2) {
            $i2 = round($quote->total * $cfg['i2_pct'] / 100, 2);
            $i1 = round($quote->total - $i2, 2);
            $quote->installments = [
                ['due_date' => $today->toDateString(), 'amount' => $i1, 'sequence' => 1],
                ['due_date' => $today->copy()->addDays($cfg['i2_days'])->toDateString(), 'amount' => $i2, 'sequence' => 2],
            ];
        } else {
            $i2 = round($quote->total * $cfg['i2_pct'] / 100, 2);
            $i3 = round($quote->total * $cfg['i3_pct'] / 100, 2);
            $i1 = round($quote->total - $i2 - $i3, 2);
            $quote->installments = [
                ['due_date' => $today->toDateString(), 'amount' => $i1, 'sequence' => 1],
                ['due_date' => $today->copy()->addDays($cfg['i2_days'])->toDateString(), 'amount' => $i2, 'sequence' => 2],
                ['due_date' => $today->copy()->addDays($cfg['i2_days'] + $cfg['i3_days'])->toDateString(), 'amount' => $i3, 'sequence' => 3],
            ];
        }

        return $quote;
    }

    /**
     * Resolve a price per night for [checkIn, checkOut), applying D7 precedence:
     * 1. Single-day override row in properties_rates_group (start_date == end_date == night)
     * 2. Date-range rule in properties_rates_group with per-weekday price for that day
     * 3. Date-range rule in properties_rates_group with single price
     * 4. Per-day row in property_rates for that exact date
     * 5. properties.price (base)
     *
     * @return array<string, float>  date YYYY-MM-DD => price
     */
    private function resolveNightlyRates(int $propertyId, \Carbon\Carbon $checkIn, \Carbon\Carbon $checkOut, $property): array
    {
        $rates = [];
        $weekdayCols = [
            0 => 'sunday_price',
            1 => 'monday_price',
            2 => 'tuesday_price',
            3 => 'wednesday_price',
            4 => 'thrusday_price',  // schema typo preserved
            5 => 'friday_price',
            6 => 'saturday_price',
        ];

        $cursor = $checkIn->copy();
        while ($cursor->lt($checkOut)) {
            $dateStr = $cursor->toDateString();
            $price = null;

            // Layer 0 (HIGHEST precedence) — admin per-date override.
            // FIX (Jun 18 2026): the Calendar & rates tab saves single-date prices to property_rates
            // (bulkUpdateDays), and the admin calendar display (days()) applies that per-date price
            // LAST, so it WINS over range/seasonal rules. The quote previously placed property_rates at
            // the BOTTOM (Layer 4, below range rules), so a host who defined a price for specific days
            // saw it in the calendar but the booking charged the range/standard rate. Match the admin
            // display: an explicit per-date price overrides every group/range rule.
            $perDay = \DB::table('property_rates')
                ->where('property_id', $propertyId)
                ->where('single_date', $dateStr)
                ->orderByDesc('id')
                ->first();
            if ($perDay && $perDay->price !== null && $perDay->price !== '') {
                $price = (float)$perDay->price;
            }

            // Layer 1: single-day group override
            if ($price === null) {
                $singleDay = \DB::table('properties_rates_group')
                    ->where('property_id', $propertyId)
                    ->where('start_date', $dateStr)
                    ->where('end_date', $dateStr)
                    ->orderByDesc('id')
                    ->first();
                if ($singleDay && $singleDay->price !== null) {
                    $price = (float)$singleDay->price;
                }
            }

            // Layer 2: date-range (multi-day) with per-weekday
            if ($price === null) {
                $rangeRule = \DB::table('properties_rates_group')
                    ->where('property_id', $propertyId)
                    ->where('start_date', '<=', $dateStr)
                    ->where('end_date', '>=', $dateStr)
                    ->whereColumn('start_date', '!=', 'end_date')  // exclude single-day overrides (handled by Layer 1)
                    ->orderByDesc('id')
                    ->first();
                if ($rangeRule) {
                    $weekdayCol = $weekdayCols[$cursor->dayOfWeek];
                    if ($rangeRule->{$weekdayCol} !== null && (float)$rangeRule->{$weekdayCol} > 0) {
                        $price = (float)$rangeRule->{$weekdayCol};
                    }
                    // Layer 3: date-range single price
                    if ($price === null && $rangeRule->price !== null) {
                        $price = (float)$rangeRule->price;
                    }
                }
            }

            // Layer 4.5: weekend uplift — applies when no explicit rule covered the day.
            if ($price === null && !empty($property->weekend_rate) && !empty($property->weekend_days)) {
                $weekendDays = array_filter(array_map('trim', explode(',', $property->weekend_days)), 'strlen');
                if (in_array((string) $cursor->dayOfWeek, $weekendDays, true)) {
                    $price = (float) $property->weekend_rate;
                }
            }

            // Layer 5: property base price (the fallback when no rate rule covers the night)
            if ($price === null) {
                $price = (float)($property->standard_rate ?? 0);
            }

            $rates[$dateStr] = $price;
            $cursor->addDay();
        }

        return $rates;
    }

    /**
     * Resolve which line-item categories are in the discount base
     * (per-property override wins; null = use global from basic_settings).
     *
     * @return array<string, bool>  category => is-discountable
     */
    private function resolveDiscountBase($property): array
    {
        // Phase 3: typed addon categories (hottub_addon, firewood_addon, rv_hookup_addon, extra_visitor_addon)
        // were removed. The new generic 'addon_fee' category respects per-fee taxable + applies_discount
        // flags directly on the breakdown line — see the array_key_exists fallback in the main calculate() loop.
        $categories = ['nights', 'cleaning_fee', 'pet_fee', 'extra_guest_fee'];
        $base = [];
        foreach ($categories as $cat) {
            $col = "discount_applies_to_{$cat}";
            if ($property->{$col} !== null) {
                $base[$cat] = (bool)$property->{$col};
            } else {
                $global = \DB::table('basic_settings')->where('name', $col)->value('value');
                $base[$cat] = (bool)$global;
            }
        }
        return $base;
    }

    /**
     * Resolve tax base (per-property override → global). Mirror of resolveDiscountBase.
     * @return array<string, bool>
     */
    private function resolveTaxBase($property): array
    {
        // Phase 3: typed addon categories (hottub_addon, firewood_addon, rv_hookup_addon, extra_visitor_addon)
        // were removed. The new generic 'addon_fee' category respects per-fee taxable + applies_discount
        // flags directly on the breakdown line — see the array_key_exists fallback in the main calculate() loop.
        $categories = ['nights', 'cleaning_fee', 'pet_fee', 'extra_guest_fee'];
        $base = [];
        foreach ($categories as $cat) {
            $col = "tax_applies_to_{$cat}";
            if ($property->{$col} !== null) {
                $base[$cat] = (bool)$property->{$col};
            } else {
                $global = \DB::table('basic_settings')->where('name', $col)->value('value');
                $base[$cat] = (bool)$global;
            }
        }
        return $base;
    }

    /**
     * Resolve the installment config (per-property override wins; null = global).
     * @return array{plan: string, i2_days: int, i2_pct: int, i2_min_lead: int, i3_days: int, i3_pct: int, i3_min_lead: int}
     */
    private function resolveInstallmentConfig($property): array
    {
        $keys = ['installment_plan', 'installment_2_days', 'installment_2_percent', 'installment_2_min_lead_days',
                 'installment_3_days', 'installment_3_percent', 'installment_3_min_lead_days'];
        $cfg = [];
        if ($property->installment_plan !== null) {
            // Use per-property values entirely
            $cfg['plan'] = $property->installment_plan;
            $cfg['i2_days'] = (int)($property->installment_2_days ?? 0);
            $cfg['i2_pct'] = (int)($property->installment_2_percent ?? 0);
            $cfg['i2_min_lead'] = (int)($property->installment_2_min_lead_days ?? 0);
            $cfg['i3_days'] = (int)($property->installment_3_days ?? 0);
            $cfg['i3_pct'] = (int)($property->installment_3_percent ?? 0);
            $cfg['i3_min_lead'] = (int)($property->installment_3_min_lead_days ?? 0);
        } else {
            // Pull from globals
            $globals = \DB::table('basic_settings')->whereIn('name', $keys)->pluck('value', 'name')->toArray();
            $cfg['plan'] = $globals['installment_plan'] ?? 'full';
            $cfg['i2_days'] = (int)($globals['installment_2_days'] ?? 0);
            $cfg['i2_pct'] = (int)($globals['installment_2_percent'] ?? 0);
            $cfg['i2_min_lead'] = (int)($globals['installment_2_min_lead_days'] ?? 999999);
            $cfg['i3_days'] = (int)($globals['installment_3_days'] ?? 0);
            $cfg['i3_pct'] = (int)($globals['installment_3_percent'] ?? 0);
            $cfg['i3_min_lead'] = (int)($globals['installment_3_min_lead_days'] ?? 999999);
        }
        return $cfg;
    }

    /**
     * Find the LOS discount % to apply. Returns 0 if not applicable.
     * Looks at any rate-row covering the date range; first non-null discount wins.
     */
    private function findLOSDiscountPct(int $propertyId, \Carbon\Carbon $checkIn, \Carbon\Carbon $checkOut, int $nights): float
    {
        if ($nights < 7) return 0.0;

        $col = $nights >= 28 ? 'discount_monthly' : 'discount_weekly';

        // Try rate group first (date-range), then per-day rows
        $fromGroup = \DB::table('properties_rates_group')
            ->where('property_id', $propertyId)
            ->where('start_date', '<=', $checkOut->toDateString())
            ->where('end_date', '>=', $checkIn->toDateString())
            ->whereNotNull($col)
            ->orderByDesc('id')
            ->value($col);
        if ($fromGroup !== null && (float)$fromGroup > 0) {
            return (float)$fromGroup;
        }

        $fromDaily = \DB::table('property_rates')
            ->where('property_id', $propertyId)
            ->whereBetween('single_date', [$checkIn->toDateString(), $checkOut->copy()->subDay()->toDateString()])
            ->whereNotNull($col)
            ->orderByDesc('id')
            ->value($col);
        if ($fromDaily !== null && (float)$fromDaily > 0) {
            return (float)$fromDaily;
        }

        return 0.0;
    }
}
