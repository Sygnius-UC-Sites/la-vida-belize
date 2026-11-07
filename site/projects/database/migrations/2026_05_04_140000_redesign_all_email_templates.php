<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Replaces every email template (customer + admin) with a professionally
 * designed, email-safe HTML version. All templates share a common visual
 * grammar: greeting → context paragraph → booking-summary card → CTA →
 * friendly close. Email-safe means inline styles + table layout (no CSS
 * grid, no flexbox — those break in Outlook/Gmail clients).
 *
 * Idempotent on re-run: overwrites every template (admin-customised
 * versions ARE replaced — re-run only when you want to reset).
 *
 * Merge fields that the StayCommunicationsResolver expands at send time:
 *   {{guest_name}} {{property_name}} {{check_in}} {{check_out}} {{nights}}
 *   {{adults}} {{children}} {{pets}} {{total_amount}} {{booking_id}}
 *   {{brand}} {{admin_url}} {{booking_url}} {{review_url}}
 */
return new class extends Migration {
    public function up(): void
    {
        // Reusable atoms (PHP heredoc — substituted into each body).
        $card = static function (array $rows): string {
            $tr = '';
            foreach ($rows as $label => $value) {
                $tr .= '<tr>'
                    . '<td style="padding:8px 0;color:#64748b;font-size:13px;width:38%;">' . $label . '</td>'
                    . '<td style="padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;">' . $value . '</td>'
                    . '</tr>';
            }
            return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" '
                 . 'style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;">'
                 . $tr
                 . '</table>';
        };

        $btn = static function (string $label, string $href): string {
            return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0;">'
                 . '<tr><td style="border-radius:6px;background:#1a73e8;">'
                 . '<a href="' . $href . '" target="_blank" '
                 . 'style="display:inline-block;padding:12px 26px;color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;border-radius:6px;">'
                 . $label . '</a></td></tr></table>';
        };

        $signoff = '<p style="margin-top:24px;color:#475569;">Warmly,<br><strong style="color:#0f172a;">{{brand}}</strong></p>';

        $stayCard = $card([
            'Property' => '{{property_name}}',
            'Check-in' => '{{check_in}}',
            'Check-out' => '{{check_out}}',
            'Nights' => '{{nights}}',
            'Guests' => '{{adults}} adult, {{children}} child, {{pets}} pet',
            'Booking ID' => '#{{booking_id}}',
        ]);

        $stayCardWithTotal = $card([
            'Property' => '{{property_name}}',
            'Check-in' => '{{check_in}}',
            'Check-out' => '{{check_out}}',
            'Nights' => '{{nights}}',
            'Guests' => '{{adults}} adult, {{children}} child, {{pets}} pet',
            'Total' => '${{total_amount}}',
            'Booking ID' => '#{{booking_id}}',
        ]);

        $templates = [

            // ============ 1. ENQUIRY (received from website form) ============
            'enquiry_default_subject' => 'We received your enquiry — {{property_name}}',
            'enquiry_default_body_html' =>
                '<p style="font-size:18px;color:#0f172a;margin:0 0 12px;">Thanks for reaching out, {{guest_name}}.</p>'
              . '<p>We\'ve got your enquiry for <strong>{{property_name}}</strong> and will get back to you within 24 hours with availability and a tailored quote.</p>'
              . $stayCard
              . '<p>If you have any questions in the meantime — accessibility, pet policy, local activities — just reply to this email and one of us will pick it up.</p>'
              . $signoff,

            // ============ 2. AWAITING PAYMENT ============
            'awaiting_payment_default_subject' => 'Complete your booking at {{property_name}}',
            'awaiting_payment_default_body_html' =>
                '<p style="font-size:18px;color:#0f172a;margin:0 0 12px;">You\'re almost there, {{guest_name}}.</p>'
              . '<p>Your enquiry for <strong>{{property_name}}</strong> has been approved. To lock in your dates, please complete your payment using the link below.</p>'
              . $stayCardWithTotal
              . $btn('Pay & confirm booking', '{{booking_url}}')
              . '<p style="color:#64748b;font-size:13px;">The booking is held for you for 48 hours — after that, the dates open back up to other guests.</p>'
              . $signoff,

            // ============ 3. CONFIRMED ============
            'confirmed_default_subject' => 'Booking confirmed — {{property_name}} ({{check_in}} → {{check_out}})',
            'confirmed_default_body_html' =>
                '<p style="font-size:20px;color:#0f172a;margin:0 0 12px;">🎉 You\'re booked, {{guest_name}}!</p>'
              . '<p>Your stay at <strong>{{property_name}}</strong> is confirmed. We\'ll send check-in details a day or two before your arrival.</p>'
              . $stayCardWithTotal
              . '<p style="margin-top:18px;"><strong>What\'s next:</strong></p>'
              . '<ul style="margin:8px 0 0 20px;padding:0;color:#334155;">'
              . '<li style="margin-bottom:6px;">A welcome package with house info will arrive a few days before check-in</li>'
              . '<li style="margin-bottom:6px;">Check-in details (door code, parking, wifi) the day before</li>'
              . '<li style="margin-bottom:6px;">Reach us anytime at this email or via the contact info in the welcome pack</li>'
              . '</ul>'
              . $signoff,

            // ============ 4. CANCELLATION ============
            'cancellation_default_subject' => 'Your booking at {{property_name}} has been cancelled',
            'cancellation_default_body_html' =>
                '<p style="font-size:18px;color:#0f172a;margin:0 0 12px;">Hi {{guest_name}},</p>'
              . '<p>This is to confirm your booking at <strong>{{property_name}}</strong> has been cancelled.</p>'
              . $stayCard
              . '<p>If a refund applies under the cancellation policy you accepted at booking, it will be processed to the original payment method within 5–10 business days.</p>'
              . '<p>We\'re sorry your plans changed — and hope to host you another time. If anything was wrong on our end, please reply and let us know.</p>'
              . $signoff,

            // ============ 5. WELCOME PACKAGE ============
            'welcome_default_subject' => 'Welcome to {{property_name}} — your stay begins {{check_in}}',
            'welcome_default_body_html' =>
                '<p style="font-size:20px;color:#0f172a;margin:0 0 12px;">{{check_in}} is just around the corner, {{guest_name}}.</p>'
              . '<p>We\'re looking forward to hosting you at <strong>{{property_name}}</strong>. Here\'s a quick orientation so you arrive prepared.</p>'
              . $stayCard
              . '<p style="margin-top:18px;"><strong>Getting there</strong></p>'
              . '<p style="margin:6px 0;">Detailed driving directions, the gate code, and parking instructions arrive in your check-in email a day before arrival.</p>'
              . '<p style="margin-top:14px;"><strong>What we provide</strong></p>'
              . '<ul style="margin:6px 0 0 20px;padding:0;color:#334155;">'
              . '<li style="margin-bottom:5px;">Linens, towels, basic toiletries</li>'
              . '<li style="margin-bottom:5px;">Coffee, tea, salt, oil — the essentials</li>'
              . '<li style="margin-bottom:5px;">High-speed Wi-Fi throughout the property</li>'
              . '<li style="margin-bottom:5px;">A house manual on-site with restaurant + activity tips</li>'
              . '</ul>'
              . '<p style="margin-top:14px;color:#64748b;font-size:13px;">Need anything before arrival? Reply to this email — we read everything.</p>'
              . $signoff,

            // ============ 6. CHECK-IN INFO ============
            'checkin_default_subject' => 'Check-in details for {{property_name}} — see you tomorrow',
            'checkin_default_body_html' =>
                '<p style="font-size:18px;color:#0f172a;margin:0 0 12px;">All set for tomorrow, {{guest_name}}.</p>'
              . '<p>Here are your arrival details for <strong>{{property_name}}</strong>:</p>'
              . $stayCard
              . '<p style="margin-top:18px;"><strong>How to get in</strong></p>'
              . '<p style="margin:6px 0 14px;">Self check-in with keypad. Your access details and full directions are in the property\'s house manual + the printed welcome book inside.</p>'
              . '<p><strong>While you\'re here</strong></p>'
              . '<ul style="margin:6px 0 0 20px;padding:0;color:#334155;">'
              . '<li style="margin-bottom:5px;">Quiet hours 10 PM – 7 AM</li>'
              . '<li style="margin-bottom:5px;">Wi-Fi name + password are inside the welcome book</li>'
              . '<li style="margin-bottom:5px;">Emergency phone: posted by the front door + in the welcome book</li>'
              . '</ul>'
              . '<p style="margin-top:14px;color:#64748b;font-size:13px;">Have a wonderful stay. Reach out anytime — we\'re close by if you need anything.</p>'
              . $signoff,

            // ============ 7. CHECK-OUT REMINDER ============
            'checkout_default_subject' => 'Check-out tomorrow — {{property_name}}',
            'checkout_default_body_html' =>
                '<p style="font-size:18px;color:#0f172a;margin:0 0 12px;">Hope you\'ve had a great stay, {{guest_name}}.</p>'
              . '<p>A quick reminder: check-out from <strong>{{property_name}}</strong> is tomorrow.</p>'
              . $stayCard
              . '<p style="margin-top:18px;"><strong>Before you head out</strong></p>'
              . '<ul style="margin:6px 0 0 20px;padding:0;color:#334155;">'
              . '<li style="margin-bottom:5px;">Strip used towels into the laundry basket</li>'
              . '<li style="margin-bottom:5px;">Bag the trash and place it in the bin outside</li>'
              . '<li style="margin-bottom:5px;">Turn off A/C, lights, and ceiling fans</li>'
              . '<li style="margin-bottom:5px;">Lock the doors — keypad disarms automatically</li>'
              . '</ul>'
              . '<p style="margin-top:14px;">Safe travels! We\'ll be in touch in a few days for a quick review request.</p>'
              . $signoff,

            // ============ 8. INSTALMENT 2 ============
            'installment2_default_subject' => 'Reminder: second payment due — {{property_name}}',
            'installment2_default_body_html' =>
                '<p style="font-size:18px;color:#0f172a;margin:0 0 12px;">Hi {{guest_name}},</p>'
              . '<p>This is a friendly nudge — the <strong>second instalment</strong> for your stay at <strong>{{property_name}}</strong> is now due.</p>'
              . $stayCardWithTotal
              . $btn('Make a payment', '{{booking_url}}')
              . '<p style="color:#64748b;font-size:13px;">Cleared payments keep your booking on schedule. Reply if you need a different payment method or split arrangement.</p>'
              . $signoff,

            // ============ 9. INSTALMENT 3 ============
            'installment3_default_subject' => 'Final payment due — {{property_name}}',
            'installment3_default_body_html' =>
                '<p style="font-size:18px;color:#0f172a;margin:0 0 12px;">Almost there, {{guest_name}}.</p>'
              . '<p>The <strong>final instalment</strong> for your stay at <strong>{{property_name}}</strong> is due. Once it clears, your check-in details will go out on schedule.</p>'
              . $stayCardWithTotal
              . $btn('Complete final payment', '{{booking_url}}')
              . '<p style="color:#64748b;font-size:13px;">Need a payment-method tweak or a quick split? Just reply — we\'ll figure it out.</p>'
              . $signoff,

            // ============ 10. REVIEW REQUEST ============
            'review_default_subject' => 'How was your stay at {{property_name}}?',
            'review_default_body_html' =>
                '<p style="font-size:20px;color:#0f172a;margin:0 0 12px;">Thanks for staying with us, {{guest_name}}.</p>'
              . '<p>We hope you had a wonderful time at <strong>{{property_name}}</strong>. Would you take a minute to share how it went?</p>'
              . $stayCard
              . '<p>Your review tells future guests what to expect — and tells us where we can improve. Even a sentence or two means a lot.</p>'
              . $btn('Leave a review', '{{review_url}}')
              . '<p style="color:#64748b;font-size:13px;">If anything was off or could have been better, please reply privately — we\'d rather know.</p>'
              . $signoff,
        ];

        // ===== Owner-facing alerts (concise, not customer-tone) =====
        $ownerCard = $card([
            'Booking'   => '#{{booking_id}}',
            'Guest'     => '{{guest_name}}',
            'Property'  => '{{property_name}}',
            'Stay'      => '{{check_in}} → {{check_out}} ({{nights}}n)',
            'Guests'    => '{{adults}}A · {{children}}C · {{pets}}P',
            'Total'     => '${{total_amount}}',
        ]);

        $ownerCta = '<p><a href="{{admin_url}}" target="_blank" '
                  . 'style="display:inline-block;padding:10px 22px;background:#0f172a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">'
                  . 'Open booking in admin →</a></p>';

        $ownerTemplates = [
            'admin_alert_enquiry_subject' => '[New enquiry] {{property_name}} — {{guest_name}}',
            'admin_alert_enquiry_body_html' =>
                '<p>A new booking enquiry just came in for <strong>{{property_name}}</strong>.</p>' . $ownerCard . $ownerCta,

            'admin_alert_awaiting_payment_subject' => '[Payment pending] #{{booking_id}} — {{property_name}}',
            'admin_alert_awaiting_payment_body_html' =>
                '<p>Booking #{{booking_id}} was approved and the payment link has been issued. Awaiting renter payment.</p>' . $ownerCard . $ownerCta,

            'admin_alert_confirmed_subject' => '[Booking confirmed] #{{booking_id}} — {{property_name}}',
            'admin_alert_confirmed_body_html' =>
                '<p><strong>Payment received</strong> — booking #{{booking_id}} is now confirmed and on the calendar.</p>' . $ownerCard . $ownerCta,

            'admin_alert_cancellation_subject' => '[Cancelled] #{{booking_id}} — {{property_name}}',
            'admin_alert_cancellation_body_html' =>
                '<p>Booking #{{booking_id}} was cancelled. Refund processing (if applicable) follows the cancellation policy on the booking.</p>' . $ownerCard . $ownerCta,

            'admin_alert_welcome_subject' => '[Welcome email sent] #{{booking_id}} — {{property_name}}',
            'admin_alert_welcome_body_html' =>
                '<p>The welcome package has been delivered to <strong>{{guest_name}}</strong>. Check-in details will follow on schedule.</p>' . $ownerCard . $ownerCta,

            'admin_alert_checkin_subject' => '[Check-in info sent] #{{booking_id}} — {{property_name}}',
            'admin_alert_checkin_body_html' =>
                '<p>Check-in instructions delivered to <strong>{{guest_name}}</strong>. Door code, wifi and arrival window included.</p>' . $ownerCard . $ownerCta,

            'admin_alert_checkout_subject' => '[Check-out reminder sent] #{{booking_id}} — {{property_name}}',
            'admin_alert_checkout_body_html' =>
                '<p>Check-out reminder delivered to <strong>{{guest_name}}</strong>. Cleaning checklist included.</p>' . $ownerCard . $ownerCta,

            'admin_alert_installment2_subject' => '[Instalment 2 reminder sent] #{{booking_id}} — {{property_name}}',
            'admin_alert_installment2_body_html' =>
                '<p>Second instalment payment reminder sent to <strong>{{guest_name}}</strong>.</p>' . $ownerCard . $ownerCta,

            'admin_alert_installment3_subject' => '[Final payment reminder sent] #{{booking_id}} — {{property_name}}',
            'admin_alert_installment3_body_html' =>
                '<p>Final instalment payment reminder sent to <strong>{{guest_name}}</strong>.</p>' . $ownerCard . $ownerCta,

            'admin_alert_review_subject' => '[Review request sent] #{{booking_id}} — {{property_name}}',
            'admin_alert_review_body_html' =>
                '<p>Review request delivered to <strong>{{guest_name}}</strong>. Submitted reviews appear in the admin Testimonials tab for approval.</p>' . $ownerCard . $ownerCta,
        ];

        foreach (array_merge($templates, $ownerTemplates) as $name => $value) {
            $exists = DB::table('basic_settings')->where('name', $name)->exists();
            if ($exists) {
                DB::table('basic_settings')->where('name', $name)->update(['value' => $value, 'updated_at' => now()]);
            } else {
                DB::table('basic_settings')->insert(['name' => $name, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // Non-destructive — leave templates in place on rollback so admin
        // customisations on top of these aren't wiped.
    }
};
