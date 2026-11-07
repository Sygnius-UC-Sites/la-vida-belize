<?php

namespace App\Services\Spam\Checks;

use App\Services\Spam\Result;
use App\Services\Spam\SpamSettings;
use Illuminate\Http\Request;

class DisposableEmailCheck implements Check
{
    /** @var array<string, true>|null */
    private static ?array $fileCache = null;

    public function __construct(private SpamSettings $settings) {}

    public function run(Request $request, string $form): Result
    {
        if (!$this->settings->disposableEmailEnabled()) {
            return Result::pass();
        }

        $field = config("spam.forms.$form.email_field");
        if (!$field) {
            return Result::pass();
        }

        $email = (string) $request->input($field, '');
        if ($email === '' || !str_contains($email, '@')) {
            return Result::pass();
        }

        $domain = strtolower(trim(substr(strrchr($email, '@'), 1)));
        if ($domain === '') {
            return Result::pass();
        }

        if (isset($this->blocklist()[$domain])) {
            return Result::fail('disposable_email', 'Please use a non-disposable email address.');
        }

        return Result::pass();
    }

    /** @return array<string, true> */
    private function blocklist(): array
    {
        $domains = self::loadFromFile();

        $extra = $this->settings->extraDisposableDomains();
        if ($extra !== '') {
            foreach (preg_split('/[\s,]+/', $extra) ?: [] as $line) {
                $d = strtolower(trim($line));
                if ($d !== '') {
                    $domains[$d] = true;
                }
            }
        }

        return $domains;
    }

    /** @return array<string, true> */
    private static function loadFromFile(): array
    {
        if (self::$fileCache !== null) {
            return self::$fileCache;
        }
        $domains = [];
        $path = resource_path((string) config('spam.disposable_email.list_path', 'spam/disposable_email_domains.txt'));
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $d = strtolower(trim($line));
                if ($d !== '' && $d[0] !== '#') {
                    $domains[$d] = true;
                }
            }
        }
        return self::$fileCache = $domains;
    }

    public static function clearCache(): void
    {
        self::$fileCache = null;
    }
}
