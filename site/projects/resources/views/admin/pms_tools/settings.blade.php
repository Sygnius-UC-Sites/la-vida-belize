@extends('admin.layouts')

@section('title', 'Admin ')

@section('content_header')
    <h1 class="m-0 text-dark">PMS Settings &amp; Sync</h1>
@stop

@section('content')
    @parent
    <div class="col-12 col-sm-12 col-lg-12">
        <div class="card card-success card-outline">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-clipboard-check mr-2"></i>Go-live status, cron, and webhook logs</h3>
            </div>
            <div class="card-body d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                <div class="mb-3 mb-md-0">
                    <p class="mb-1">
                        Open the operations dashboard to see the release checklist, latest scheduler heartbeat,
                        queue proof, webhook registrations, webhook payload summaries, and mail-send logs.
                    </p>
                    <small class="text-muted">Use this before launch and after every credential/domain change.</small>
                </div>
                <a href="{{ route('admin.operations.index') }}" class="btn btn-success">
                    <i class="fa fa-tasks mr-1"></i> Open Go-live Dashboard
                </a>
            </div>
        </div>

        <div class="card card-primary card-outline">
            {{-- Posts to the site's existing admin/setting save endpoint (generic
                 key→value into basic_settings) so the host DashboardController
                 is never modified. --}}
            {!! Form::open(['url' => 'admin/setting', 'files' => 'true']) !!}
            <div class="card-body">
                @include('admin.dashboard.sub.pms')

                {{-- What happens to already-synced data when an API key changes --}}
                <hr>
                <div class="form-group">
                    <label style="display:block;">If a provider's API key changes, what should happen to its already-synced data?</label>
                    <small class="text-muted d-block mb-2">
                        Applies to rooms, photos, calendars, rates and imported reservations from the old account.
                        Site pages you published stay live either way — they just detach from the old calendar.
                    </small>
                    <div class="custom-control custom-radio">
                        <input class="custom-control-input" type="radio" id="pms-data-archive" name="pms_data_action" value="archive" checked>
                        <label class="custom-control-label" for="pms-data-archive" style="font-weight:400;">
                            <strong>Archive, then clear</strong> (recommended) — a full snapshot is saved below before the data is removed, so nothing is ever lost.
                        </label>
                    </div>
                    <div class="custom-control custom-radio">
                        <input class="custom-control-input" type="radio" id="pms-data-delete" name="pms_data_action" value="delete">
                        <label class="custom-control-label" for="pms-data-delete" style="font-weight:400;">
                            <strong>Delete permanently</strong> — remove the old account's data with no snapshot.
                        </label>
                    </div>
                    <div class="custom-control custom-radio">
                        <input class="custom-control-input" type="radio" id="pms-data-keep" name="pms_data_action" value="keep">
                        <label class="custom-control-label" for="pms-data-keep" style="font-weight:400;">
                            <strong>Keep as-is</strong> — leave the old data in place (it may show stale availability until the new account syncs over it).
                        </label>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
            {!! Form::close() !!}
        </div>

        {{-- Archived PMS snapshots --}}
        @php
            $archiveDir = storage_path('app/pms-archives');
            $archives = [];
            if (is_dir($archiveDir)) {
                foreach (scandir($archiveDir, SCANDIR_SORT_DESCENDING) as $f) {
                    if (str_ends_with($f, '.json')) {
                        $archives[] = ['name' => $f, 'size' => filesize($archiveDir . '/' . $f), 'mtime' => filemtime($archiveDir . '/' . $f)];
                    }
                }
            }
        @endphp
        <div class="card card-outline card-secondary">
            <div class="card-header"><h3 class="card-title"><i class="fa fa-box-archive mr-2"></i>PMS data archives</h3></div>
            <div class="card-body">
                @if(empty($archives))
                    <p class="text-muted mb-0">No archives yet. When you change a provider's API key with "Archive, then clear" selected, a snapshot of the old account's data appears here.</p>
                @else
                    <table class="table table-sm table-hover mb-0">
                        <thead><tr><th>Snapshot</th><th>Created</th><th>Size</th><th style="width:180px;"></th></tr></thead>
                        <tbody>
                        @foreach($archives as $a)
                            <tr>
                                <td><i class="fa fa-file-code mr-1 text-muted"></i>{{ $a['name'] }}</td>
                                <td>{{ date('M j, Y H:i', $a['mtime']) }}</td>
                                <td>{{ number_format($a['size'] / 1024, 1) }} KB</td>
                                <td>
                                    <a class="btn btn-xs btn-outline-success" href="{{ url('admin/pms-archives/restore/' . $a['name']) }}"
                                       onclick="return confirm('Restore this snapshot?\n\nThe provider\'s CURRENT data will be archived first (nothing is lost), then replaced by this snapshot. Site pages re-link automatically.');">
                                        <i class="fa fa-rotate-left"></i> Restore
                                    </a>
                                    <a class="btn btn-xs btn-outline-primary" href="{{ url('admin/pms-archives/download/' . $a['name']) }}">
                                        <i class="fa fa-download"></i> Download
                                    </a>
                                    <a class="btn btn-xs btn-outline-danger" href="{{ url('admin/pms-archives/delete/' . $a['name']) }}"
                                       onclick="return confirm('Delete this archive permanently? This cannot be undone.');">
                                        <i class="fa fa-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
@stop
