<?php

namespace App\Services\Spam;

use App\Services\Spam\Checks\Check;
use App\Services\Spam\Checks\DisposableEmailCheck;
use App\Services\Spam\Checks\HoneypotCheck;
use App\Services\Spam\Checks\RateLimitCheck;
use App\Services\Spam\Checks\TimeTrapCheck;
use App\Services\Spam\Checks\TurnstileCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SpamProtection
{
    /** @var list<Check> */
    private array $checks;

    public function __construct(
        HoneypotCheck $honeypot,
        TimeTrapCheck $timeTrap,
        RateLimitCheck $rateLimit,
        TurnstileCheck $turnstile,
        DisposableEmailCheck $disposable,
    ) {
        // Order matters: cheapest + silent checks first; disposable-email last
        // (only meaningful once we trust the request enough to bother validating its email).
        $this->checks = [$honeypot, $timeTrap, $rateLimit, $turnstile, $disposable];
    }

    public function check(Request $request, string $form): Result
    {
        foreach ($this->checks as $check) {
            $result = $check->run($request, $form);
            if (!$result->passed) {
                $this->log($request, $form, $result);
                return $result;
            }
        }
        return Result::pass();
    }

    private function log(Request $request, string $form, Result $result): void
    {
        $emailField = config("spam.forms.$form.email_field");
        $context = [
            'form'       => $form,
            'reason'     => $result->reason,
            'silent'     => $result->silent,
            'ip'         => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'email'      => $emailField ? $request->input($emailField) : null,
            'request_id' => substr(bin2hex(random_bytes(6)), 0, 12),
        ];

        try {
            Log::channel((string) config('spam.log_channel', 'spam'))->info('spam_rejected', $context);
        } catch (\Throwable $e) {
            // If the dedicated channel is misconfigured, fall back to default.
            Log::warning('spam_rejected (fallback)', $context);
        }
    }
}
