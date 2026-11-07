<?php

namespace Tests\Unit\Helpers;

use App\Helper\CanonicalFee;
use App\Helper\FeeValidator;
use Tests\TestCase;

/**
 * Regression: Hostfully optional-fee overrides must match on the NUMERIC feeId.
 *
 * Verified live (2026-06-15): a Hostfully quote override keyed only by the UUID
 * `feeUid` is silently ignored (total unchanged), while an override carrying the
 * numeric `feeId` applies the fee (+$185 pet fee). Before this fix the adapter
 * sent `feeUid` only, so selecting an optional fee on a Hostfully property did
 * nothing — the guest was under-charged and the PMS reservation lost the fee.
 */
class PmsFeeOverrideTest extends TestCase
{
    /** CanonicalFee::fromHostfully must preserve the numeric feeId as fee_id. */
    public function test_canonical_hostfully_carries_numeric_fee_id(): void
    {
        $cf = CanonicalFee::fromHostfully([
            'uid'        => '2268c36c-a642-4cbe-9dad-09f47654142b',
            'feeId'      => 664795,
            'name'       => 'Pet fee',
            'type'       => 'PER_STAY',
            'netPrice'   => 185,
            'isOptional' => true,
            'isActive'   => true,
        ]);

        $this->assertNotNull($cf);
        $this->assertSame(664795, $cf['fee_id'], 'numeric feeId must be carried as fee_id');
        $this->assertSame('2268c36c-a642-4cbe-9dad-09f47654142b', $cf['pms_id'], 'pms_id keeps the UUID');
    }

    /** buildHostfullyOverrides must emit the numeric feeId (UUID alone is ignored by Hostfully). */
    public function test_hostfully_override_emits_fee_id(): void
    {
        $canonical = [[
            'pms_id'       => '2268c36c-a642-4cbe-9dad-09f47654142b',
            'fee_id'       => 664795,
            'name'         => 'Pet fee',
            'is_mandatory' => false,
            'is_optional'  => true,
            'is_active'    => true,
            'pms_source'   => 'hostfully',
        ]];

        $overrides = FeeValidator::buildHostfullyOverrides(
            ['hostfully_2268c36c-a642-4cbe-9dad-09f47654142b' => 1],
            $canonical
        );

        $this->assertCount(1, $overrides);
        $this->assertArrayHasKey('feeId', $overrides[0], 'override MUST carry numeric feeId');
        $this->assertSame(664795, $overrides[0]['feeId']);
        $this->assertTrue($overrides[0]['enabled']);
        $this->assertSame(1, $overrides[0]['quantity']);
    }

    /**
     * Optionality must be driven by the fee's isOptional flag (which the adapter
     * sources from a live quote — the client's actual config), NOT hardcoded.
     * A client-defined optional fee is selectable; everything else is mandatory.
     */
    public function test_canonical_hostfully_optionality_follows_is_optional_flag(): void
    {
        $base = ['uid' => 'u', 'feeId' => 1, 'name' => 'Fee', 'type' => 'PER_STAY', 'netPrice' => 50, 'isActive' => true];

        $optional = CanonicalFee::fromHostfully(['isOptional' => true] + $base);
        $this->assertTrue($optional['is_optional']);
        $this->assertFalse($optional['is_mandatory']);

        $mandatory = CanonicalFee::fromHostfully(['isOptional' => false] + $base);
        $this->assertFalse($mandatory['is_optional']);
        $this->assertTrue($mandatory['is_mandatory']);

        // Absent flag → mandatory (fail-closed: never auto-offer an unconfirmed fee).
        $absent = CanonicalFee::fromHostfully($base);
        $this->assertTrue($absent['is_mandatory']);
    }

    /** Falls back to pms_id when it is numeric (older synced data stored the int there). */
    public function test_hostfully_override_falls_back_to_numeric_pms_id(): void
    {
        $canonical = [[
            'pms_id'       => '664795',   // older sync: numeric id in pms_id, no fee_id
            'name'         => 'Pet fee',
            'is_mandatory' => false,
            'is_active'    => true,
            'pms_source'   => 'hostfully',
        ]];

        $overrides = FeeValidator::buildHostfullyOverrides(
            ['hostfully_664795' => 2],
            $canonical
        );

        $this->assertCount(1, $overrides);
        $this->assertSame(664795, $overrides[0]['feeId']);
        $this->assertSame(2, $overrides[0]['quantity']);
    }
}
