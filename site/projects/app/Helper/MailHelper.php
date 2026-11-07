<?php
namespace App\Helper;
use Auth;
use DB;
use App\Models\BasicSetting;
use App\Models\EmailTemplete;
use Mail;
use Config;
use ModelHelper;
use App\Services\Mail\SesMailer;
use App\Services\Mail\SesSettings;

/**
 * Backwards-compatible wrapper around the SES module.
 *
 * Existing callsites continue calling `MailHelper::emailSenderByController(...)`
 * unchanged. When SES is configured (domain + access key set), the send is
 * routed through SesMailer. Otherwise it falls back to the legacy
 * Mail::send() / SMTP path for installs that haven't migrated yet.
 */
class MailHelper{

    function emailSenderByController($html, $to, $subject, $files = []): bool {
        $sesSettings = app(SesSettings::class);

        // Prefer SES when minimally configured: domain + a from address present.
        $useSes = $sesSettings->enabled()
               && $sesSettings->domain() !== ''
               && $sesSettings->fromEmail() !== ''
               && $sesSettings->awsAccessKeyId() !== '';

        if ($useSes && empty($files)) {
            // SesMailer handles validation, suppression, rate-limiting,
            // domain enforcement, logging, and never throws on failure.
            // First recipient only — comma-separated lists fan out below.
            $recipients = is_array($to) ? $to : array_filter(array_map('trim', explode(',', (string) $to)));
            $purpose = 'transactional';
            $allSent = !empty($recipients);
            foreach ($recipients as $recipient) {
                $result = app(SesMailer::class)->send($recipient, $subject, $html, ['purpose' => $purpose]);
                $allSent = $allSent && $result->success;
            }
            return $allSent;
        }

        // Legacy SMTP fallback. Attachments path runs through here too —
        // SES SimpleEmail doesn't support attachments without using raw MIME,
        // and none of the current callsites use attachments + SES together.
        $data = ['email_body' => $html];
        $objDemo = new \stdClass();
        $objDemo->to = is_array($to) ? array_values(array_filter($to)) : array_values(array_filter(array_map('trim', explode(',', (string) $to))));
        $objDemo->from = ModelHelper::getDataFromSetting("mail_from") ?: config('mail.from.address');
        $objDemo->title = ModelHelper::getDataFromSetting("mail_from_name") ?: config('mail.from.name');
        $objDemo->subject = $subject;
        if (empty($objDemo->to) || !$objDemo->from) {
            return false;
        }
        try{
            Mail::send('mail.dummyMail', $data, function ($message) use ($objDemo, $files) {
                $message->from($objDemo->from, $objDemo->title);
                $message->to($objDemo->to);
                $message->subject($objDemo->subject);
                if (count($files) > 0) {
                    foreach ($files as $file) {
                        $message->attach($file);
                    }
                }
            });
            $this->logLegacySmtpSend($objDemo, 'sent');
            return true;
        } catch (\Throwable $e) {
            \Log::warning('SMTP mail send failed', [
                'to' => $objDemo->to,
                'subject' => $objDemo->subject,
                'error' => $e->getMessage(),
            ]);
            $this->logLegacySmtpSend($objDemo, 'failed', $e->getMessage());
            return false;
        }
    }

    private function logLegacySmtpSend(\stdClass $mail, string $status, ?string $error = null): void
    {
        try {
            foreach ($mail->to as $recipient) {
                DB::table('mail_send_log')->insert([
                    'purpose' => 'transactional',
                    'from_email' => (string) $mail->from,
                    'to_email' => (string) $recipient,
                    'subject' => mb_substr((string) $mail->subject, 0, 500),
                    'status' => $status,
                    'error_code' => $error ? 'smtp_exception' : null,
                    'error_message' => $error,
                    'retry_eligible' => $error !== null,
                    'retry_count' => 0,
                    'sent_at' => $status === 'sent' ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning('mail_send_log insert failed for SMTP fallback', [
                'error' => $e->getMessage(),
                'status' => $status,
                'subject' => (string) $mail->subject,
            ]);
        }
    }

    public function mailsendbydbdata($name)
    {
        $row = BasicSetting::where('name', $name)->first();
        return $row ? $row->value : null;
    }
}
