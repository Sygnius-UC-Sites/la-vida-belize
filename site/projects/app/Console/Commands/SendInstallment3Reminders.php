<?php

namespace App\Console\Commands;

use App\Models\BookingRequest;
use App\Models\Property;
use Illuminate\Console\Command;
use MailHelper;
use ModelHelper;

class SendInstallment3Reminders extends Command
{
    protected $signature = 'bookings:send-installment-3-reminders';
    protected $description = 'Send installment-3 due-soon reminder emails for 3-installment plans where installment 2 has been paid.';

    public function handle(): int
    {
        $offsetDays = (int) ModelHelper::getDataFromSetting('third_how_many_days');
        $targetDate = date('Y-m-d', strtotime("+{$offsetDays} days"));

        $bookings = BookingRequest::where([
            'booking_status' => 'booked',
            'payment_status' => 'partially',
            'total_payment' => 3,
            'how_many_payment_done' => 2,
            'third_reminder_email' => 'false',
        ])->where('checkin', $targetDate)->get();

        $sent = 0;
        foreach ($bookings as $booking) {
            $property = Property::find($booking->property_id);
            if (!$property) continue;

            try {
                // Phase 13D — installment-3 reminder is admin-editable on the Guest Emails page.
                $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
                $brand    = ModelHelper::getDataFromSetting('mailer_admin_name')
                         ?: ModelHelper::getDataFromSetting('mail_from_name')
                         ?: ModelHelper::getDataFromSetting('owner_name')
                         ?: 'Notification';
                $footer   = ModelHelper::getDataFromSetting('mail_footer') ?: '';
                $currency = ModelHelper::getDataFromSetting('payment_currency') ?: '$';

                $magicLink = (string) \URL::temporarySignedRoute('booking.pay-link', now()->addDays(7), ['id' => $booking->id]);
                $extras = [
                    '{{magic_link}}'           => $magicLink,
                    '{{installment_number}}'   => '3',
                    '{{installment_amount}}'   => $currency . number_format((float)($booking->third_payment_amount ?? 0), 2),
                    '{{installment_due_date}}' => $targetDate,
                ];

                $subjectTpl = (string) (ModelHelper::getDataFromSetting('installment3_default_subject')
                    ?: 'Final payment reminder: installment {{installment_number}} due {{installment_due_date}}');
                $bodyTpl    = (string) (ModelHelper::getDataFromSetting('installment3_default_body_html')
                    ?: '<p>Hi {{guest_name}},</p><p>Final installment for {{property_name}} is due {{installment_due_date}}.</p><p><a href="{{magic_link}}">Pay now</a></p>');

                $subject      = $resolver->expandMergeFields($subjectTpl, $extras);
                $body         = $resolver->expandMergeFields($bodyTpl, $extras);
                $customerHtml = view('mail.email-shell', ['body_html_rendered' => $body, 'brand' => $brand, 'mail_footer' => $footer])->render();

                if (!MailHelper::emailSenderByController($customerHtml, $booking->email, $subject)) {
                    throw new \RuntimeException('Guest reminder email could not be delivered.');
                }
                \App\Services\Email\SendLog::record((int)$booking->id, 'installment3', (string)$booking->email, $subject, $customerHtml);

                $adminTo = ModelHelper::getDataFromSetting('reminder_package_receiving_mail') ?: ModelHelper::getDataFromSetting('admin_email_primary');
                if ($adminTo) {
                    // Phase 13E — admin alert editable on Email Templates → Owner alerts → Final installment.
                    $adminSubject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('admin_alert_installment3_subject') ?: 'Final installment reminder sent — booking #{{booking_id}} ({{property_name}})'), $extras);
                    $adminBody    = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('admin_alert_installment3_body_html') ?: '<p>Final installment reminder sent for booking #{{booking_id}}.</p>'), $extras);
                    $adminHtml    = view('mail.email-shell', ['body_html_rendered' => $adminBody, 'brand' => $brand, 'mail_footer' => $footer])->render();
                    MailHelper::emailSenderByController($adminHtml, $adminTo, $adminSubject);
                }

                $booking->update(['third_reminder_email' => 'true']);
                $sent++;
            } catch (\Throwable $e) {
                $this->error("Booking {$booking->id} failed: " . $e->getMessage());
                logger()->error('SendInstallment3Reminders failed', ['booking_id' => $booking->id, 'exception' => $e]);
            }
        }

        $this->info("Sent {$sent} installment-3 reminders (target check-in date {$targetDate})");
        return self::SUCCESS;
    }
}
