<?php

namespace App\View\Components;

use App\Services\Spam\Checks\TimeTrapCheck;
use App\Services\Spam\SpamSettings;
use Illuminate\View\Component;

class SpamProtection extends Component
{
    public function __construct(public string $form) {}

    public function render()
    {
        $settings      = app(SpamSettings::class);
        $honeypotField = (string) config('spam.honeypot.field_name', 'website');
        $timeTrapField = (string) config('spam.time_trap.field_name', '_form_ts');
        $timeToken     = TimeTrapCheck::generateToken();
        $turnstileOn   = $settings->turnstileEnabled();
        $turnstileKey  = $settings->turnstileSiteKey();
        $honeypotOn    = $settings->honeypotEnabled();
        $timeTrapOn    = $settings->timeTrapEnabled();

        return view('components.spam-protection', compact(
            'honeypotField', 'timeTrapField', 'timeToken',
            'turnstileOn', 'turnstileKey', 'honeypotOn', 'timeTrapOn'
        ));
    }
}
