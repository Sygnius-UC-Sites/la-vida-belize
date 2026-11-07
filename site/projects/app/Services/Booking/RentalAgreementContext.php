<?php

namespace App\Services\Booking;

use App\Models\BookingRequest;
use Illuminate\Database\Eloquent\Model;
use ModelHelper;

final class RentalAgreementContext
{
    public function __construct(
        public readonly string $guest_name,
        public readonly string $guest_email,
        public readonly ?string $guest_phone,
        public readonly string $property_name,
        public readonly ?string $property_address,
        public readonly ?string $owner_name,
        public readonly ?string $owner_company,
        public readonly int $booking_id,
        public readonly string $check_in,
        public readonly string $check_out,
        public readonly int $nights,
        public readonly int $adults,
        public readonly int $children,
        public readonly int $pets,
        public readonly float $total_amount,
        public readonly float $damage_deposit,
        public readonly string $currency,
        public readonly string $template_html,
        public readonly string $house_rules_html,
        public readonly string $signature_data_url,
        public readonly string $signed_at_iso,
        public readonly string $signed_ip,
        public readonly string $signed_user_agent,
        public readonly string $signed_consent_text,
    ) {}

    public static function fromBooking(BookingRequest $b, Model $p, array $signing): self
    {
        $checkIn  = (string) $b->checkin;
        $checkOut = (string) $b->checkout;
        $nights   = max(1, (int) abs(\Carbon\Carbon::parse($checkOut)->diffInDays(\Carbon\Carbon::parse($checkIn))));

        return new self(
            guest_name:           (string) ($b->name ?? ''),
            guest_email:          (string) ($b->email ?? ''),
            guest_phone:          $b->mobile,
            property_name:        (string) ($p->name ?? ($p->title ?? ('Property #' . $p->id))),
            property_address:     $p->address ?? null,
            owner_name:           ModelHelper::getDataFromSetting('owner_name') ?: null,
            owner_company:        ModelHelper::getDataFromSetting('website') ?: null,
            booking_id:           (int) $b->id,
            check_in:             $checkIn,
            check_out:            $checkOut,
            nights:               (int) ($b->total_night ?? $nights),
            adults:               (int) ($b->adults ?? 0),
            children:             (int) ($b->child ?? 0),
            pets:                 (int) ($b->total_pets ?? 0),
            total_amount:         (float) ($b->total_amount ?? ($b->gross_amount ?? 0)),
            damage_deposit:       0.0,
            currency:             (string) (ModelHelper::getDataFromSetting('payment_currency') ?: 'USD'),
            template_html:        (string) $signing['template_html'],
            house_rules_html:     (string) ($signing['house_rules_html'] ?? ''),
            signature_data_url:   (string) $signing['signature_data_url'],
            signed_at_iso:        (string) $signing['signed_at_iso'],
            signed_ip:            (string) $signing['signed_ip'],
            signed_user_agent:    (string) $signing['signed_user_agent'],
            signed_consent_text:  (string) $signing['signed_consent_text'],
        );
    }

    /**
     * Phase 5 — legal-hardening helpers. These compute fields the signed
     * PDF surfaces in its audit footer so the document is defensible.
     */

    /**
     * Stable SHA-256 of the exact text the guest signed. Includes the
     * agreement body, house rules, and signing timestamp — anything tampered
     * with after the fact yields a different hash. Truncated to 16 chars in
     * the printed footer for readability; full hash stored in the audit
     * block. Format: hex.
     */
    public function documentHash(): string
    {
        return hash('sha256',
            $this->template_html . '||' .
            $this->house_rules_html . '||' .
            $this->signed_at_iso . '||' .
            $this->booking_id . '||' .
            $this->signed_consent_text
        );
    }

    /** Short human-readable document identifier — RA-{booking}-{YmdHis}. */
    public function documentId(): string
    {
        $stamp = $this->signed_at_iso
            ? \Carbon\Carbon::parse($this->signed_at_iso)->format('YmdHis')
            : date('YmdHis');
        return sprintf('RA-%d-%s', $this->booking_id, $stamp);
    }

    /** Display-friendly version of signed_at_iso (UTC + local-style). */
    public function signedAtDisplay(): string
    {
        if (!$this->signed_at_iso) return '';
        $c = \Carbon\Carbon::parse($this->signed_at_iso);
        return $c->utc()->format('M j, Y · H:i:s') . ' UTC  (' . $c->utc()->toIso8601String() . ')';
    }

    /** Renderer-side: when the PDF was produced. Differs from signed_at on
     *  re-prints; usually identical for first generation. */
    public function generatedAtDisplay(): string
    {
        return now()->utc()->format('M j, Y · H:i:s') . ' UTC';
    }

    /** Brand contact block for the PDF footer + signature page. Pulls from
     *  BasicSettings — same source the email-shell footer uses, so the
     *  document and outgoing emails match. */
    public function brandContact(): array
    {
        return [
            'brand'   => (string) (ModelHelper::getDataFromSetting('mailer_admin_name')
                                  ?: ModelHelper::getDataFromSetting('mail_from_name')
                                  ?: $this->owner_company
                                  ?: 'Operator'),
            'email'   => (string) (ModelHelper::getDataFromSetting('email')
                                  ?: ModelHelper::getDataFromSetting('contact_us_receiving_mail') ?: ''),
            'phone'   => (string) (ModelHelper::getDataFromSetting('mobile') ?: ''),
            'address' => (string) (ModelHelper::getDataFromSetting('address') ?: ''),
        ];
    }

    public function applyMergeFields(string $html): string
    {
        $tokens = [
            '{{guest_name}}'      => e($this->guest_name),
            '{{guest_email}}'     => e($this->guest_email),
            '{{guest_phone}}'     => e($this->guest_phone ?? ''),
            '{{property_name}}'   => e($this->property_name),
            '{{property_address}}' => e($this->property_address ?? ''),
            '{{check_in}}'        => e($this->check_in),
            '{{check_out}}'       => e($this->check_out),
            '{{nights}}'          => (string) $this->nights,
            '{{adults}}'          => (string) $this->adults,
            '{{children}}'        => (string) $this->children,
            '{{pets}}'            => (string) $this->pets,
            '{{total_amount}}'    => number_format($this->total_amount, 2),
            '{{damage_deposit}}'  => number_format($this->damage_deposit, 2),
            '{{currency}}'        => e($this->currency),
            '{{owner_name}}'      => e($this->owner_name ?? ''),
            '{{owner_company}}'   => e($this->owner_company ?? ''),
            '{{house_rules_html}}' => $this->house_rules_html, // intentional: HTML, not escaped
        ];

        return strtr($html, $tokens);
    }
}
