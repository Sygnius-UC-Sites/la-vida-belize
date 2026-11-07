<?php

namespace App\Mail;

use App\Models\BookingRequest;
use App\Models\Property;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CheckinInfoSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BookingRequest $booking,
        public Property $property,
        public array $payload
    ) {}

    public function build()
    {
        // Phase 13D — admin-editable subject (Guest Emails → Pre-stay tab → Check-in info).
        $resolver = new StayCommunicationsResolver($this->property, $this->booking);
        $subjectTpl = (string) (\ModelHelper::getDataFromSetting('checkin_default_subject')
            ?: 'Check-in details for your stay at {{property_name}}');
        $subject = $resolver->expandMergeFields($subjectTpl);

        $mail = $this->subject($subject)
            ->view('mail.booking-checkin-info')
            ->with([
                'booking' => $this->booking,
                'data'    => $this->booking,
                'property'=> $this->property,
                'p'       => $this->payload,
            ]);
        // Admin-managed attachments uploaded via /admin/email-templates → checkin.
        foreach (\App\Models\EmailAttachment::pathsFor('checkin') as $att) {
            $mail->attach($att['path'], ['as' => $att['name']]);
        }
        return $mail;
    }
}
