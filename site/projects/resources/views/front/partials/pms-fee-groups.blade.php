@php
    $feeGroupDefinitions = [
        'accommodation' => 'Accommodation',
        'cleaning'      => 'Cleaning',
        'fees'          => 'Additional fees',
        'taxes'         => 'Taxes',
        'discounts'     => 'Discounts',
        'deposits'      => 'Security deposit',
        'other'         => 'Other charges',
    ];
    $feeTypeGroups = [
        'accommodation' => 'accommodation',
        'cleaning'      => 'cleaning',
        'fee'           => 'fees',
        'tax'           => 'taxes',
        'discount'      => 'discounts',
        'deposit'       => 'deposits',
    ];
    $groupedFeeItems = array_fill_keys(array_keys($feeGroupDefinitions), []);
    $hasTypedTaxes = false;

    foreach (($feeItems ?? []) as $feeItem) {
        if (!is_array($feeItem)) {
            continue;
        }

        $feeType = strtolower(trim((string)($feeItem['type'] ?? '')));
        $groupKey = $feeTypeGroups[$feeType] ?? 'other';
        $hasTypedTaxes = $hasTypedTaxes || $feeType === 'tax';

        $feeLines = isset($feeItem['prices']) && is_array($feeItem['prices'])
            ? $feeItem['prices']
            : [[
                'description' => $feeItem['name'] ?? $feeItem['label'] ?? '',
                'amount'      => $feeItem['value'] ?? $feeItem['amount'] ?? 0,
            ]];

        foreach ($feeLines as $feeLine) {
            if (!is_array($feeLine)) {
                continue;
            }

            $feeAmount = (float)($feeLine['amount'] ?? $feeLine['value'] ?? 0);
            if ($feeAmount == 0.0) {
                continue;
            }

            $groupedFeeItems[$groupKey][] = [
                'label'  => $feeLine['description'] ?? $feeLine['name'] ?? $feeGroupDefinitions[$groupKey],
                'amount' => $feeAmount,
            ];
        }
    }

    if (!$hasTypedTaxes) {
        foreach (($fallbackTaxItems ?? []) as $taxItem) {
            if (!is_array($taxItem)) {
                continue;
            }

            $taxAmount = (float)($taxItem['amount'] ?? $taxItem['value'] ?? 0);
            if ($taxAmount == 0.0) {
                continue;
            }

            $groupedFeeItems['taxes'][] = [
                'label'  => $taxItem['label'] ?? $taxItem['name'] ?? 'Tax',
                'amount' => $taxAmount,
            ];
        }
    }

    $securityDepositAmount = (float)($securityDeposit ?? 0);
    if ($securityDepositAmount > 0 && empty($groupedFeeItems['deposits'])) {
        if ($depositInTotal ?? false) {
            $depositLabel = 'Refundable, included in total';
        } elseif ($depositIsPreauth ?? true) {
            $depositLabel = 'Refundable hold, not included in total';
        } else {
            $depositLabel = 'Refundable';
        }

        $groupedFeeItems['deposits'][] = [
            'label'  => $depositLabel,
            'amount' => $securityDepositAmount,
        ];
    }
@endphp

<div class="pms-fee-groups">
    @foreach($feeGroupDefinitions as $groupKey => $groupLabel)
        @php
            $groupItems = $groupedFeeItems[$groupKey];
            $groupTotal = array_sum(array_column($groupItems, 'amount'));
            $groupHint = count($groupItems) > 1
                ? count($groupItems) . ' items'
                : ($groupItems[0]['label'] ?? '');
        @endphp
        @if(count($groupItems))
            <details class="pms-fee-group">
                <summary>
                    <span class="pms-fee-group-label">
                        {{ $groupLabel }}
                        <small>{{ $groupHint }}</small>
                    </span>
                    <span class="pms-fee-group-total">{!! $currencySymbol ?? '' !!}{{ number_format($groupTotal, 2) }}</span>
                </summary>
                <div class="pms-fee-group-items">
                    @foreach($groupItems as $groupItem)
                        <div class="pms-fee-group-row">
                            <span>{{ $groupItem['label'] }}</span>
                            <span>{!! $currencySymbol ?? '' !!}{{ number_format($groupItem['amount'], 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    @endforeach
</div>
