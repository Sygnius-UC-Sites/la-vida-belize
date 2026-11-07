<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StayCommunicationsSettingsController extends Controller
{
    private const FIELDS = [
        'welcome_default_description', 'welcome_default_attachment_path',
        'checkin_default_arrival_window', 'checkin_default_door_code', 'checkin_default_wifi_name', 'checkin_default_wifi_password',
        'checkin_default_parking_instructions', 'checkin_default_host_name', 'checkin_default_host_phone', 'checkin_default_emergency_phone',
        'checkin_default_notes_html',
        'checkout_default_departure_time', 'checkout_default_key_return', 'checkout_default_cleaning_checklist', 'checkout_default_notes_html',
        'welcome_package_send_day', 'checkin_email_send_day', 'checkout_email_send_day',
        // Phase 13C — moved here from the deleted Booking & Payments tab.
        'review_send_day',
        // Phase 13C — admin-editable cancellation email (subject + HTML body with merge tokens).
        'cancellation_default_subject', 'cancellation_default_body_html',
        // Phase 13D — every guest-facing email is now editable (subject + body).
        // Booking lifecycle
        'enquiry_default_subject',          'enquiry_default_body_html',
        'awaiting_payment_default_subject', 'awaiting_payment_default_body_html',
        'confirmed_default_subject',        'confirmed_default_body_html',
        'receipt_default_subject',          'receipt_default_body_html',
        // Pre-stay (subject only — body comes from existing welcome_default_description /
        // checkin_default_notes_html / checkout_default_notes_html plus structured data fields).
        'welcome_default_subject',
        'checkin_default_subject',
        'checkout_default_subject',
        // Payment reminders
        'installment2_default_subject', 'installment2_default_body_html',
        'installment3_default_subject', 'installment3_default_body_html',
        // Post-stay
        'review_default_subject', 'review_default_body_html',

        // Phase 13E — Owner / admin alerts (every internal-facing notification is now editable too).
        'admin_alert_enquiry_subject',           'admin_alert_enquiry_body_html',
        'admin_alert_awaiting_payment_subject',  'admin_alert_awaiting_payment_body_html',
        'admin_alert_confirmed_subject',         'admin_alert_confirmed_body_html',
        'admin_alert_receipt_subject',           'admin_alert_receipt_body_html',
        'admin_alert_cancelled_subject',         'admin_alert_cancelled_body_html',
        'admin_alert_installment2_subject',      'admin_alert_installment2_body_html',
        'admin_alert_installment3_subject',      'admin_alert_installment3_body_html',
        'admin_alert_rental_agreement_subject',  'admin_alert_rental_agreement_body_html',
        // Optional admin BCCs for the cron-driven guest emails (off by default).
        'admin_bcc_welcome_enabled',  'admin_alert_welcome_subject',  'admin_alert_welcome_body_html',
        'admin_bcc_checkin_enabled',  'admin_alert_checkin_subject',  'admin_alert_checkin_body_html',
        'admin_bcc_checkout_enabled', 'admin_alert_checkout_subject', 'admin_alert_checkout_body_html',
    ];

    public function edit()
    {
        $rows = DB::table('basic_settings')
            ->whereIn('name', self::FIELDS)
            ->pluck('value', 'name')
            ->toArray();

        $values = [];
        foreach (self::FIELDS as $f) {
            $values[$f] = $rows[$f] ?? '';
        }

        // Email-type → uploaded attachments map for the inline helper partial.
        // Pre-grouped so the blade renders without N+1 queries (one per type).
        $attachments = \App\Models\EmailAttachment::orderBy('email_type')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->groupBy('email_type');

        return view('admin.settings.stay-communications', $values + ['attachments' => $attachments]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (self::FIELDS as $f) {
            $rules[$f] = str_ends_with($f, '_send_day') ? 'nullable|integer|min:0' : 'nullable|string';
        }
        $validated = $request->validate($rules);

        foreach (self::FIELDS as $f) {
            $value = $validated[$f] ?? '';
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $f],
                ['value' => (string) $value, 'updated_at' => now()]
            );
        }

        return redirect()->back()->with('success', 'Stay communications settings updated.');
    }
}
