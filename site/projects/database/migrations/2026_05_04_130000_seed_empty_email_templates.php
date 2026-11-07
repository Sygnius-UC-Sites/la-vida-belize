<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fill the email-template rows that are still empty (subject or body == '')
 * with sensible, branded defaults. Non-empty rows are NOT touched — admin
 * customisations always win.
 *
 * Merge fields available (expanded server-side by StayCommunicationsResolver):
 *   {{property_name}} {{guest_name}} {{check_in}} {{check_out}} {{nights}}
 *   {{total_amount}} {{adults}} {{children}} {{pets}} {{booking_id}}
 *   {{brand}} {{admin_url}} {{booking_url}}
 */
return new class extends Migration {
    public function up(): void
    {
        // Common signature/footer block reused inside each body. Plain HTML
        // (no inline-style tricks) — the email-shell layout wraps these.
        $sig = '<p style="margin-top:24px;">— {{brand}}</p>';

        $defaults = [
            // ===== Customer-facing =====
            'enquiry_default_subject' =>
                'Thanks for your enquiry about {{property_name}}',
            'enquiry_default_body_html' =>
                "<p>Hi {{guest_name}},</p>"
              . "<p>Thanks for getting in touch about <strong>{{property_name}}</strong> for {{check_in}} → {{check_out}} ({{nights}} night{{nights_plural}}).</p>"
              . "<p>We've received your enquiry and will get back to you within 24 hours with availability and a tailored quote.</p>"
              . "<p>If you have any questions in the meantime, just reply to this email.</p>"
              . $sig,

            'awaiting_payment_default_subject' =>
                'Complete your booking — {{property_name}}',

            'confirmed_default_subject' =>
                'Booking confirmed — {{property_name}} ({{check_in}} → {{check_out}})',

            'cancellation_default_subject' =>
                'Booking cancelled — {{property_name}}',

            'welcome_default_subject' =>
                'Welcome to {{property_name}} — your stay begins {{check_in}}',

            'checkin_default_subject' =>
                'Check-in details for {{property_name}}',

            'checkout_default_subject' =>
                'Check-out reminder — {{property_name}}',

            'installment2_default_subject' =>
                'Reminder: second payment due for {{property_name}}',
            'installment2_default_body_html' =>
                "<p>Hi {{guest_name}},</p>"
              . "<p>This is a friendly reminder that the <strong>second instalment</strong> for your stay at {{property_name}} is now due.</p>"
              . "<p><strong>Stay:</strong> {{check_in}} → {{check_out}} ({{nights}} night{{nights_plural}})<br>"
              . "<strong>Booking ID:</strong> {{booking_id}}</p>"
              . "<p>You can complete the payment using the link in your original confirmation email, or reply here if you need help.</p>"
              . $sig,

            'installment3_default_subject' =>
                'Final payment due for {{property_name}}',
            'installment3_default_body_html' =>
                "<p>Hi {{guest_name}},</p>"
              . "<p>The <strong>final instalment</strong> for your booking at {{property_name}} is now due.</p>"
              . "<p><strong>Stay:</strong> {{check_in}} → {{check_out}}<br>"
              . "<strong>Booking ID:</strong> {{booking_id}}</p>"
              . "<p>Once this clears, your check-in details will be sent on schedule.</p>"
              . $sig,

            'review_default_subject' =>
                'How was your stay at {{property_name}}?',
            'review_default_body_html' =>
                "<p>Hi {{guest_name}},</p>"
              . "<p>We hope you had a wonderful time at <strong>{{property_name}}</strong>. Would you take a minute to share how it went?</p>"
              . "<p>Your review helps future guests know what to expect — and tells us where we can do better.</p>"
              . "<p style=\"margin:20px 0;\"><a href=\"{{review_url}}\" "
              . "style=\"display:inline-block;background:#1a73e8;color:#fff;padding:10px 22px;border-radius:6px;text-decoration:none;font-weight:600;\">"
              . "Leave a review</a></p>"
              . "<p>Thanks again for choosing us — hope to see you back.</p>"
              . $sig,
        ];

        foreach ($defaults as $name => $value) {
            $existing = DB::table('basic_settings')->where('name', $name)->value('value');
            if ($existing === null) {
                DB::table('basic_settings')->insert([
                    'name' => $name, 'value' => $value,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            } elseif (trim($existing) === '') {
                DB::table('basic_settings')->where('name', $name)
                    ->update(['value' => $value, 'updated_at' => now()]);
            }
            // else: admin customised — leave alone.
        }
    }

    public function down(): void
    {
        // Non-destructive: don't roll back. Admins may have edited the
        // values via /admin/settings/stay-communications — wiping them on
        // rollback would lose their work.
    }
};
