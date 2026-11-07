<?php

namespace App\Mail;

use App\Models\BookingRequest;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RentalAgreementSigned extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BookingRequest $booking,
        public Model $property,
        public string $pdfPath,
        public string $audience  // 'user' | 'admin'
    ) {}

    public function build()
    {
        // Phase 5 — both audiences now use the email-shell + admin-editable
        // BasicSetting templates so the look matches the rest of the
        // lifecycle and the copy is editable at /admin/settings/stay-communications.
        $resolver = new StayCommunicationsResolver($this->property, $this->booking);
        $brand    = \ModelHelper::getDataFromSetting('mailer_admin_name')
                 ?: \ModelHelper::getDataFromSetting('mail_from_name')
                 ?: \ModelHelper::getDataFromSetting('owner_name')
                 ?: 'Notification';
        $footer   = \ModelHelper::getDataFromSetting('mail_footer') ?: '';

        if ($this->audience === 'admin') {
            $subjectKey      = 'admin_alert_rental_agreement_subject';
            $bodyKey         = 'admin_alert_rental_agreement_body_html';
            $subjectFallback = 'Rental agreement signed — booking #{{booking_id}}';
            $bodyFallback    = '<p>Guest signed rental agreement for booking #{{booking_id}}.</p><p><a href="{{admin_url}}">View →</a></p>';
        } else {
            $subjectKey      = 'rental_agreement_signed_default_subject';
            $bodyKey         = 'rental_agreement_signed_default_body_html';
            $subjectFallback = 'Your rental agreement is signed — {{property_name}}';
            $bodyFallback    = '<p>Hi {{guest_name}},</p><p>Your signed rental agreement for {{property_name}} is attached.</p><p>{{brand}}</p>';
        }

        $subject = $resolver->expandMergeFields(
            (string) (\ModelHelper::getDataFromSetting($subjectKey) ?: $subjectFallback)
        );
        $body = $resolver->expandMergeFields(
            (string) (\ModelHelper::getDataFromSetting($bodyKey) ?: $bodyFallback)
        );

        return $this->subject($subject)
            ->view('mail.email-shell')
            ->with([
                'body_html_rendered' => $body,
                'brand'              => $brand,
                'mail_footer'        => $footer,
            ])
            ->attachFromStorageDisk('local', $this->pdfPath, 'rental-agreement-' . $this->booking->id . '.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
