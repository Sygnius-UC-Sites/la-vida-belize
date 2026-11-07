<?php

namespace App\Services\Spam\Checks;

use App\Services\Spam\Result;
use App\Services\Spam\SpamSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitCheck implements Check
{
    public function __construct(private SpamSettings $settings) {}

    public function run(Request $request, string $form): Result
    {
        if (!$this->settings->rateLimitEnabled()) {
            return Result::pass();
        }

        $max = $this->settings->rateLimitMax($form);
        $key = 'spam:' . $form . ':' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, $max)) {
            return Result::fail('rate_limit', 'Too many requests. Please try again in a minute.');
        }

        RateLimiter::hit($key, 60);
        return Result::pass();
    }
}
