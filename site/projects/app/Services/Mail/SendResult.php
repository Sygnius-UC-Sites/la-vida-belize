<?php

namespace App\Services\Mail;

class SendResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $messageId = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
        public readonly bool $retryEligible = false,
    ) {}

    public static function ok(string $messageId): self
    {
        return new self(success: true, messageId: $messageId);
    }

    public static function fail(string $errorCode, string $errorMessage, bool $retryEligible = false): self
    {
        return new self(
            success: false,
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            retryEligible: $retryEligible,
        );
    }
}
