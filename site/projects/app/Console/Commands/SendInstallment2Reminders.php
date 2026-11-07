<?php

namespace App\Console\Commands;

use App\Models\BookingRequest;
use App\Models\Property;
use Illuminate\Console\Command;
use MailHelper;
use ModelHelper;

class SendInstallment2Reminders extends Command
{
    protected $signature = 'bookings:send-installment-2-reminders';
    protected $description = 'Send installment-2 due-soon reminder emails. Covers 2-installment plans (second_how_many_days timing) and 3-installment plans not-yet-installment-2-paid (second_third_how_many_days timing).';

    public function handle(): int
    {
        $sent = 0;

        // Chunk 1: 2-installment plans, installment 2 due in 'second_how_many_days' days
        $offset1 = (int) ModelHelper::getDataFromSetting('second_how_many_days');
        $sent += $this->sendForOffset(
            offsetDays: $offset1,
            extraWhere: ['total_payment' => 2, 'how_many_payment_done' => 1],
            label: '2-installment'
        );

        // Chunk 2: 3-installment plans where customer hasn't paid installment 2 yet
        $offset2 = (int) ModelHelper::getDataFromSetting('second_third_how_many_days');
        $sent += $this->sendForOffset(
            offsetDays: $offset2,
            extraWhere: ['total_payment' => 3, 'how_many_payment_done' => 1, 'third_reminder_email' => 'false'],
            label: '3-installment-pending-2'
        );

        $this->info("Sent {$sent} installment-2 reminders");
        return self::SUCCESS;
    }

    private function sendForOffset(int $offsetDays, array $extraWhere, string $label): int
    {
        $targetDate = date('Y-m-d', strtotime("+{$offsetDays} days"));
        $base = ['booking_status' => 'booked', 'payment_status' => 'partially', 'reminder_email' => 'false'];
        $bookings = BookingRequest::where(array_merge($base, $extraWhere))
            ->where('checkin', $targetDate)
            ->get();

        $sent = 0;
        foreach ($bookings as $booking) {
            $property = Property::find($booking->property_id);
            if (!$property) continue;

            try {
                $files = [];
                if ($property->welcome_package_attachment) {
                    $files[] = public_path($property->welcome_package_attachment);
                }

                // Phase 13D — installment-2 reminder is admin-editable on the Guest Emails page.
                $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
                $brand    = ModelHelper::getDataFromSetting('mailer_admin_name')
                         ?: ModelHelper::getDataFromSetting('mail_from_name')
                         ?: ModelHelper::getDataFromSetting('owner_name')
                         ?: 'Notification';
                $footer   = ModelHelper::getDataFromSetting('mail_footer') ?: '';
                $currency = ModelHelper::getDataFromSetting('payment_currency') ?: '$';

                // Magic link for completing this installment.
                $magicLink = (string) \URL::temporarySignedRoute('booking.pay-link', now()->addDays(7), ['id' => $booking->id]);
                // Per-installment context for tokens.
                $extras = [
                    '{{magic_link}}'           => $magicLink,
                    '{{installment_number}}'   => '2',
                    '{{installment_amount}}'   => $currency . number_format((float)($booking->second_payment_amount ?? 0), 2),
                    '{{installment_due_date}}' => date('F j, Y', strtotime("+{$offsetDays} days")),
                ];

                $subjectTpl = (string) (ModelHelper::getDataFromSetting('installment2_default_subject')
                    ?: 'Payment reminder: installment {{installment_number}} due {{installment_due_date}}');
                $bodyTpl    = (string) (ModelHelper::getDataFromSetting('installment2_default_body_html')
                    ?: '<p>Hi {{guest_name}},</p><p>Reminder: installment {{installment_number}} for {{property_name}} is due {{installment_due_date}}.</p><p><a href="{{magic_link}}">Pay now</a></p>');

                $subject      = $resolver->expandMergeFields($subjectTpl, $extras);
                $body         = $resolver->expandMergeFields($bodyTpl, $extras);
                $customerHtml = view('mail.email-shell', ['body_html_rendered' => $body, 'brand' => $brand, 'mail_footer' => $footer])->render();

                if (!MailHelper::emailSenderByController($customerHtml, $booking->email, $subject, $files)) {
                    throw new \RuntimeException('Guest reminder email could not be delivered.');
                }
                \App\Services\Email\SendLog::record((int)$booking->id, 'installment2', (string)$booking->email, $subject, $customerHtml);

                // Admin alert — brief summary
                $adminTo = ModelHelper::getDataFromSetting('reminder_package_receiving_mail') ?: ModelHelper::getDataFromSetting('admin_email_primary');
                if ($adminTo) {
                    // Phase 13E — admin alert editable on Email Templates → Owner alerts → Installment 2.
                    $adminSubject = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('admin_alert_installment2_subject') ?: 'Installment 2 reminder sent — booking #{{booking_id}} ({{property_name}})'), $extras);
                    $adminBody    = $resolver->expandMergeFields((string) (ModelHelper::getDataFromSetting('admin_alert_installment2_body_html') ?: '<p>Installment 2 reminder sent for booking #{{booking_id}}.</p>'), $extras);
                    $adminHtml    = view('mail.email-shell', ['body_html_rendered' => $adminBody, 'brand' => $brand, 'mail_footer' => $footer])->render();
                    MailHelper::emailSenderByController($adminHtml, $adminTo, $adminSubject, $files);
                }

                $booking->update(['reminder_email' => 'true']);
                $sent++;
            } catch (\Throwable $e) {
                $this->error("Booking {$booking->id} ({$label}) failed: " . $e->getMessage());
                logger()->error('SendInstallment2Reminders failed', ['booking_id' => $booking->id, 'label' => $label, 'exception' => $e]);
            }
        }

        return $sent;
    }
}
