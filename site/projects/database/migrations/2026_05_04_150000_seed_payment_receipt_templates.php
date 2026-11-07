<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds payment-receipt email templates (customer + owner copy) that fire
 * automatically every time a payment posts on a booking — first instalment,
 * second instalment, balance, or any future top-up.
 *
 * Merge fields these templates use (server-side substitution):
 *   {{guest_name}}    {{property_name}}    {{check_in}} {{check_out}}
 *   {{nights}}        {{adults}} {{children}} {{pets}}
 *   {{booking_id}}    {{total_amount}}     {{brand}}    {{admin_url}}
 *   {{payment_amount}} {{payment_date}}    {{payment_method}}
 *   {{transaction_id}} {{receipt_url}}     {{outstanding_balance}}
 */
return new class extends Migration {
    public function up(): void
    {
        $card = static function (array $rows): string {
            $tr = '';
            foreach ($rows as $label => $value) {
                $tr .= '<tr>'
                    . '<td style="padding:8px 0;color:#64748b;font-size:13px;width:42%;">' . $label . '</td>'
                    . '<td style="padding:8px 0;color:#0f172a;font-size:14px;font-weight:600;text-align:right;">' . $value . '</td>'
                    . '</tr>';
            }
            return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" '
                 . 'style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 16px;margin:18px 0;">'
                 . $tr . '</table>';
        };

        $bigAmount = '<div style="margin:24px 0;padding:18px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:8px;text-align:center;">'
                   . '<div style="color:#065f46;font-size:13px;text-transform:uppercase;letter-spacing:.05em;font-weight:600;">Amount paid</div>'
                   . '<div style="color:#047857;font-size:34px;font-weight:700;margin-top:6px;">{{payment_amount}}</div>'
                   . '</div>';

        $signoff = '<p style="margin-top:24px;color:#475569;">Thanks for your payment,<br><strong style="color:#0f172a;">{{brand}}</strong></p>';

        $customerReceipt =
            '<p style="font-size:18px;color:#0f172a;margin:0 0 12px;">Hi {{guest_name}},</p>'
          . '<p>This is a receipt for your payment toward <strong>{{property_name}}</strong>. Keep this email for your records.</p>'
          . $bigAmount
          . $card([
                'Receipt date'   => '{{payment_date}}',
                'Payment method' => '{{payment_method}}',
                'Transaction ID' => '<code style="font-size:12px;color:#475569;">{{transaction_id}}</code>',
                'Booking ID'     => '#{{booking_id}}',
            ])
          . '<p style="margin-top:18px;"><strong>Booking details</strong></p>'
          . $card([
                'Property'   => '{{property_name}}',
                'Check-in'   => '{{check_in}}',
                'Check-out'  => '{{check_out}}',
                'Nights'     => '{{nights}}',
                'Guests'     => '{{adults}}A · {{children}}C · {{pets}}P',
                'Booking total'        => '${{total_amount}}',
                'Outstanding balance'  => '{{outstanding_balance}}',
            ])
          . '<p style="color:#64748b;font-size:13px;margin-top:14px;">Need a different copy of this receipt or an invoice for accounting? Reply to this email and we\'ll send what you need.</p>'
          . $signoff;

        $ownerReceiptCard = $card([
            'Booking'        => '#{{booking_id}}',
            'Guest'          => '{{guest_name}}',
            'Property'       => '{{property_name}}',
            'Stay'           => '{{check_in}} → {{check_out}} ({{nights}}n)',
            'Payment method' => '{{payment_method}}',
            'Transaction ID' => '<code style="font-size:12px;">{{transaction_id}}</code>',
            'Amount received' => '<span style="color:#047857;">{{payment_amount}}</span>',
            'Booking total'  => '${{total_amount}}',
            'Outstanding'    => '{{outstanding_balance}}',
        ]);

        $ownerCta = '<p><a href="{{admin_url}}" target="_blank" '
                  . 'style="display:inline-block;padding:10px 22px;background:#0f172a;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">'
                  . 'Open booking in admin →</a></p>';

        $ownerReceipt =
            '<p style="font-size:18px;color:#0f172a;margin:0 0 12px;">Payment received — booking #{{booking_id}}</p>'
          . '<p>A payment of <strong>{{payment_amount}}</strong> just landed on the booking below.</p>'
          . $ownerReceiptCard
          . $ownerCta;

        $rows = [
            'receipt_default_subject'         => 'Payment receipt — {{property_name}} · {{payment_amount}}',
            'receipt_default_body_html'       => $customerReceipt,

            'admin_alert_receipt_subject'     => '[Payment received {{payment_amount}}] #{{booking_id}} — {{property_name}}',
            'admin_alert_receipt_body_html'   => $ownerReceipt,
        ];

        foreach ($rows as $name => $value) {
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
        DB::table('basic_settings')->whereIn('name', [
            'receipt_default_subject', 'receipt_default_body_html',
            'admin_alert_receipt_subject', 'admin_alert_receipt_body_html',
        ])->delete();
    }
};
