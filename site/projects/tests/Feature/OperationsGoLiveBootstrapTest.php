<?php

namespace Tests\Feature;

use App\Models\BasicSetting;
use Tests\TestCase;

class OperationsGoLiveBootstrapTest extends TestCase
{
    public function test_go_live_bootstrap_records_success_in_dry_run_mode(): void
    {
        BasicSetting::query()->updateOrCreate(
            ['name' => 'lodgify_webhook_token'],
            ['value' => 'test-token']
        );

        $this->artisan('ops:go-live-bootstrap', [
            '--url' => 'https://book.example.test',
            '--dry-run-webhooks' => true,
            '--skip-queue' => true,
        ])->assertSuccessful();

        $this->assertDatabaseHas('basic_settings', [
            'name' => 'webhook_base_url',
            'value' => 'https://book.example.test',
        ]);
        $this->assertDatabaseHas('ops_runs', [
            'run_type' => 'go_live_bootstrap',
            'status' => 'success',
        ]);
        $this->assertDatabaseHas('ops_runs', [
            'run_type' => 'webhook_registration',
            'status' => 'success',
        ]);
    }

    public function test_go_live_bootstrap_rejects_non_https_url(): void
    {
        $this->artisan('ops:go-live-bootstrap', [
            '--url' => 'http://book.example.test',
            '--dry-run-webhooks' => true,
            '--skip-queue' => true,
        ])->assertFailed();

        $this->assertDatabaseHas('ops_runs', [
            'run_type' => 'go_live_bootstrap',
            'status' => 'failure',
        ]);
    }
}
