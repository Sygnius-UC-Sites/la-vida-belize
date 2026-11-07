<?php

namespace App\Services\Spam;

class Result
{
    public function __construct(
        public readonly bool $passed,
        public readonly ?string $reason = null,
        public readonly ?string $userMessage = null,
        public readonly bool $silent = false,
    ) {}

    public static function pass(): self
    {
        return new self(passed: true);
    }

    public static function fail(string $reason, string $userMessage): self
    {
        return new self(passed: false, reason: $reason, userMessage: $userMessage, silent: false);
    }

    public static function silentFail(string $reason): self
    {
        return new self(passed: false, reason: $reason, userMessage: null, silent: true);
    }
}
