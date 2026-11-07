<?php

namespace App\Helper;

/**
 * StripeHelper — shared utilities for Stripe API calls.
 */
class StripeHelper
{
    /**
     * Currencies where Stripe charges and PaymentIntents carry amounts in whole
     * major units — no ×100 to encode, no ÷100 to decode.
     *
     * IMPORTANT — do not add ISK or UGX here:
     *   ISK  Stripe represents 5 ISK as 500 (minor-unit convention for charges).
     *        The zero-decimal rule applies to payouts only, not to charge amounts.
     *   UGX  Same compatibility rule — Stripe charges use minor units (500 = 5 UGX).
     *   HUF  Divide by 100 for charges. Zero-decimal rule is payout-only.
     *   TWD  Same as HUF.
     *
     * The list below is exactly the currencies Stripe treats as zero-decimal for
     * charge amounts (amounts ARE the major-unit value, no conversion needed):
     *
     * @see https://stripe.com/docs/currencies#zero-decimal
     */
    private const ZERO_DECIMAL_CURRENCIES = [
        'BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW',
        'MGA', 'PYG', 'RWF', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
    ];

    /**
     * Capture a security deposit hold — supports both legacy Charges (ch_xxx)
     * and new PaymentIntents with capture_method=manual (pi_xxx).
     */
    public static function captureDepositHold(string $depositId): void
    {
        if (str_starts_with($depositId, 'pi_')) {
            \Stripe\PaymentIntent::retrieve($depositId)->capture();
        } else {
            \Stripe\Charge::retrieve($depositId)->capture();
        }
    }

    /**
     * Release (void) a security deposit hold — supports both legacy Charges
     * and new PaymentIntents.
     */
    public static function releaseDepositHold(string $depositId): void
    {
        if (str_starts_with($depositId, 'pi_')) {
            // PI with capture_method=manual: cancel voids the hold
            \Stripe\PaymentIntent::retrieve($depositId)->cancel();
        } else {
            // Legacy Charge: refunding an uncaptured charge releases the hold
            \Stripe\Refund::create(['charge' => $depositId]);
        }
    }

    /**
     * Convert a major-unit decimal amount to Stripe's integer minor-unit amount.
     *
     *   USD 10.50  →  1050
     *   JPY 1000   →  1000  (zero-decimal: no conversion)
     *
     * @param  float  $amount    Amount in major currency units
     * @param  string $currency  ISO 4217 code
     * @return int               Stripe minor-unit integer
     */
    public static function toStripeAmount(float $amount, string $currency): int
    {
        $currency = strtoupper(trim($currency));
        return in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)
            ? (int) round($amount)
            : (int) round($amount * 100);
    }

    /**
     * Convert a Stripe minor-unit integer back to a major-unit decimal.
     * This is the inverse of toStripeAmount and must be used wherever a
     * PaymentIntent or Charge amount is converted for storage or PMS recording.
     *
     * NEVER use a raw "/ 100" — it produces the wrong result for JPY, ISK, etc.
     *
     *   USD: 1050  →  10.50
     *   JPY: 1000  →  1000.0  (zero-decimal: no division)
     *   ISK: 500   →  500.0   (zero-decimal)
     *
     * @param  int    $stripeAmount  Amount in Stripe's minor unit (from PaymentIntent/Charge)
     * @param  string $currency      ISO 4217 code (from PaymentIntent->currency or Charge->currency)
     * @return float                 Amount in major currency units, suitable for DB storage and PMS APIs
     */
    public static function toMajorUnits(int $stripeAmount, string $currency): float
    {
        $currency = strtoupper(trim($currency));
        return in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)
            ? (float) $stripeAmount
            : round($stripeAmount / 100, 2);
    }

    /**
     * Currency-aware price comparison tolerance (fix #7).
     *
     * One minor unit of the currency — the smallest meaningful rounding difference.
     * Never use a fixed $0.50 flat tolerance (too wide for USD, wrong direction for JPY).
     *
     *   USD, EUR, GBP, CAD …  →  0.01  (1 cent)
     *   JPY, KRW, VND, BIF …  →  1.0   (1 yen — no decimal places)
     *
     * Usage:
     *   if (abs($old - $new) > StripeHelper::priceTolerance($currency)) { ... }
     */
    public static function priceTolerance(string $currency): float
    {
        $currency = strtoupper(trim($currency));
        return in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true) ? 1.0 : 0.01;
    }
}
