<?php

namespace App\Services\Spam\Checks;

use App\Services\Spam\Result;
use App\Services\Spam\SpamSettings;
use Illuminate\Http\Request;

class TimeTrapCheck implements Check
{
    public function __construct(private SpamSettings $settings) {}

    public function run(Request $request, string $form): Result
    {
        if (!$this->settings->timeTrapEnabled()) {
            return Result::pass();
        }

        $field = (string) config('spam.time_trap.field_name', '_form_ts');
        $min   = $this->settings->timeTrapMinSeconds();
        $max   = (int) config('spam.time_trap.max_seconds', 21600);

        $token = (string) $request->input($field, '');
        $ts    = self::verifyToken($token);

        if ($ts === null) {
            return Result::fail('time_trap_missing', 'Please refresh the page and try again.');
        }

        $age = time() - $ts;
        if ($age < $min) {
            return Result::fail('time_trap_too_fast', 'Please refresh the page and try again.');
        }
        if ($age > $max) {
            return Result::fail('time_trap_stale', 'Please refresh the page and try again.');
        }

        return Result::pass();
    }

    public static function generateToken(?int $now = null): string
    {
        $ts  = (int) ($now ?? time());
        $sig = hash_hmac('sha256', (string) $ts, self::secret());
        return $ts . '.' . $sig;
    }

    public static function verifyToken(string $token): ?int
    {
        if ($token === '' || !str_contains($token, '.')) {
            return null;
        }
        [$ts, $sig] = explode('.', $token, 2);
        if (!ctype_digit($ts)) {
            return null;
        }
        $expected = hash_hmac('sha256', $ts, self::secret());
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        return (int) $ts;
    }

    private static function secret(): string
    {
        $key = (string) config('app.key', '');
        return $key !== '' ? $key : 'spam-time-trap-fallback';
    }
}
