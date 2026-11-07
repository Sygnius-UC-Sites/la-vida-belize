<?php

namespace App\Console\Commands;

use App\Models\BookingRequest;
use App\Models\Property;
use Illuminate\Console\Command;

class SendWelcomeEmails extends Command
{
    protected $signature = 'bookings:send-welcome-emails';
    protected $description = 'Send welcome-package emails to bookings whose check-in is N days away (per-property override → global welcome_package_send_day).';

    public function handle(): int
    {
        $today = date('Y-m-d');
        $horizon = date('Y-m-d', strtotime('+90 days'));

        $candidates = BookingRequest::where('booking_status', 'booked')
            ->where('welcome_email', 'false')
            ->whereBetween('checkin', [$today, $horizon])
            ->get();

        $sent = 0;
        foreach ($candidates as $booking) {
            $property = Property::find($booking->property_id);
            if (!$property) continue;

            $resolver = new \App\Services\Stay\StayCommunicationsResolver($property, $booking);
            $offsetDays = $resolver->resolveSendDayOffset('welcome_email_send_day', 'welcome_package_send_day', 0);
            $targetDate = date('Y-m-d', strtotime("{$booking->checkin} -{$offsetDays} days"));

            if ($targetDate !== $today) continue;

            try {
                $payload = $resolver->resolveWelcomePayload();
                $sendError = null;
                $mailable = new \App\Mail\WelcomePackageSent($booking, $property, $payload['description_html'], $payload['files']);
                $rendered = $mailable->render(); // capture HTML for SendLog snapshot
                $subject  = $resolver->expandMergeFields((string) (\ModelHelper::getDataFromSetting('welcome_default_subject') ?: 'Welcome to {{property_name}} — your stay starts {{check_in}}'));
                try {
                    \MailHelper::emailSenderByController($rendered, $booking->email, $subject);

                    // Phase 13E — optional admin BCC, gated on admin_bcc_welcome_enabled.
                    if (\ModelHelper::getDataFromSetting('admin_bcc_welcome_enabled') === '1') {
                        $this->sendAdminBccAlert($booking, $property, $resolver,
                            'admin_alert_welcome_subject', 'admin_alert_welcome_body_html',
                            'Welcome email sent — booking #{{booking_id}} ({{property_name}})');
                    }
                } catch (\Throwable $mailErr) {
                    $sendError = $mailErr->getMessage();
                    logger()->warning('SendWelcomeEmails: mail send failed', [
                        'booking_id' => $booking->id, 'error' => $sendError,
                    ]);
                }
                // Phase 13I — record attempt + outcome. Only flip welcome_email='true'
                // on actual success so failures stay visible (red dot) until an admin
                // re-fires the send manually.
                $update = [
                    'welcome_email_attempted_at' => now(),
                    'welcome_email_error' => $sendError,
                ];
                if ($sendError === null) {
                    $update['welcome_email'] = 'true';
                    $update['welcome_email_sent_at'] = now();
                }
                $booking->update($update);
                // Phase 13M — snapshot what was sent (or what was attempted, if it failed).
                \App\Services\Email\SendLog::record((int)$booking->id, 'welcome', (string)$booking->email, $subject, $rendered, $sendError);
                if ($sendError === null) $sent++;
            } catch (\Throwable $e) {
                $this->error("Booking {$booking->id} failed: " . $e->getMessage());
                logger()->error('SendWelcomeEmails failed', ['booking_id' => $booking->id, 'exception' => $e]);
            }
        }

        $this->info("Sent {$sent} welcome emails (matched {$candidates->count()} candidates within {$today}..{$horizon})");
        return self::SUCCESS;
    }

    /**
     * Phase 13E — shared helper for the optional admin-BCC alert sent alongside
     * a guest-facing cron email. Pulls subject + body from BasicSetting (editable
     * on /admin/settings/stay-communications → Owner alerts tab).
     */
    private function sendAdminBccAlert(
        BookingRequest $booking,
        Property $property,
        \App\Services\Stay\StayCommunicationsResolver $resolver,
        string $subjectKey,
        string $bodyKey,
        string $defaultSubject
    ): void {
        $adminTo = \ModelHelper::getDataFromSetting('booking_receiving_mail')
                ?: \ModelHelper::getDataFromSetting('admin_email_primary');
        if (!$adminTo) return;

        $brand  = \ModelHelper::getDataFromSetting('owner_name')
               ?: \ModelHelper::getDataFromSetting('mailer_admin_name')
               ?: \ModelHelper::getDataFromSetting('mail_from_name')
               ?: 'Notification';
        $footer = \ModelHelper::getDataFromSetting('mail_footer') ?: '';

        $subject = $resolver->expandMergeFields((string) (\ModelHelper::getDataFromSetting($subjectKey) ?: $defaultSubject));
        $body    = $resolver->expandMergeFields((string) (\ModelHelper::getDataFromSetting($bodyKey) ?: '<p>{{property_name}} — booking #{{booking_id}}.</p><p><a href="{{admin_url}}">View →</a></p>'));
        $html    = view('mail.email-shell', ['body_html_rendered' => $body, 'brand' => $brand, 'mail_footer' => $footer])->render();

        \MailHelper::emailSenderByController($html, $adminTo, $subject);
    }
}
