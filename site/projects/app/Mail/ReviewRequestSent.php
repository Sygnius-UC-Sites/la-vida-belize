<?php

namespace App\Mail;

use App\Models\BookingRequest;
use App\Models\Property;
use App\Services\Stay\ReviewLinkBuilder;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReviewRequestSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BookingRequest $booking,
        public Property $property,
        public string $token
    ) {}

    public function build()
    {
        // Phase 13D — fully admin-editable: subject + body via merge tokens.
        $resolver = new StayCommunicationsResolver($this->property, $this->booking);
        $extras   = ['{{review_link}}' => ReviewLinkBuilder::build($this->token)];

        $subjectTpl = (string) (\ModelHelper::getDataFromSetting('review_default_subject')
            ?: 'How was your stay at {{property_name}}?');
        $bodyTpl    = (string) (\ModelHelper::getDataFromSetting('review_default_body_html')
            ?: '<p>Hi {{guest_name}},</p><p>Thanks for staying at {{property_name}}. Reply with any thoughts.</p><p>{{brand}}</p>');

        $subject = $resolver->expandMergeFields($subjectTpl, $extras);
        $body    = $resolver->expandMergeFields($bodyTpl, $extras);

        $brand   = \ModelHelper::getDataFromSetting('mailer_admin_name')
                ?: \ModelHelper::getDataFromSetting('mail_from_name')
                ?: \ModelHelper::getDataFromSetting('owner_name')
                ?: 'Notification';
        $footer  = \ModelHelper::getDataFromSetting('mail_footer') ?: '';

        $mail = $this->subject($subject)
            ->view('mail.email-shell')
            ->with([
                'body_html_rendered' => $body,
                'brand'              => $brand,
                'mail_footer'        => $footer,
            ]);
        foreach (\App\Models\EmailAttachment::pathsFor('review') as $att) {
            $mail->attach($att['path'], ['as' => $att['name']]);
        }
        return $mail;
    }
}
