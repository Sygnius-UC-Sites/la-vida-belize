<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyLodgifyWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LodgifyWebhookConfigurationTest extends TestCase
{
    public function test_lodgify_webhook_accepts_admin_configured_token(): void
    {
        config(['services.lodgify.webhook_token' => null]);
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'lodgify_webhook_token'],
            ['value' => 'admin-token-for-webhooks', 'created_at' => now(), 'updated_at' => now()]
        );

        $request = Request::create('/lodgify/booking_change?token=admin-token-for-webhooks', 'POST');

        $response = (new VerifyLodgifyWebhookSignature())->handle(
            $request,
            fn () => response()->json(['status' => 'accepted'])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('accepted', $response->getData(true)['status']);
    }

    public function test_lodgify_webhook_rejects_when_no_token_is_configured(): void
    {
        config(['services.lodgify.webhook_token' => null]);
        DB::table('basic_settings')->where('name', 'lodgify_webhook_token')->delete();

        $request = Request::create('/lodgify/booking_change?token=anything', 'POST');

        $response = (new VerifyLodgifyWebhookSignature())->handle(
            $request,
            fn () => response()->json(['status' => 'accepted'])
        );

        $this->assertSame(500, $response->getStatusCode());
    }
}
