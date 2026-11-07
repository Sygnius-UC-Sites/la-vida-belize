<?php

namespace App\Mail;

use App\Models\BookingRequest;
use App\Models\Property;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WelcomePackageSent extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public BookingRequest $booking,
        public Property $property,
        public string $descriptionHtml,
        public array $files = []
    ) {}

    public function build()
    {
        // Phase 13D — subject is admin-editable + merge-token-expanded.
        $resolver = new StayCommunicationsResolver($this->property, $this->booking);
        $subjectTpl = (string) (\ModelHelper::getDataFromSetting('welcome_default_subject')
            ?: 'Welcome to {{property_name}} — your stay starts {{check_in}}');
        $subject = $resolver->expandMergeFields($subjectTpl);

        $m = $this->subject($subject)
            ->view('mail.welcome-package-customer')
            ->with([
                'booking'         => $this->booking,
                'data'            => $this->booking,
                'property'        => $this->property,
                'descriptionHtml' => $this->descriptionHtml,
            ]);
        foreach ($this->files as $path) {
            if (is_file($path)) $m->attach($path);
        }
        return $m;
    }
}
