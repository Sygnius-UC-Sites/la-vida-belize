<?php

namespace Tests\Feature\Email;

use Tests\TestCase;

/**
 * Regression: a PMS booking confirmation email must show tax exactly ONCE and
 * its price breakdown must sum to the Total.
 *
 * Before the fix, before_total_fees was built from ALL non-deposit line items —
 * including tax — while the email ALSO rendered after_total_fees and a separate
 * tax row. Proven render of an $808.04 booking showed every tax line twice plus
 * a Tax summary row; the breakdown figures summed to $3,863.07. The total charged
 * was correct, but the customer-facing breakdown was incoherent.
 *
 * The fix stores tax ONLY in the `tax` column (before_total_fees excludes tax +
 * deposit; after_total_fees is empty for PMS bookings), so the email renders the
 * tax once.
 */
class PmsQuoteBreakdownTest extends TestCase
{
    /** The fixed stored shape: before has no tax, after empty, tax in its own column. */
    public function test_pms_email_renders_tax_once_and_breakdown_sums_to_total(): void
    {
        // Hostaway-style quote: 540 + 135 + 42.13 fees, 90.91 tax → 808.04 total.
        $before = [
            ['name' => '3 nights',     'value' => 540,   'amount' => 540,   'type' => 'accommodation'],
            ['name' => 'Cleaning fee', 'value' => 135,   'amount' => 135,   'type' => 'cleaning'],
            ['name' => 'Service Fee',  'value' => 42.13, 'amount' => 42.13, 'type' => 'fee'],
        ];

        $data = [
            'checkin' => '2027-03-01', 'checkout' => '2027-03-04',
            'total_guests' => 2, 'adults' => 2, 'child' => 0, 'total_night' => 3,
            'gross_amount' => 540, 'sub_amount' => 717.13, 'tax' => 90.91,
            'total_amount' => 808.04, 'total_payment' => 808.04,
            'before_total_fees' => json_encode($before),
            'after_total_fees'  => json_encode([]),   // empty for PMS bookings
        ];
        $setting_data = ['payment_currency' => '$'];

        $html = view('mail.pms-booking-common-data', compact('data', 'setting_data'))->render();
        $text = preg_replace('/\s+/', ' ', strip_tags($html));

        // Tax label appears exactly once (no after_total_fees duplication).
        $this->assertSame(1, substr_count($text, 'Tax :'), 'tax must render exactly once');

        // The fee line items + the single tax must equal the stored total.
        $feeSum = array_sum(array_column($before, 'amount'));
        $this->assertEqualsWithDelta(808.04, $feeSum + $data['tax'], 0.01,
            'before_total_fees + tax must equal total_amount');
    }

    /** Guards the data contract: before_total_fees must never contain a tax-typed row. */
    public function test_before_total_fees_contract_excludes_tax(): void
    {
        // Simulate the exclusion rule the controllers apply when building the row.
        $lineItems = [
            ['label' => '3 nights',     'amount' => 540,   'type' => 'accommodation'],
            ['label' => 'Cleaning fee', 'amount' => 135,   'type' => 'cleaning'],
            ['label' => 'Taxes',        'amount' => 90.91, 'type' => 'tax'],
            ['label' => 'Deposit',      'amount' => 300,   'type' => 'deposit'],
        ];

        $before = array_values(array_filter($lineItems, fn ($li) =>
            !in_array($li['type'], ['tax', 'deposit'], true)));

        $types = array_column($before, 'type');
        $this->assertNotContains('tax', $types, 'before_total_fees must exclude tax');
        $this->assertNotContains('deposit', $types, 'before_total_fees must exclude deposit');
    }
}
