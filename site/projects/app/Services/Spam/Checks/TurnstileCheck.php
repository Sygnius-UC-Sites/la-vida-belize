<?php

namespace App\Services\Spam\Checks;

use App\Services\Spam\Result;
use App\Services\Spam\SpamSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TurnstileCheck implements Check
{
    public function __construct(private SpamSettings $settings) {}

    public function run(Request $request, string $form): Result
    {
        if (!$this->settings->turnstileEnabled()) {
            return Result::pass();
        }

        $secret = $this->settings->turnstileSecretKey();
        if ($secret === '') {
            return Result::fail('turnstile_misconfigured', 'Verification failed. Please try again.');
        }

        $token = (string) $request->input('cf-turnstile-response', '');
        if ($token === '') {
            return Result::fail('turnstile_missing', 'Verification failed. Please try again.');
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('spam.turnstile.timeout', 5))
                ->post((string) config('spam.turnstile.verify_url'), [
                    'secret'   => $secret,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (\Throwable $e) {
            return Result::fail('turnstile_network', 'Verification failed. Please try again.');
        }

        if (!$response->successful()) {
            return Result::fail('turnstile_http_error', 'Verification failed. Please try again.');
        }

        $data = $response->json();
        if (!is_array($data) || empty($data['success'])) {
            return Result::fail('turnstile_rejected', 'Verification failed. Please try again.');
        }

        return Result::pass();
    }
}
