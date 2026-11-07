<?php

namespace App\Services\Email;

use App\Models\BookingRequest;
use App\Models\Payment;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use ModelHelper;
use MailHelper;

final class BookingLifecycleMailer
{
    /**
     * Send the four messages represented by the admin templates after a
     * successful first payment: guest confirmation, owner confirmation,
     * guest receipt, and owner receipt.
     */
    public function sendConfirmationAndReceipt(
        BookingRequest $booking,
        Model $property,
        ?Payment $payment = null,
    ): array {
        $resolver = new StayCommunicationsResolver($property, $booking);
        $payment ??= Payment::where('booking_id', $booking->id)
            ->where('status', 'complete')
            ->latest('id')
            ->first();

        $results = [];
        $results['confirmed'] = $this->sendTemplate(
            booking: $booking,
            property: $property,
            resolver: $resolver,
            type: 'confirmed',
            recipient: (string) $booking->email,
            subjectKey: 'confirmed_default_subject',
            bodyKey: 'confirmed_default_body_html',
            subjectFallback: 'Booking confirmed: {{property_name}}',
            bodyFallback: '<p>Hi {{guest_name}},</p><p>Your booking is confirmed.</p>',
            attachments: $resolver->attachmentsFor('confirmed'),
            includeQuote: true,
        );

        if ($this->requiresAgreementRequest($booking, $property)) {
            $signLink = (string) URL::temporarySignedRoute(
                'rental-agreement.sign-link',
                now()->addDays(14),
                ['id' => $booking->id]
            );

            $results['rental-agreement-request'] = $this->sendTemplate(
                booking: $booking,
                property: $property,
                resolver: $resolver,
                type: 'rental-agreement-request',
                recipient: (string) $booking->email,
                subjectKey: 'rental_agreement_request_default_subject',
                bodyKey: 'rental_agreement_request_default_body_html',
                subjectFallback: 'Please sign your rental agreement for {{property_name}}',
                bodyFallback: '<p>Hi {{guest_name}},</p><p>Your booking at <strong>{{property_name}}</strong> is confirmed.</p><p>Please sign the rental agreement before check-in:</p><p><a href="{{rental_agreement_link}}">Sign agreement</a></p>',
                extras: ['{{rental_agreement_link}}' => $signLink],
            );
        }

        $owner = ModelHelper::getDataFromSetting('payment_receiving_mail')
            ?: ModelHelper::getDataFromSetting('booking_receiving_mail')
            ?: ModelHelper::getDataFromSetting('admin_email_primary');
        if ($owner) {
            $results['admin-confirmed'] = $this->sendTemplate(
                booking: $booking,
                property: $property,
                resolver: $resolver,
                type: 'admin-confirmed',
                recipient: (string) $owner,
                subjectKey: 'admin_alert_confirmed_subject',
                bodyKey: 'admin_alert_confirmed_body_html',
                subjectFallback: '[Booking confirmed] #{{booking_id}} - {{property_name}}',
                bodyFallback: '<p>Booking #{{booking_id}} is confirmed.</p><p><a href="{{admin_url}}">Open booking</a></p>',
                includeQuote: true,
            );
        }

        if ($payment) {
            $extras = $this->paymentTokens($booking, $payment);
            $results['receipt'] = $this->sendTemplate(
                booking: $booking,
                property: $property,
                resolver: $resolver,
                type: 'receipt',
                recipient: (string) $booking->email,
                subjectKey: 'receipt_default_subject',
                bodyKey: 'receipt_default_body_html',
                subjectFallback: 'Payment receipt - {{property_name}}',
                bodyFallback: '<p>We received {{payment_amount}} for booking #{{booking_id}}.</p>',
                extras: $extras,
                attachments: $resolver->attachmentsFor('receipt'),
                includeQuote: true,
            );

            if ($owner) {
                $results['admin-receipt'] = $this->sendTemplate(
                    booking: $booking,
                    property: $property,
                    resolver: $resolver,
                    type: 'admin-receipt',
                    recipient: (string) $owner,
                    subjectKey: 'admin_alert_receipt_subject',
                    bodyKey: 'admin_alert_receipt_body_html',
                    subjectFallback: '[Payment received {{payment_amount}}] #{{booking_id}}',
                    bodyFallback: '<p>{{payment_amount}} received for booking #{{booking_id}}.</p>',
                    extras: $extras,
                    includeQuote: true,
                );
            }
        }

        return $results;
    }

    private function requiresAgreementRequest(BookingRequest $booking, Model $property): bool
    {
        return method_exists($property, 'requiresPostConfirmationSigning')
            && $property->requiresPostConfirmationSigning()
            && ($booking->rental_aggrement_status ?? 'false') !== 'true'
            && SendLog::latestFor((int) $booking->id, 'rental-agreement-request') === null;
    }

    public function sendCancellation(BookingRequest $booking, Model $property): array
    {
        $resolver = new StayCommunicationsResolver($property, $booking);
        $results = [];

        if ($booking->email && $booking->email !== 'guest@pms.com') {
            $results['cancellation'] = $this->sendTemplate(
                booking: $booking,
                property: $property,
                resolver: $resolver,
                type: 'cancellation',
                recipient: (string) $booking->email,
                subjectKey: 'cancellation_default_subject',
                bodyKey: 'cancellation_default_body_html',
                subjectFallback: 'Your booking at {{property_name}} has been cancelled',
                bodyFallback: '<p>Hi {{guest_name}},</p><p>Your booking has been cancelled.</p>',
                attachments: $resolver->attachmentsFor('cancellation'),
                includeQuote: true,
            );
        }

        $owner = ModelHelper::getDataFromSetting('cancel_receiving_mail')
            ?: ModelHelper::getDataFromSetting('payment_receiving_mail')
            ?: ModelHelper::getDataFromSetting('admin_email_primary');
        if ($owner) {
            $results['admin-cancellation'] = $this->sendTemplate(
                booking: $booking,
                property: $property,
                resolver: $resolver,
                type: 'admin-cancellation',
                recipient: (string) $owner,
                subjectKey: 'admin_alert_cancelled_subject',
                bodyKey: 'admin_alert_cancelled_body_html',
                subjectFallback: '[Cancelled] #{{booking_id}} - {{property_name}}',
                bodyFallback: '<p>Booking #{{booking_id}} was cancelled.</p><p><a href="{{admin_url}}">Open booking</a></p>',
                includeQuote: true,
            );
        }

        return $results;
    }

    private function sendTemplate(
        BookingRequest $booking,
        Model $property,
        StayCommunicationsResolver $resolver,
        string $type,
        string $recipient,
        string $subjectKey,
        string $bodyKey,
        string $subjectFallback,
        string $bodyFallback,
        array $extras = [],
        array $attachments = [],
        bool $includeQuote = false,
    ): bool {
        $subjectTemplate = (string) (ModelHelper::getDataFromSetting($subjectKey) ?: $subjectFallback);
        $bodyTemplate = (string) (ModelHelper::getDataFromSetting($bodyKey) ?: $bodyFallback);
        $subject = $resolver->expandMergeFields($subjectTemplate, $extras);
        $body = $includeQuote
            ? $resolver->expandMergeFieldsWithQuote($bodyTemplate, $extras)
            : $resolver->expandMergeFields($bodyTemplate, $extras);

        $html = view('mail.email-shell', [
            'body_html_rendered' => $body,
            'brand' => ModelHelper::getDataFromSetting('mailer_admin_name')
                ?: ModelHelper::getDataFromSetting('mail_from_name')
                ?: ModelHelper::getDataFromSetting('owner_name')
                ?: 'Notification',
            'mail_footer' => ModelHelper::getDataFromSetting('mail_footer') ?: '',
            'booking' => $booking,
            'property' => $property,
        ])->render();

        $error = null;
        try {
            $sent = MailHelper::emailSenderByController($html, $recipient, $subject, $attachments);
            if (!$sent) {
                $error = 'Mail provider did not accept the message.';
            }
        } catch (\Throwable $e) {
            $sent = false;
            $error = $e->getMessage();
            Log::error('Booking lifecycle email failed', [
                'booking_id' => $booking->id,
                'type' => $type,
                'recipient' => $recipient,
                'error' => $error,
            ]);
        }

        SendLog::record(
            (int) $booking->id,
            $type,
            $recipient,
            $subject,
            $html,
            $error,
        );

        return $sent;
    }

    private function paymentTokens(BookingRequest $booking, Payment $payment): array
    {
        $currency = (string) (ModelHelper::getDataFromSetting('payment_currency') ?: '$');
        $paid = (float) DB::table('payments')
            ->where('booking_id', $booking->id)
            ->where('status', 'complete')
            ->sum('amount');
        $outstanding = max(0, (float) $booking->total_amount - $paid);

        return [
            '{{payment_amount}}' => $currency . number_format((float) $payment->amount, 2),
            '{{payment_date}}' => ($payment->created_at ?: now())->format('M j, Y · g:i A'),
            '{{payment_method}}' => strtoupper((string) ($payment->type ?: 'card')),
            '{{transaction_id}}' => (string) ($payment->tran_id ?? ''),
            '{{receipt_url}}' => (string) ($payment->receipt_url ?? ''),
            '{{outstanding_balance}}' => $outstanding > 0
                ? $currency . number_format($outstanding, 2) . ' remaining'
                : 'Paid in full',
        ];
    }
}
