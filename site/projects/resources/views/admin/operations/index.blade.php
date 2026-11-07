@extends('admin.layouts')

@section('title', 'Go Live Operations')

@section('content_header')
    <h1 class="m-0 text-dark">Go Live Operations</h1>
@stop

@section('content')
    @parent

    @php
        $statusIcon = function (string $status): string {
            return match ($status) {
                'pass', 'success' => '<i class="fas fa-check-circle text-success"></i>',
                'warn', 'ignored', 'running' => '<i class="fas fa-exclamation-circle text-warning"></i>',
                default => '<i class="fas fa-times-circle text-danger"></i>',
            };
        };
        $badgeClass = function (?string $status): string {
            return match ($status) {
                'pass', 'success' => 'badge-success',
                'warn', 'ignored', 'running' => 'badge-warning',
                default => 'badge-danger',
            };
        };
        $fmtDate = function ($value): string {
            if (!$value) {
                return '-';
            }

            $timezone = 'Asia/Kolkata';
            return \Illuminate\Support\Carbon::parse($value)
                ->timezone($timezone)
                ->format('M j, Y H:i:s T P');
        };
        $json = function ($value): string {
            return json_encode($value ?: [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        };
    @endphp

    @if(session('operations_output'))
        <div class="card card-outline card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-terminal mr-2"></i>Last action output</h3>
            </div>
            <div class="card-body">
                <pre class="mb-0" style="white-space:pre-wrap;max-height:320px;overflow:auto;">{{ session('operations_output') }}</pre>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-md-3">
            <div class="small-box bg-success">
                <div class="inner">
                    <h3>{{ $summary['passed'] }}</h3>
                    <p>Passing checks</p>
                </div>
                <div class="icon"><i class="fas fa-check-circle"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box {{ $summary['failed'] ? 'bg-danger' : 'bg-secondary' }}">
                <div class="inner">
                    <h3>{{ $summary['failed'] }}</h3>
                    <p>Blocking failures</p>
                </div>
                <div class="icon"><i class="fas fa-times-circle"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box {{ $summary['warned'] ? 'bg-warning' : 'bg-secondary' }}">
                <div class="inner">
                    <h3>{{ $summary['warned'] }}</h3>
                    <p>Evidence gaps / warnings</p>
                </div>
                <div class="icon"><i class="fas fa-exclamation-circle"></i></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="small-box {{ $summary['ready'] ? 'bg-success' : 'bg-danger' }}">
                <div class="inner">
                    <h3>{{ $summary['ready'] ? 'Ready' : 'Hold' }}</h3>
                    <p>Automated release gate</p>
                </div>
                <div class="icon"><i class="fas fa-clipboard-check"></i></div>
            </div>
        </div>
    </div>

    <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title"><i class="fa fa-rocket mr-2"></i>Go-live automation</h3>
        </div>
        <div class="card-body">
            <p class="text-muted">
                This runs the safe app-side launch tasks: save the public webhook URL, register PMS webhooks, write a scheduler heartbeat,
                and prove the queue worker. Server cron still has to exist on the server; the heartbeat below proves whether it is actually firing.
            </p>
            <div class="row">
                <div class="col-lg-7">
                    <form method="POST" action="{{ route('admin.operations.bootstrap') }}" class="mb-3">
                        @csrf
                        <div class="input-group">
                            <input type="url" name="url" class="form-control" placeholder="https://your-live-domain.com" value="{{ rtrim(config('app.url'), '/') }}">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit" onclick="return confirm('Run go-live bootstrap now? This may call PMS webhook registration APIs.');">
                                    <i class="fa fa-play mr-1"></i> Run Bootstrap
                                </button>
                            </div>
                        </div>
                        <div class="mt-2">
                            <label class="mr-3"><input type="checkbox" name="dry_run_webhooks" value="1"> Dry-run webhooks</label>
                            <label><input type="checkbox" name="skip_queue" value="1"> Skip queue probe</label>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('admin.operations.webhooks') }}" class="d-inline">
                        @csrf
                        <button class="btn btn-outline-primary btn-sm" type="submit" onclick="return confirm('Register PMS webhooks now?');">
                            <i class="fa fa-plug mr-1"></i> Register Webhooks
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.operations.webhooks') }}" class="d-inline ml-1">
                        @csrf
                        <input type="hidden" name="dry_run" value="1">
                        <button class="btn btn-outline-secondary btn-sm" type="submit">
                            <i class="fa fa-eye mr-1"></i> Preview Webhook URLs
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.operations.queue-probe') }}" class="d-inline ml-1">
                        @csrf
                        <button class="btn btn-outline-success btn-sm" type="submit">
                            <i class="fa fa-tasks mr-1"></i> Run Queue Probe
                        </button>
                    </form>
                </div>
                <div class="col-lg-5">
                    <label>Server cron that must be installed</label>
                    <pre class="mb-0" style="white-space:pre-wrap;">{{ $cronCommand }}</pre>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-outline card-success">
        <div class="card-header">
            <h3 class="card-title"><i class="fa fa-tasks mr-2"></i>Release checklist</h3>
        </div>
        <div class="card-body p-0">
            @foreach($sections as $section)
                <div class="p-3 border-bottom">
                    <h5 class="mb-3">{{ $section['title'] }}</h5>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                            <tr>
                                <th style="width:42px;">OK</th>
                                <th>Check</th>
                                <th>Detail</th>
                                <th style="width:180px;">Source</th>
                                <th style="width:170px;">Tested</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($section['items'] as $item)
                                <tr>
                                    <td>{!! $statusIcon($item['status']) !!}</td>
                                    <td>
                                        <strong>{{ $item['label'] }}</strong>
                                        @if(!empty($item['description']))
                                            <small class="text-muted d-block">{{ $item['description'] }}</small>
                                        @endif
                                        @if(!empty($item['steps']))
                                            <ol class="pl-3 mt-2 mb-0 text-muted small">
                                                @foreach($item['steps'] as $step)
                                                    <li>{{ $step }}</li>
                                                @endforeach
                                            </ol>
                                        @endif
                                    </td>
                                    <td><small>{{ $item['detail'] }}</small></td>
                                    <td><span class="badge badge-light">{{ $item['source'] }}</span></td>
                                    <td><small>{{ $fmtDate($item['tested_at'] ?? null) }}</small></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card card-outline card-info">
                <div class="card-header"><h3 class="card-title"><i class="fa fa-bolt mr-2"></i>Recent webhooks</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>Time</th><th>Provider</th><th>Event</th><th>Status</th><th>Detail</th></tr></thead>
                        <tbody>
                        @forelse($recentWebhooks as $run)
                            <tr>
                                <td><small>{{ $fmtDate($run->created_at) }}</small></td>
                                <td>{{ $run->provider ?: '-' }}</td>
                                <td>{{ $run->event ?: '-' }}</td>
                                <td><span class="badge {{ $badgeClass($run->status) }}">{{ $run->status }}</span></td>
                                <td>
                                    <small>{{ $run->summary ?: $run->error }}</small>
                                    @if($run->context)
                                        <details class="mt-1"><summary>payload</summary><pre style="white-space:pre-wrap;max-height:180px;overflow:auto;">{{ $json($run->context) }}</pre></details>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted p-3">No webhook evidence recorded yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-outline card-warning">
                <div class="card-header"><h3 class="card-title"><i class="fa fa-clock mr-2"></i>Recent cron / queue runs</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>Time</th><th>Command</th><th>Status</th><th>Detail</th></tr></thead>
                        <tbody>
                        @forelse($recentSchedulerRuns as $run)
                            <tr>
                                <td><small>{{ $fmtDate($run->created_at) }}</small></td>
                                <td><small>{{ $run->command ?: $run->run_type }}</small></td>
                                <td><span class="badge {{ $badgeClass($run->status) }}">{{ $run->status }}</span></td>
                                <td><small>{{ $run->summary ?: $run->error }}</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted p-3">No scheduler evidence recorded yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-6">
            <div class="card card-outline card-secondary">
                <div class="card-header"><h3 class="card-title"><i class="fa fa-clipboard-list mr-2"></i>Recent operations</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>Time</th><th>Type</th><th>Status</th><th>Summary</th></tr></thead>
                        <tbody>
                        @forelse($recentOperations as $run)
                            <tr>
                                <td><small>{{ $fmtDate($run->created_at) }}</small></td>
                                <td>{{ $run->run_type }}</td>
                                <td><span class="badge {{ $badgeClass($run->status) }}">{{ $run->status }}</span></td>
                                <td>
                                    <small>{{ $run->summary ?: $run->error }}</small>
                                    @if($run->output || $run->error)
                                        <details class="mt-1"><summary>output</summary><pre style="white-space:pre-wrap;max-height:180px;overflow:auto;">{{ $run->output ?: $run->error }}</pre></details>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted p-3">No operational runs recorded yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-outline card-secondary">
                <div class="card-header"><h3 class="card-title"><i class="fa fa-envelope mr-2"></i>Recent mail sends</h3></div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>Time</th><th>Purpose</th><th>Status</th><th>Detail</th></tr></thead>
                        <tbody>
                        @forelse($recentMail as $mail)
                            <tr>
                                <td><small>{{ $fmtDate($mail->created_at) }}</small></td>
                                <td>{{ $mail->purpose }}</td>
                                <td><span class="badge {{ $badgeClass($mail->status === 'sent' ? 'success' : 'failure') }}">{{ $mail->status }}</span></td>
                                <td><small>{{ $mail->subject }} {{ $mail->error_message ? ' - ' . $mail->error_message : '' }}</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-muted p-3">No mail-send rows recorded yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@stop
