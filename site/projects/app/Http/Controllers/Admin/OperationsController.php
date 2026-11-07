<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OperationsRun;
use App\Services\Operations\GoLiveChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OperationsController extends Controller
{
    public function index(GoLiveChecklist $checklist)
    {
        $sections = $checklist->build();
        $summary = $checklist->summary($sections);

        $recentWebhooks = OperationsRun::query()
            ->where('run_type', 'webhook_received')
            ->orderByDesc('created_at')
            ->limit(25)
            ->get();

        $recentSchedulerRuns = OperationsRun::query()
            ->whereIn('run_type', ['scheduler', 'scheduler_heartbeat', 'queue_probe'])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $recentOperations = OperationsRun::query()
            ->whereNotIn('run_type', ['webhook_received', 'scheduler', 'scheduler_heartbeat', 'queue_probe'])
            ->orderByDesc('created_at')
            ->limit(30)
            ->get();

        $recentMail = collect();
        if (Schema::hasTable('mail_send_log')) {
            $recentMail = DB::table('mail_send_log')
                ->orderByDesc('id')
                ->limit(15)
                ->get(['purpose', 'to_email', 'subject', 'status', 'error_code', 'error_message', 'sent_at', 'created_at']);
        }

        $cronCommand = '* * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1';

        return view('admin.operations.index', compact(
            'sections',
            'summary',
            'recentWebhooks',
            'recentSchedulerRuns',
            'recentOperations',
            'recentMail',
            'cronCommand'
        ));
    }

    public function goLiveBootstrap(Request $request)
    {
        set_time_limit(0);

        $url = trim((string) $request->input('url', ''));
        $args = [];
        if ($url !== '') {
            $args['--url'] = $url;
        }
        if ($request->boolean('dry_run_webhooks')) {
            $args['--dry-run-webhooks'] = true;
        }
        if ($request->boolean('skip_queue')) {
            $args['--skip-queue'] = true;
        }

        $code = Artisan::call('ops:go-live-bootstrap', $args);
        $output = Artisan::output();

        return redirect()->route('admin.operations.index')
            ->with($code === 0 ? 'success' : 'danger', $code === 0 ? 'Go-live bootstrap completed.' : 'Go-live bootstrap completed with failures.')
            ->with('operations_output', $output);
    }

    public function registerWebhooks(Request $request)
    {
        set_time_limit(0);

        $args = [];
        if ($request->boolean('dry_run')) {
            $args['--dry-run'] = true;
        }

        $code = Artisan::call('webhooks:register', $args);
        $output = Artisan::output();

        return redirect()->route('admin.operations.index')
            ->with($code === 0 ? 'success' : 'danger', $code === 0 ? 'Webhook registration completed.' : 'Webhook registration failed.')
            ->with('operations_output', $output);
    }

    public function queueProbe()
    {
        set_time_limit(0);

        $code = Artisan::call('ops:queue-probe', ['--wait' => 30, '--keep' => true]);
        $output = Artisan::output();

        return redirect()->route('admin.operations.index')
            ->with($code === 0 ? 'success' : 'danger', $code === 0 ? 'Queue probe passed.' : 'Queue probe failed.')
            ->with('operations_output', $output);
    }
}
