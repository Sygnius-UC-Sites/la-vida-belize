<?php

namespace App\Support;

use App\Models\BasicSetting;

class WebhookConfiguration
{
    public static function baseUrl(): string
    {
        $url = self::setting('webhook_base_url')
            ?: (string) config('services.webhooks.base_url', '')
            ?: (string) config('app.url', '');

        return rtrim(trim($url), '/');
    }

    public static function token(string $provider): string
    {
        $configured = (string) config("services.{$provider}.webhook_token", '');
        if ($configured !== '') {
            return $configured;
        }

        return self::setting("{$provider}_webhook_token")
            ?: self::setting('lodgify_webhook_token');
    }

    private static function setting(string $name): string
    {
        try {
            return (string) (BasicSetting::query()
                ->where('name', $name)
                ->value('value') ?? '');
        } catch (\Throwable) {
            return '';
        }
    }
}
