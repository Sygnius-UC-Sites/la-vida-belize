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
        // Non-destructive: keep seeded rows on rollback.
    }

    private function defaults(): array
    {
        return [
            // Booking enquiry confirmation
            'enquiry_default_subject' => 'We received your enquiry about {{property_name}}',
            'enquiry_default_body_html' => <<<HTML
<p>Hi {{guest_name}},</p>
<p>Thank you for your interest in <strong>{{property_name}}</strong> for {{check_in}} → {{check_out}} ({{nights}} nights).</p>
<p>We've received your enquiry and our team will review the request and get back to you shortly. If you have any questions in the meantime, reply to this email or reach us at <a href="mailto:{{site_email}}">{{site_email}}</a>.</p>
<p>Best regards,<br>{{brand}}</p>
HTML,

            // Awaiting payment (magic link)
            'awaiting_payment_default_subject' => 'Complete your booking at {{property_name}}',
            'awaiting_payment_default_body_html' => <<<HTML
<p>Hi {{guest_name}},</p>
<p>Great news — your request for <strong>{{property_name}}</strong> ({{check_in}} → {{check_out}}, {{nights}} nights) has been approved.</p>
<p>To confirm your stay, please complete payment using the secure link below:</p>
<p><a href="{{magic_link}}" style="display:inline-block;background:#1e293b;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600;">Complete payment →</a></p>
<p>The link is unique to your booking and valid for 7 days. If you have any questions, reach us at <a href="mailto:{{site_email}}">{{site_email}}</a>.</p>
<p>Best regards,<br>{{brand}}</p>
HTML,

            // Booking confirmed (payment received)
            'confirmed_default_subject' => 'Booking confirmed: {{property_name}} ({{check_in}} → {{check_out}})',
            'confirmed_default_body_html' => <<<HTML
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
HTML,

            // Pre-stay subject lines (bodies come from welcome_default_description etc.)
            'welcome_default_subject'  => 'Welcome to {{property_name}} — your stay starts {{check_in}}',
            'checkin_default_subject'  => 'Check-in details for your stay at {{property_name}}',
            'checkout_default_subject' => 'Check-out details for your stay at {{property_name}}',

            // Installment 2 reminder
            'installment2_default_subject' => 'Payment reminder: installment {{installment_number}} due {{installment_due_date}}',
            'installment2_default_body_html' => <<<HTML
<p>Hi {{guest_name}},</p>
<p>This is a friendly reminder that installment <strong>{{installment_number}}</strong> for your stay at {{property_name}} is due on <strong>{{installment_due_date}}</strong>.</p>
<p><strong>Amount due:</strong> {{installment_amount}}</p>
<p>You can complete the payment using the secure link below:</p>
<p><a href="{{magic_link}}" style="display:inline-block;background:#1e293b;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600;">Pay installment {{installment_number}} →</a></p>
<p>If you have already paid or have any questions, reach us at <a href="mailto:{{site_email}}">{{site_email}}</a>.</p>
<p>Best regards,<br>{{brand}}</p>
HTML,

            // Installment 3 reminder (similar but worded for the final payment)
            'installment3_default_subject' => 'Final payment reminder: installment {{installment_number}} due {{installment_due_date}}',
            'installment3_default_body_html' => <<<HTML
<p>Hi {{guest_name}},</p>
<p>This is a reminder that the final installment ({{installment_number}}) for your stay at {{property_name}} ({{check_in}} → {{check_out}}) is due on <strong>{{installment_due_date}}</strong>.</p>
<p><strong>Amount due:</strong> {{installment_amount}}</p>
<p>Complete the payment here:</p>
<p><a href="{{magic_link}}" style="display:inline-block;background:#1e293b;color:#fff;padding:10px 18px;border-radius:6px;text-decoration:none;font-weight:600;">Pay final installment →</a></p>
<p>Once received, we'll consider your booking fully paid and you can look forward to your stay. Reach us at <a href="mailto:{{site_email}}">{{site_email}}</a> with any questions.</p>
<p>Best regards,<br>{{brand}}</p>
HTML,

            // Review request
            'review_default_subject' => 'How was your stay at {{property_name}}?',
            'review_default_body_html' => <<<HTML
<p>Hi {{guest_name}},</p>
<p>Thank you for choosing <strong>{{property_name}}</strong> for your recent stay. We hope you had a wonderful time.</p>
<p>If you have a moment, we'd love to hear about your experience. Your feedback helps us improve and helps future guests find the right place for them.</p>
<p>Reply to this email or reach us at <a href="mailto:{{site_email}}">{{site_email}}</a> with any thoughts you'd like to share.</p>
<p>Hope to host you again soon.</p>
<p>Best regards,<br>{{brand}}</p>
HTML,
        ];
    }
};
