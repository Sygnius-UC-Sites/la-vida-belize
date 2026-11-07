<?php

namespace App\Services\Spam\Checks;

use App\Services\Spam\Result;
use App\Services\Spam\SpamSettings;
use Illuminate\Http\Request;

class HoneypotCheck implements Check
{
    public function __construct(private SpamSettings $settings) {}

    public function run(Request $request, string $form): Result
    {
        if (!$this->settings->honeypotEnabled()) {
            return Result::pass();
        }

        $field = (string) config('spam.honeypot.field_name', 'website');
        $value = $request->input($field);

        if ($value !== null && $value !== '') {
            return Result::silentFail('honeypot');
        }

        return Result::pass();
    }
}
