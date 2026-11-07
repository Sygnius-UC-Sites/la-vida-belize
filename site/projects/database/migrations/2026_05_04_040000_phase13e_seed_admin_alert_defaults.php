<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        foreach ($this->defaults() as $name => $value) {
            $existing = DB::table('basic_settings')->where('name', $name)->first();
            if ($existing && $existing->value !== null && $existing->value !== '') {
                continue; // don't overwrite admin customizations
            }
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }

    private function defaults(): array
    {
        $shortBody = function (string $headline, string $extraNote = '') {
            $extra = $extraNote ? "<p>{$extraNote}</p>" : '';
            return <<<HTML
<p><strong>{$headline}</strong></p>
<ul>
<li>Booking ID: #{{booking_id}}</li>
<li>Property: {{property_name}}</li>
<li>Guest: {{guest_name}} &lt;{{guest_email}}&gt;</li>
<li>Dates: {{check_in}} → {{check_out}} ({{nights}} nights)</li>
<li>Total: {{total_amount}}</li>
</ul>
{$extra}
<p><a href="{{admin_url}}">View booking in admin →</a></p>
HTML;
        };

        return [
            // 1. Enquiry received
            'admin_alert_enquiry_subject' => 'New booking enquiry — {{property_name}}',
            'admin_alert_enquiry_body_html' => $shortBody('A new booking enquiry has been submitted.'),

            // 2. Awaiting-payment link sent
            'admin_alert_awaiting_payment_subject' => 'Payment link sent — booking #{{booking_id}} ({{property_name}})',
            'admin_alert_awaiting_payment_body_html' => $shortBody('An awaiting-payment magic link has been sent to the guest.', 'The link is valid for 7 days.'),

            // 3. Booking confirmed (payment received)
            'admin_alert_confirmed_subject' => 'Booking confirmed — {{property_name}} ({{check_in}} → {{check_out}})',
            'admin_alert_confirmed_body_html' => $shortBody('Payment received — the booking is now confirmed.'),

            // 4. Booking cancelled
            'admin_alert_cancelled_subject' => 'Booking #{{booking_id}} cancelled — {{property_name}}',
            'admin_alert_cancelled_body_html' => $shortBody('A booking has been cancelled.', 'Cancelled at: {{cancel_date}}'),

            // 5. Installment 2 reminder sent
            'admin_alert_installment2_subject' => 'Installment 2 reminder sent — booking #{{booking_id}} ({{property_name}})',
            'admin_alert_installment2_body_html' => $shortBody('A 2nd-installment payment reminder has been sent to the guest.'),

            // 6. Installment 3 reminder sent
            'admin_alert_installment3_subject' => 'Final installment reminder sent — booking #{{booking_id}} ({{property_name}})',
            'admin_alert_installment3_body_html' => $shortBody('A final-installment payment reminder has been sent to the guest.'),

            // 7. Rental agreement signed
            'admin_alert_rental_agreement_subject' => 'Rental agreement signed — booking #{{booking_id}}',
            'admin_alert_rental_agreement_body_html' => $shortBody('The guest has signed the rental agreement.', 'A copy of the signed PDF is attached.'),

            // 8. Welcome BCC (off by default)
            'admin_bcc_welcome_enabled'        => '0',
            'admin_alert_welcome_subject'      => 'Welcome email sent — booking #{{booking_id}} ({{property_name}})',
            'admin_alert_welcome_body_html'    => $shortBody('A welcome package has been sent to the guest.'),

            // 9. Check-in BCC (off by default)
            'admin_bcc_checkin_enabled'        => '0',
            'admin_alert_checkin_subject'      => 'Check-in info sent — booking #{{booking_id}} ({{property_name}})',
            'admin_alert_checkin_body_html'    => $shortBody('Check-in details have been sent to the guest.'),

            // 10. Check-out BCC (off by default)
            'admin_bcc_checkout_enabled'       => '0',
            'admin_alert_checkout_subject'     => 'Check-out info sent — booking #{{booking_id}} ({{property_name}})',
            'admin_alert_checkout_body_html'   => $shortBody('Check-out details have been sent to the guest.'),
        ];
    }
};
