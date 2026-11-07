<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyGuestyWebhookSignature;
use App\Http\Middleware\VerifyHostawayWebhookToken;
use App\Http\Middleware\VerifyHostfullyWebhookToken;
use App\Models\BasicSetting;
use Illuminate\Http\Request;
use Tests\TestCase;

class SharedWebhookTokenTest extends TestCase
{
    public function test_admin_webhook_token_protects_token_based_provider_endpoints(): void
    {
        BasicSetting::query()->updateOrCreate(
            ['name' => 'lodgify_webhook_token'],
            ['value' => 'shared-admin-token']
        );

        config([
            'services.hostaway.webhook_token' => null,
            'services.hostfully.webhook_token' => null,
            'services.guesty.webhook_token' => null,
            'services.guesty.webhook_secret' => null,
        ]);

        $cases = [
            [new VerifyHostawayWebhookToken(), '/webhooks/hostaway?token=shared-admin-token'],
            [new VerifyHostfullyWebhookToken(), '/webhooks/hostfully?token=shared-admin-token'],
            [new VerifyGuestyWebhookSignature(), '/webhooks/guesty/shared-admin-token'],
        ];

        foreach ($cases as [$middleware, $url]) {
            $request = Request::create($url, 'POST');
            $request->setRouteResolver(fn () => new class($request) {
                public function __construct(private Request $request)
                {
                }

                public function parameter(string $name, mixed $default = null): mixed
                {
                    if ($name === 'webhookToken' && str_contains($this->request->path(), '/guesty/')) {
                        return basename($this->request->path());
                    }

                    return $default;
                }
            });

            $response = $middleware->handle(
                $request,
                fn () => response()->json(['status' => 'accepted'])
            );

            $this->assertSame(200, $response->getStatusCode());
        }
    }

    public function test_guesty_rejects_a_missing_callback_token_as_unauthorized(): void
    {
        BasicSetting::query()->updateOrCreate(
            ['name' => 'lodgify_webhook_token'],
            ['value' => 'shared-admin-token']
        );
        config([
            'services.guesty.webhook_token' => null,
            'services.guesty.webhook_secret' => null,
        ]);

        $request = Request::create('/webhooks/guesty', 'POST');
        $request->setRouteResolver(fn () => new class {
            public function parameter(string $name, mixed $default = null): mixed
            {
                return $default;
            }
        });

        $response = (new VerifyGuestyWebhookSignature())->handle(
            $request,
            fn () => response()->json(['status' => 'accepted'])
        );

        $this->assertSame(401, $response->getStatusCode());
    }
}
