<?php

namespace Tests\Feature\Admin;

use App\Models\OperationsRun;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationsDashboardTest extends TestCase
{
    public function test_operations_dashboard_renders_checklist_and_evidence(): void
    {
        OperationsRun::create([
            'run_type' => 'webhook_registration',
            'source' => 'test',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
            'summary' => 'Webhook registration completed',
        ]);
        OperationsRun::create([
            'run_type' => 'webhook_received',
            'source' => 'webhook',
            'provider' => 'lodgify',
            'event' => 'booking_change',
            'status' => 'success',
            'started_at' => now(),
            'finished_at' => now(),
            'summary' => 'Booking webhook processed',
            'context' => ['payload' => ['booking_id' => 'abc123']],
        ]);

        $response = $this->actingAs($this->user())
            ->get(route('admin.operations.index'));

        $response->assertOk();
        $response->assertSee('Go Live Operations');
        $response->assertSee('Release checklist');
        $response->assertSee('Security And Access Control');
        $response->assertSee('Spam And Form Protection');
        $response->assertSee('Current Domain / Staging Readiness');
        $response->assertSee('Final Live Domain Gate');
        $response->assertSee('CSRF middleware protects web forms');
        $response->assertSee('Final production domain smoke test recorded');
        $response->assertSee('Cloudflare Managed Challenge protects final production domain');
        $response->assertSee('Exclude /stripe/webhook, /lodgify/*, /webhooks/*, /pms-availability, /pms-quote, and /pms/save-booking-data.');
        $response->assertSee('Catches simple bots that fill hidden fields humans never see.');
        $response->assertSee('Records composer audit output so vulnerable PHP packages do not slip into release.');
        $response->assertSee('Recent webhooks');
        $response->assertSee('booking_change');
        $response->assertSee('Server cron that must be installed');
    }

    public function test_queue_probe_action_records_evidence(): void
    {
        $response = $this->actingAs($this->user())
            ->withoutMiddleware(VerifyCsrfToken::class)
            ->post(route('admin.operations.queue-probe'));

        $response->assertRedirect(route('admin.operations.index'));
        $this->assertDatabaseHas('ops_runs', [
            'run_type' => 'queue_probe',
            'status' => 'success',
        ]);
    }

    private function user(): User
    {
        return User::create([
            'name' => 'Ops Admin',
            'email' => 'ops-admin@example.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }
}
