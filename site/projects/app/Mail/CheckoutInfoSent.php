<?php

namespace App\Mail;

use App\Models\BookingRequest;
use App\Models\Property;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CheckoutInfoSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BookingRequest $booking,
        public Property $property,
        public array $payload
    ) {}

    public function build()
    {
        // Phase 13D — admin-editable subject (Guest Emails → Pre-stay tab → Check-out info).
        $resolver = new StayCommunicationsResolver($this->property, $this->booking);
        $subjectTpl = (string) (\ModelHelper::getDataFromSetting('checkout_default_subject')
            ?: 'Check-out details for your stay at {{property_name}}');
        $subject = $resolver->expandMergeFields($subjectTpl);

        $mail = $this->subject($subject)
            ->view('mail.booking-checkout-info')
            ->with([
                'booking' => $this->booking,
                'data'    => $this->booking,
                'property'=> $this->property,
                'p'       => $this->payload,
            ]);
        foreach (\App\Models\EmailAttachment::pathsFor('checkout') as $att) {
            $mail->attach($att['path'], ['as' => $att['name']]);
        }
        return $mail;
    }
}
