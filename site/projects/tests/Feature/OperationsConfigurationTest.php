<?php

namespace Tests\Feature;

use App\Models\BasicSetting;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class OperationsConfigurationTest extends TestCase
{
    public function test_pms_tasks_are_not_registered_twice(): void
    {
        Artisan::call('schedule:list', ['--json' => true]);
        $events = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $commands = collect($events)->pluck('command');

        $this->assertSame(
            1,
            $commands->filter(fn (string $command) => str_ends_with($command, 'artisan pms:sync-all'))->count()
        );
        $this->assertSame(
            1,
            $commands->filter(fn (string $command) => str_ends_with($command, 'artisan guesty:warm-token'))->count()
        );
    }

    public function test_webhook_dry_run_uses_saved_admin_base_url(): void
    {
        BasicSetting::query()->updateOrCreate(
            ['name' => 'webhook_base_url'],
            ['value' => 'https://book.example.test/']
        );
        BasicSetting::query()->updateOrCreate(
            ['name' => 'lodgify_webhook_token'],
            ['value' => 'shared-admin-token']
        );

        $this->artisan('webhooks:register', ['--pms' => 'lodgify', '--dry-run' => true])
            ->expectsOutputToContain('https://book.example.test/lodgify')
            ->doesntExpectOutputToContain('LODGIFY_WEBHOOK_TOKEN is not set')
            ->assertSuccessful();
    }

    public function test_provider_webhook_environment_values_are_exposed_through_config(): void
    {
        config()->set('services.hostaway.webhook_token', 'hostaway-token');
        config()->set('services.hostfully.webhook_token', 'hostfully-token');
        config()->set('services.guesty.webhook_token', 'guesty-token');
        config()->set('services.guesty.webhook_secret', 'guesty-secret');

        $this->assertSame('hostaway-token', config('services.hostaway.webhook_token'));
        $this->assertSame('hostfully-token', config('services.hostfully.webhook_token'));
        $this->assertSame('guesty-token', config('services.guesty.webhook_token'));
        $this->assertSame('guesty-secret', config('services.guesty.webhook_secret'));
    }
}
