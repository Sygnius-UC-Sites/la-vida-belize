<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Phase 13J — bake {{invoice_table}} into the seeded default bodies for the
     * 3 customer-facing emails that should show an itemized fee breakdown:
     * confirmed (booking confirmation), awaiting-payment (magic link), cancellation.
     *
     * Non-destructive: only updates rows where the value still matches the OLD seeded
     * default. If an admin has customized the body, we leave it alone — they can
     * paste {{invoice_table}} themselves wherever they want.
     */
    public function up(): void
    {
        foreach ($this->updates() as $name => $pair) {
            [$oldDefault, $newDefault] = $pair;
            $existing = DB::table('basic_settings')->where('name', $name)->value('value');
            if ($existing === null || $existing === '') {
                // Empty — just seed the new value.
                DB::table('basic_settings')->updateOrInsert(
                    ['name' => $name],
                    ['value' => $newDefault, 'created_at' => now(), 'updated_at' => now()],
                );
                continue;
            }
            // If admin still has the unmodified Phase-13D default, upgrade it. Otherwise skip.
            if (trim($existing) === trim($oldDefault)) {
                DB::table('basic_settings')->where('name', $name)->update([
                    'value' => $newDefault,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }

    private function updates(): array
    {
        // Old default (from Phase 13D migration) → new default (with {{invoice_table}}).
        return [
            'awaiting_payment_default_body_html' => [
                $this->oldAwaitingPayment(),
                $this->newAwaitingPayment(),
            ],
            'confirmed_default_body_html' => [
                $this->oldConfirmed(),
                $this->newConfirmed(),
            ],
            'cancellation_default_body_html' => [
                $this->oldCancellation(),
                $this->newCancellation(),
            ],
        ];
    }

    private function oldAwaitingPayment(): string
    {
        return <<<HTML
<p>Hi {{guest_name}},</p>
<p>Great news — your request for <strong>{{property_name}}</strong> ({{check_in}} → {{check_out}}, {{nights}} nights) has been approved.</p>
<p>To confirm your stay, please complete payment using the secure link below:</p>
<p><a href="{{magic_link}}" style="display:inline-block;background:#1e293b;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600;">Complete payment →</a></p>
<p>The link is unique to your booking and valid for 7 days. If you have any questions, reach us at <a href="mailto:{{site_email}}">{{site_email}}</a>.</p>
<p>Best regards,<br>{{brand}}</p>
HTML;
    }

    private function newAwaitingPayment(): string
    {
        return <<<HTML
<p>Hi {{guest_name}},</p>
<p>Great news — your request for <strong>{{property_name}}</strong> ({{check_in}} → {{check_out}}, {{nights}} nights) has been approved.</p>

{{invoice_table}}

<p>To confirm your stay, please complete payment using the secure link below:</p>
<p><a href="{{magic_link}}" style="display:inline-block;background:#1e293b;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600;">Complete payment →</a></p>
<p>The link is unique to your booking and valid for 7 days. If you have any questions, reach us at <a href="mailto:{{site_email}}">{{site_email}}</a>.</p>
<p>Best regards,<br>{{brand}}</p>
HTML;
    }

    private function oldConfirmed(): string
    {
        return <<<HTML
<p>Hi {{guest_name}},</p>
<p>Your booking is confirmed! 🎉</p>
<p><strong>Booking summary</strong><br>
Booking ID: #{{booking_id}}<br>
Property: {{property_name}}<br>
Dates: {{check_in}} → {{check_out}} ({{nights}} nights)<br>
Total: {{total_amount}}</p>
<p>You'll receive your check-in details a day or two before arrival. In the meantime, if anything comes up, reach us at <a href="mailto:{{site_email}}">{{site_email}}</a> or {{site_phone}}.</p>
<p>We can't wait to host you.</p>
<p>Best regards,<br>{{brand}}</p>
HTML;
    }

    private function newConfirmed(): string
    {
        return <<<HTML
<p>Hi {{guest_name}},</p>
<p>Your booking is confirmed! 🎉</p>
<p><strong>Booking #{{booking_id}}</strong><br>
{{property_name}}<br>
{{check_in}} → {{check_out}} ({{nights}} nights)</p>

{{invoice_table}}

<p>You'll receive your check-in details a day or two before arrival. If anything comes up, reach us at <a href="mailto:{{site_email}}">{{site_email}}</a> or {{site_phone}}.</p>
<p>We can't wait to host you.</p>
<p>Best regards,<br>{{brand}}</p>
HTML;
    }

    private function oldCancellation(): string
    {
        return <<<HTML
<p>Hi {{guest_name}},</p>

<p>Your booking at <strong>{{property_name}}</strong> for {{check_in}} → {{check_out}} ({{nights}} nights) has been cancelled on {{cancel_date}}.</p>

<p><strong>Booking summary</strong><br>
Booking ID: #{{booking_id}}<br>
Total: {{total_amount}}</p>

<p>If you have any questions or believe this was made in error, please reach out to us at <a href="mailto:{{site_email}}">{{site_email}}</a> or {{site_phone}}.</p>

<p>We hope to host you in the future.</p>

<p>Best regards,<br>
{{brand}}</p>
HTML;
    }

    private function newCancellation(): string
    {
        return <<<HTML
<p>Hi {{guest_name}},</p>

<p>Your booking at <strong>{{property_name}}</strong> for {{check_in}} → {{check_out}} ({{nights}} nights) has been cancelled on {{cancel_date}}.</p>

{{invoice_table}}

<p>If you have any questions or believe this was made in error, please reach out to us at <a href="mailto:{{site_email}}">{{site_email}}</a> or {{site_phone}}.</p>

<p>We hope to host you in the future.</p>

<p>Best regards,<br>
{{brand}}</p>
HTML;
    }
};
