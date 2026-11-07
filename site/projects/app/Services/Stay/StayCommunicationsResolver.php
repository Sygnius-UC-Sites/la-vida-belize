<?php

namespace App\Services\Stay;

use App\Models\BookingRequest;
use Illuminate\Database\Eloquent\Model;
use ModelHelper;

final class StayCommunicationsResolver
{
    public function __construct(
        private readonly Model $property,
        private readonly BookingRequest $booking,
    ) {}

    public function resolve(string $propertyColumn, string $globalKey, bool $expandTokens = true): string
    {
        $value = $this->property->{$propertyColumn} ?: ModelHelper::getDataFromSetting($globalKey);
        $value = (string) ($value ?? '');
        return $expandTokens ? $this->expandMergeFields($value) : $value;
    }

    public function resolveSendDayOffset(string $propertyColumn, string $globalKey, int $fallback = 0): int
    {
        if ($this->property->{$propertyColumn} !== null && $this->property->{$propertyColumn} !== '') {
            return (int) $this->property->{$propertyColumn};
        }
        $global = ModelHelper::getDataFromSetting($globalKey);
        return $global !== null && $global !== '' ? (int) $global : $fallback;
    }

    public function resolveCheckinPayload(): array
    {
        return [
            'arrival_window'        => $this->resolve('checkin_arrival_window',      'checkin_default_arrival_window',      false),
            'door_code'             => $this->resolve('checkin_door_code',            'checkin_default_door_code',            false),
            'wifi_name'             => $this->resolve('checkin_wifi_name',            'checkin_default_wifi_name',            false),
            'wifi_password'         => $this->resolve('checkin_wifi_password',        'checkin_default_wifi_password',        false),
            'parking_instructions'  => $this->resolve('checkin_parking_instructions', 'checkin_default_parking_instructions', true),
            'host_name'             => $this->resolve('checkin_host_name',            'checkin_default_host_name',            false),
            'host_phone'            => $this->resolve('checkin_host_phone',           'checkin_default_host_phone',           false),
            'emergency_phone'       => $this->resolve('checkin_emergency_phone',      'checkin_default_emergency_phone',      false),
            'notes_html'            => $this->resolve('detail_check_in_instructions', 'checkin_default_notes_html',           true),
        ];
    }

    public function resolveCheckoutPayload(): array
    {
        return [
            'departure_time'      => $this->resolve('checkout_departure_time',      'checkout_default_departure_time',      false),
            'key_return'          => $this->resolve('checkout_key_return',          'checkout_default_key_return',          true),
            'cleaning_checklist'  => $this->resolve('checkout_cleaning_checklist',  'checkout_default_cleaning_checklist',  true),
            'notes_html'          => $this->resolve('checkout_notes',                'checkout_default_notes_html',          true),
        ];
    }

    public function resolveWelcomePayload(): array
    {
        $description = $this->resolve('welcome_package_description', 'welcome_default_description', true);
        $attachmentPath = $this->property->welcome_package_attachment
            ?: ModelHelper::getDataFromSetting('welcome_default_attachment_path');
        $legacy = $attachmentPath ? [public_path($attachmentPath)] : [];
        // Merge with admin-managed attachments uploaded via /admin/email-templates.
        // The legacy single-path mechanism stays for backward compat.
        $files = array_values(array_filter(array_merge($legacy, $this->attachmentsFor('welcome'))));
        return ['description_html' => $description, 'files' => $files];
    }

    /**
     * Absolute filesystem paths for every admin-uploaded attachment on a
     * given lifecycle email type. Suitable for passing as the `$files`
     * argument to MailHelper::emailSenderByController() or for a Mailable's
     * attach() loop. Type slug must match `email_attachments.email_type`
     * (welcome / checkin / checkout / review / etc.).
     */
    public function attachmentsFor(string $type): array
    {
        return array_column(\App\Models\EmailAttachment::pathsFor($type), 'path');
    }

    /**
     * Phase 13D — $extraTokens lets callers (cron, controller) inject per-send tokens
     * like {{magic_link}}, {{installment_number}}, {{installment_amount}},
     * {{installment_due_date}}, {{paid_amount}}, {{balance_due}}. Standard tokens
     * are always populated from booking + property + settings.
     */
    public function expandMergeFields(string $html, array $extraTokens = []): string
    {
        $hostName  = $this->property->checkin_host_name  ?: ModelHelper::getDataFromSetting('checkin_default_host_name');
        $hostPhone = $this->property->checkin_host_phone ?: ModelHelper::getDataFromSetting('checkin_default_host_phone');
        $emergency = $this->property->checkin_emergency_phone ?: ModelHelper::getDataFromSetting('checkin_default_emergency_phone');

        // Phase 13C — site/brand + booking-finance tokens for cancellation emails (and other future uses).
        $brand = ModelHelper::getDataFromSetting('mailer_admin_name')
              ?: ModelHelper::getDataFromSetting('mail_from_name')
              ?: ModelHelper::getDataFromSetting('owner_name')
              ?: '';
        $siteEmail   = ModelHelper::getDataFromSetting('email') ?: '';
        $sitePhone   = ModelHelper::getDataFromSetting('mobile') ?: '';
        $currency    = ModelHelper::getDataFromSetting('payment_currency') ?: '$';
        $cancelDate  = $this->booking->cancel_at ? date('F j, Y', strtotime((string)$this->booking->cancel_at)) : '';

        // Date formatting that's friendlier than YYYY-MM-DD on customer-facing emails.
        $checkinFmt  = $this->booking->checkin  ? date('l · M j, Y', strtotime((string) $this->booking->checkin))  : '';
        $checkoutFmt = $this->booking->checkout ? date('l · M j, Y', strtotime((string) $this->booking->checkout)) : '';

        // Guest counts — fall back to 0 when missing so we never emit a literal {{adults}}.
        $adults   = (int) ($this->booking->adults ?? 0);
        $children = (int) ($this->booking->child  ?? $this->booking->children ?? 0);
        $pets     = (int) ($this->booking->total_pets ?? $this->booking->pets ?? 0);
        $nights   = (int) ($this->booking->total_night ?? 1);

        // Public booking-status / pay link for the renter. Uses the existing
        // signed magic-link route when available, else a status fallback.
        $bookingUrl = $this->booking->id ? url('booking-request-received?booking_id=' . $this->booking->id) : '';

        // Review-submission URL: only valid when a Testimonial row + token exists for the booking.
        $reviewUrl = '';
        if ($this->booking->id) {
            $token = \DB::table('testimonials')->where('property_id', $this->property->id ?? 0)
                ->where('email', $this->booking->email)->orderByDesc('id')->value('submission_token');
            if ($token) $reviewUrl = url('review/' . $token);
        }

        $tokens = [
            '{{guest_name}}'      => e((string)$this->booking->name),
            '{{guest_email}}'     => e((string)$this->booking->email),
            // QA 2026-06-12: guest_phone + property_type were used by contact /
            // owner-alert templates but missing here — any template rendered
            // through the resolver leaked the raw token.
            '{{guest_phone}}'     => e((string)($this->booking->mobile ?? '')),
            '{{property_type}}'   => e((string)($this->property->type ?? '')),
            '{{property_name}}'   => e((string)($this->property->name ?? '')),
            '{{property_address}}' => e((string)($this->property->address ?? '')),
            '{{check_in}}'        => e($checkinFmt),
            '{{check_out}}'       => e($checkoutFmt),
            '{{nights}}'          => (string) $nights,
            '{{nights_plural}}'   => $nights === 1 ? '' : 's',
            '{{adults}}'          => (string) $adults,
            '{{children}}'        => (string) $children,
            '{{pets}}'            => (string) $pets,
            '{{host_name}}'       => e((string)($hostName ?? '')),
            '{{host_phone}}'      => e((string)($hostPhone ?? '')),
            '{{emergency_phone}}' => e((string)($emergency ?? '')),

            // Site / brand
            '{{brand}}'           => e($brand),
            '{{site_email}}'      => e($siteEmail),
            '{{site_phone}}'      => e($sitePhone),

            // Booking finance
            '{{booking_id}}'      => (string)($this->booking->id ?? ''),
            '{{cancel_date}}'     => e($cancelDate),
            '{{total_amount}}'    => $currency . number_format((float)($this->booking->total_amount ?? 0), 2),

            // Public links the renter clicks
            '{{booking_url}}'     => $bookingUrl,
            '{{review_url}}'      => $reviewUrl,

            // Payment-specific tokens — auto-pulled from the latest complete
            // Payment row on this booking. Receipt-stage emails use these.
            // The finalEmailAndUpdateBookingPayment helper still overrides
            // these via strtr() when it has the precise Payment in hand.
        ] + $this->paymentTokens($currency) + [

            // Phase 13E — admin deep-link to view THIS booking in the admin panel.
            // FIX (Jun 18 2026): was pointing at .../properties/{id} which does Property::find($bookingId)
            // → 404 (booking id is not a property id). Point at the booking's own edit/detail page instead.
            '{{admin_url}}'       => $this->booking->id ? url('admin/booking-enquiries/' . $this->booking->id . '/edit') : '',

            // Phase 13J — itemized invoice table built from the booking's fee JSON columns.
            '{{invoice_table}}'   => $this->buildInvoiceTable($currency),
        ];

        // Caller-supplied tokens default to BLANK so a guest never sees a raw
        // {{token}} if a caller forgets to pass one. The real caller (installment
        // charger, magic-link sender, rental-agreement flow) overrides these
        // via $extraTokens with the actual values.
        $tokens += [
            '{{magic_link}}'            => '',
            '{{installment_number}}'    => '',
            '{{installment_amount}}'    => '',
            '{{installment_due_date}}'  => '',
            '{{rental_agreement_link}}' => '',
            '{{paid_amount}}'           => '',
            '{{balance_due}}'           => '',
        ];

        // Phase 13D — caller-supplied tokens (magic_link, installment_*, paid_amount, balance_due).
        // Caller values win on key collision so callers can override the standard population.
        foreach ($extraTokens as $k => $v) {
            $tokens[$k] = (string) $v;
        }

        $out = strtr($html, $tokens);

        // Some older seeded templates wrote "${{total_amount}}" even though the
        // token already includes the configured currency symbol.
        if ($currency !== '') {
            $out = str_replace($currency . $currency, $currency, $out);
        }

        // Final safety net (QA 2026-06-12): strip any UNKNOWN {{token}} that
        // slipped through so guests never receive raw merge syntax. Logged so
        // a genuinely new token gets added above rather than silently eaten.
        if (preg_match_all('/\{\{\s*[a-z0-9_]+\s*\}\}/i', $out, $m)) {
            \Log::warning('Unmapped email merge tokens stripped', ['tokens' => array_unique($m[0])]);
            $out = preg_replace('/\{\{\s*[a-z0-9_]+\s*\}\}/i', '', $out);
        }

        return $out;
    }

    /**
     * Render an admin-managed body and guarantee that the frozen booking quote
     * is present. Templates can position {{invoice_table}} themselves; when
     * they do not, the quote is appended so confirmation/receipt emails never
     * silently lose the price the guest accepted.
     */
    public function expandMergeFieldsWithQuote(string $html, array $extraTokens = []): string
    {
        $positionsQuote = str_contains($html, '{{invoice_table}}');
        $rendered = $this->expandMergeFields($html, $extraTokens);

        if (!$positionsQuote) {
            $rendered .= $this->quoteTable();
        }

        return $rendered;
    }

    public function quoteTable(): string
    {
        $currency = ModelHelper::getDataFromSetting('payment_currency') ?: '$';
        return $this->buildInvoiceTable((string) $currency);
    }

    /**
     * Auto-populate payment-specific tokens from the latest complete payment
     * on this booking. Used by receipt templates so any caller — not just
     * finalEmailAndUpdateBookingPayment — gets sensible substitutions.
     *
     * @return array<string,string>
     */
    private function paymentTokens(string $currency): array
    {
        if (!$this->booking->id) {
            return [
                '{{payment_amount}}'      => '',
                '{{payment_date}}'        => '',
                '{{payment_method}}'      => '',
                '{{transaction_id}}'      => '',
                '{{receipt_url}}'         => '',
                '{{outstanding_balance}}' => '',
            ];
        }
        $latest = \DB::table('payments')
            ->where('booking_id', $this->booking->id)
            ->where('status', 'complete')
            ->orderByDesc('id')->first();

        $bookingTotal     = (float) ($this->booking->total_amount ?? 0);
        $paidSoFar        = (float) \DB::table('payments')->where('booking_id', $this->booking->id)
            ->where('status', 'complete')->sum('amount');
        $outstanding      = max(0, $bookingTotal - $paidSoFar);

        return [
            '{{payment_amount}}'      => $latest ? $currency . number_format((float) $latest->amount, 2) : '',
            '{{payment_date}}'        => $latest && $latest->created_at ? \Carbon\Carbon::parse($latest->created_at)->format('M j, Y · g:i A') : '',
            '{{payment_method}}'      => $latest ? strtoupper((string) $latest->type) : '',
            '{{transaction_id}}'      => $latest ? (string) $latest->tran_id : '',
            '{{receipt_url}}'         => $latest && $latest->receipt_url ? (string) $latest->receipt_url : '',
            '{{outstanding_balance}}' => $bookingTotal > 0
                ? ($outstanding > 0 ? $currency . number_format($outstanding, 2) . ' remaining' : 'Paid in full')
                : '',
        ];
    }

    /**
     * Phase 13J — build an HTML invoice table for the {{invoice_table}} merge token.
     * Pulls every fee/charge/discount on the booking and renders a clean, email-safe
     * table. Empty/null fields are skipped so the table only shows lines that exist.
     */
    private function buildInvoiceTable(string $currency): string
    {
        $b = $this->booking;
        $rows = [];
        $beforeFees = $this->safeJsonArray($b->before_total_fees ?? null);
        $hasTypedQuote = collect($beforeFees)->contains(
            fn ($item) => is_array($item) && trim((string) ($item['type'] ?? '')) !== ''
        );

        if ($hasTypedQuote) {
            // PMS bookings store the normalized quote lines in display order.
            // Use only that frozen snapshot: gross/tax fallback columns contain
            // the same amounts and would duplicate accommodation and tax.
            foreach ($beforeFees as $item) {
                $name = $item['name'] ?? $item['label'] ?? '';
                $amount = (float) ($item['amount'] ?? $item['value'] ?? 0);
                $type = strtolower((string) ($item['type'] ?? ''));
                if ($name === '' || $amount == 0.0 || $type === 'deposit') {
                    continue;
                }
                $isDiscount = $type === 'discount' || $amount < 0;
                $rows[] = [
                    'label' => e((string) $name),
                    'value' => ($isDiscount ? '&minus; ' : '') . $currency . number_format(abs($amount), 2),
                    'class' => $isDiscount ? 'discount' : '',
                ];
            }

            // Q2: a PMS quote snapshot (before_total_fees) usually holds only the
            // accommodation line(s); taxes/fees live in the booking total (Lodgify
            // keeps them in the `tax` column, not in before_total_fees). Reconcile so
            // the itemisation sums to the booking total — append a single "Taxes (&
            // fees)" line for the remainder, so the email matches the on-site quote
            // (which bit us: "4 nights … Total" with an unexplained gap).
            $listedSoFar = 0.0;
            foreach ($rows as $r) {
                $num = (float) preg_replace('/[^0-9.]/', '', (string) $r['value']);
                $listedSoFar += (strpos((string) $r['value'], '&minus;') !== false) ? -$num : $num;
            }
            $remainder = round((float) ($b->total_amount ?? 0) - $listedSoFar, 2);
            if ($remainder > 0.01) {
                $rows[] = [
                    'label' => 'Taxes (&amp; fees)',
                    'value' => $currency . number_format($remainder, 2),
                    'class' => '',
                ];
            }

            $securityDeposit = $this->securityDepositFromQuote();
            if ($securityDeposit['amount'] > 0) {
                $rows[] = [
                    'label' => $securityDeposit['label'],
                    'value' => $currency . number_format($securityDeposit['amount'], 2),
                    'class' => '',
                ];
            }
        } else {
            $nights = (int) ($b->total_night ?? 0);
            $gross  = (float) ($b->gross_amount ?? 0);
            if ($nights > 0 && $gross > 0) {
                $perNight = $gross / $nights;
                $rows[] = [
                    'label' => "Rental rate &mdash; {$nights} night" . ($nights == 1 ? '' : 's') . " &times; " . $currency . number_format($perNight, 2),
                    'value' => $currency . number_format($gross, 2),
                ];
            }

            foreach ($this->safeJsonArray($b->custom_before_total_fees ?? null) as $c) {
                $name = $c['product_name'] ?? '';
                $amt  = (float) ($c['product_amount'] ?? 0);
                if ($name === '' || $amt == 0) continue;
                $rows[] = ['label' => e((string) $name), 'value' => $currency . number_format($amt, 2)];
            }

            foreach ($beforeFees as $c) {
                $name = $c['name'] ?? ($c['product_name'] ?? '');
                $amt  = (float) ($c['amount'] ?? ($c['product_amount'] ?? 0));
                if ($name === '' || $amt == 0) continue;
                $rows[] = ['label' => e((string) $name), 'value' => $currency . number_format($amt, 2)];
            }

            $totalPets = (int) ($b->total_pets ?? 0);
            $petFee    = (float) ($b->pet_fee ?? 0);
            if ($totalPets > 0 && $petFee > 0) {
                $rows[] = ['label' => "Pet fee &times; {$totalPets}", 'value' => $currency . number_format($petFee, 2)];
            }

            $guestFee = (float) ($b->guest_fee ?? 0);
            if ($guestFee > 0) {
                $rows[] = ['label' => 'Extra guest fee', 'value' => $currency . number_format($guestFee, 2)];
            }

            $discount = (float) ($b->discount ?? 0);
            if ($discount > 0) {
                $coupon = $b->discount_coupon ?? null;
                $rows[] = [
                    'label' => 'Discount' . ($coupon ? ' (' . e((string) $coupon) . ')' : ''),
                    'value' => '&minus; ' . $currency . number_format($discount, 2),
                    'class' => 'discount',
                ];
            }

            $tax = (float) ($b->tax ?? 0);
            if ($tax > 0) {
                $defineTax = $b->define_tax ?? null;
                $rows[] = ['label' => 'Lodging tax' . ($defineTax ? " ({$defineTax}%)" : ''), 'value' => $currency . number_format($tax, 2)];
            }

            foreach ($this->safeJsonArray($b->after_total_fees ?? null) as $c) {
                $name = $c['name'] ?? ($c['product_name'] ?? '');
                $amt  = (float) ($c['amount'] ?? ($c['product_amount'] ?? 0));
                if ($name === '' || $amt == 0) continue;
                $rows[] = ['label' => e((string) $name), 'value' => $currency . number_format($amt, 2)];
            }
        }

        $schedule = array_values(array_filter(
            $this->safeJsonArray($b->amount_data ?? null),
            fn ($item) => is_array($item) && isset($item['amount']) && (float) $item['amount'] > 0
        ));
        if (empty($rows) && empty($schedule)) return '';

        $total = $currency . number_format((float) ($b->total_amount ?? 0), 2);
        $currencyCode = trim((string) ($b->currency ?? ''));

        // Email-safe inline-styled table.
        $html  = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:14px 0;font-size:14px;color:#1a202c;">';
        $html .= '<thead><tr><th colspan="2" style="text-align:left;background:#f4f6f8;color:#5b6473;font-size:11px;text-transform:uppercase;letter-spacing:.04em;padding:10px 12px;border-bottom:1px solid #e2e8f0;">Price details</th></tr></thead>';
        $html .= '<tbody>';
        foreach ($rows as $r) {
            $valColor = ($r['class'] ?? '') === 'discount' ? 'color:#15803d;' : '';
            $html .= '<tr>'
                  . '<td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;">' . $r['label'] . '</td>'
                  . '<td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:right;font-variant-numeric:tabular-nums;' . $valColor . '">' . $r['value'] . '</td>'
                  . '</tr>';
        }
        $html .= '<tr>'
              . '<td style="padding:12px;font-weight:700;background:#f7f8fb;">Total' . ($currencyCode ? ' (' . e($currencyCode) . ')' : '') . '</td>'
              . '<td style="padding:12px;font-weight:700;background:#f7f8fb;text-align:right;font-variant-numeric:tabular-nums;">' . $total . '</td>'
              . '</tr>';
        $html .= '</tbody></table>';

        if (!empty($schedule)) {
            $paidRemaining = (float) \DB::table('payments')
                ->where('booking_id', $b->id)
                ->where('status', 'complete')
                ->sum('amount');

            $html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:14px 0;font-size:14px;color:#1a202c;">'
                  . '<thead><tr><th colspan="2" style="text-align:left;background:#f4f6f8;color:#5b6473;font-size:11px;text-transform:uppercase;letter-spacing:.04em;padding:10px 12px;border-bottom:1px solid #e2e8f0;">Payment schedule</th></tr></thead><tbody>';
            foreach ($schedule as $index => $installment) {
                $amount = (float) ($installment['amount'] ?? 0);
                if ($amount <= 0) continue;
                $label = trim((string) ($installment['label'] ?? '')) ?: 'Payment ' . ($index + 1);
                $due = trim((string) ($installment['date_due'] ?? ''));
                $dueTs = $due !== '' ? strtotime($due) : false;
                $dueLabel = $dueTs ? date('M j, Y', $dueTs) : $due;
                $isPaid = $paidRemaining + 0.005 >= $amount;
                if ($isPaid) {
                    $paidRemaining -= $amount;
                }
                $meta = array_filter([$dueLabel ? 'Due ' . $dueLabel : null, $isPaid ? 'Paid' : null]);
                $html .= '<tr>'
                      . '<td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;">' . e($label)
                      . ($meta ? '<br><span style="font-size:12px;color:' . ($isPaid ? '#15803d' : '#64748b') . ';">' . e(implode(' · ', $meta)) . '</span>' : '')
                      . '</td>'
                      . '<td style="padding:8px 12px;border-bottom:1px solid #f1f5f9;text-align:right;font-variant-numeric:tabular-nums;">'
                      . $currency . number_format($amount, 2) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        return $html;
    }

    /**
     * Security deposits are provider-specific and usually excluded from the
     * booking total. Read the same frozen raw quote used by the payment page.
     *
     * @return array{amount: float, label: string}
     */
    private function securityDepositFromQuote(): array
    {
        $raw = $this->safeJsonArray($this->booking->quote_data_all ?? null);
        $source = strtolower((string) ($this->booking->pms_source ?? $this->property->pms_source ?? ''));
        $amount = 0.0;
        $included = false;
        $preauthorized = true;

        if ($source === 'lodgify') {
            $entry = $raw['data'][0] ?? $raw['data'] ?? [];
            $damage = $entry['scheduled_damage_protection'][0] ?? [];
            $amount = (float) ($damage['amount'] ?? 0);
            $preauthorized = strtolower((string) ($damage['type'] ?? 'authorization')) === 'authorization';
        } elseif ($source === 'hostfully') {
            $quote = $raw['data']['quote'] ?? [];
            $amount = (float) ($quote['securityDeposit'] ?? 0);
            $included = (bool) ($quote['includeSecurityDepositInTotal'] ?? false);
            $preauthorized = !$included;
        } elseif ($source === 'guesty') {
            $amount = (float) ($raw['data']['money']['securityDeposit'] ?? 0);
        } elseif ($source === 'hostaway') {
            $result = $raw['data']['result'] ?? $raw['result'] ?? [];
            $amount = (float) ($result['refundableDamageDeposit'] ?? 0);
        }

        $label = $included
            ? 'Refundable security deposit (included in total)'
            : ($preauthorized
                ? 'Refundable security deposit hold (not included in total)'
                : 'Refundable security deposit (not included in total)');

        return ['amount' => $amount, 'label' => $label];
    }

    /**
     * Phase 13J — accept JSON string or array, return a normalized array of associative arrays.
     */
    private function safeJsonArray($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
