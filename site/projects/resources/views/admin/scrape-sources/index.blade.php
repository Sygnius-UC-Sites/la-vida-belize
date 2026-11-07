@extends('admin.layouts')

@section('title', 'Listing Imports')

@section('content_header')
    <h1>Listing Imports</h1>
    <p class="text-muted">
        Paste the Airbnb / VRBO URLs for this property's listings, click <strong>Import</strong>, and the scraper pulls the full
        listing data (title, description, photos, capacity, price, address) into a draft Property. You then publish it after a quick review.
        Re-scraping an already-imported listing fills <em>blank fields only</em> — your local edits always win.
    </p>
@stop

@section('content')
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('danger'))  <div class="alert alert-danger">{{ session('danger') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @if($values['importer_token_corrupt'])
        <div class="alert alert-danger">
            <strong>The API token is stored but cannot be decrypted.</strong>
            Most likely APP_KEY changed or wasn't set when the token was saved. <strong>Re-enter the token in Settings → Save</strong> to fix.
            (Details logged to <code>storage/logs/importer-*.log</code>.)
        </div>
    @elseif(!$values['importer_token_set'])
        <div class="alert alert-warning">
            <strong>The API token isn't set.</strong> Set it in the Settings card below before clicking Import.
        </div>
    @endif

    {{-- Add sources --}}
    <div class="card mb-4">
        <div class="card-header"><h5 class="m-0">Add listing URLs</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.scrape-sources.store') }}">
                @csrf
                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label>Platform</label>
                        <select name="platform" class="form-control">
                            <option value="airbnb">Airbnb</option>
                            <option value="vrbo">VRBO</option>
                            <option value="booking">Booking.com</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group col-md-9">
                        <label>Notes (optional)</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. 'Cabin #4 — owner Jeff'">
                    </div>
                </div>
                <div class="form-group">
                    <label>URLs (one per line)</label>
                    <textarea name="urls" class="form-control" rows="4" required placeholder="https://www.airbnb.com/rooms/12345&#10;https://www.airbnb.com/rooms/67890"></textarea>
                    <small class="text-muted">Duplicates and invalid URLs are skipped automatically.</small>
                </div>
                <button class="btn btn-primary" type="submit">Add to queue</button>
            </form>
        </div>
    </div>

    {{-- Sources list --}}
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="m-0">Sources ({{ $sources->count() }})</h5>
            <small class="text-muted">Click Import to scrape; once status reads <code>imported</code> the property appears in the catalogue with status <code>draft</code> for your review.</small>
        </div>
        <div class="card-body p-0">
            @if($sources->isEmpty())
                <div class="p-3 text-muted">No sources yet. Paste some URLs above to get started.</div>
            @else
                <table class="table table-sm m-0">
                    <thead><tr>
                        <th>URL</th><th>Platform</th><th>Status</th><th>Property</th><th>Last scrape</th><th style="width:300px;">Actions</th>
                    </tr></thead>
                    <tbody>
                    @foreach($sources as $s)
                        @php $latestRun = $runsById->get($s->id); @endphp
                        <tr>
                            <td><small><a href="{{ $s->source_url }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($s->source_url, 60) }}</a></small>
                                @if($s->notes)<div class="text-muted"><small>{{ $s->notes }}</small></div>@endif
                            </td>
                            <td><small><code>{{ $s->platform }}</code></small></td>
                            <td>
                                @switch($s->status)
                                    @case('imported')   <span class="badge badge-success">imported</span> @break
                                    @case('scraping')   <span class="badge badge-info">scraping</span> @break
                                    @case('failed')     <span class="badge badge-danger">failed</span> @break
                                    @default           <span class="badge badge-secondary">{{ $s->status }}</span>
                                @endswitch
                                @if($s->last_error)
                                    <details class="text-danger mt-1" style="cursor:pointer;">
                                        <summary><small>{{ \Illuminate\Support\Str::limit(strtok($s->last_error, "\n"), 140) }}</small></summary>
                                        <pre style="font-size:0.75em; white-space:pre-wrap; max-height:240px; overflow:auto; background:#fff5f5; padding:8px; border:1px solid #fbb; border-radius:4px; margin-top:4px;">{{ $s->last_error }}</pre>
                                    </details>
                                @endif
                                {{-- Provider-console link removed: external URL leaked the upstream service identity. --}}
                            </td>
                            <td>
                                @if($s->property_id && $s->property_name)
                                    <a href="{{ url('admin/properties/'.$s->property_id.'/edit') }}">{{ \Illuminate\Support\Str::limit($s->property_name, 40) }}</a>
                                    <div class="text-muted"><small>#{{ $s->property_id }} · {{ $s->property_type }}</small></div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td><small>{{ $s->last_scraped_at ?? '—' }}</small></td>
                            <td>
                                <form method="POST" action="{{ route('admin.scrape-sources.scrape', $s->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-primary" type="submit">
                                        {{ $s->status === 'imported' ? 'Re-scrape' : 'Import' }}
                                    </button>
                                </form>
                                @php
                                    $inProgressStates = ['RUNNING', 'queued', 'READY'];
                                    $isInProgress = $latestRun && in_array($latestRun->status, $inProgressStates, true);
                                    // Also show Check Status when a run completed on the importer's side but
                                    // we never finalised it (raw_payload empty). Localhost without a
                                    // webhook leaves runs in this stuck state — clicking Check Status
                                    // now triggers finalise(), pulling the dataset and writing rows.
                                    $needsFinalise = $latestRun
                                        && in_array($latestRun->status, ['SUCCEEDED', 'FAILED', 'TIMED-OUT', 'ABORTED'], true)
                                        && empty(\DB::table('scrape_runs')->where('id', $latestRun->id)->value('raw_payload'));
                                @endphp
                                @if($isInProgress || $needsFinalise)
                                    <form method="POST" action="{{ route('admin.scrape-sources.check-status', $s->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary" type="submit"
                                                title="Latest run kind: {{ $latestRun->kind ?? 'listing' }} · Status: {{ $latestRun->status }}{{ $needsFinalise ? ' (not yet imported — click to finalise)' : '' }}">
                                            {{ $needsFinalise ? 'Finalise import' : 'Check status' }}
                                        </button>
                                    </form>
                                @endif
                                @if($latestRun)
                                    <a class="btn btn-sm btn-outline-info" href="{{ route('admin.scrape-sources.raw', $s->id) }}">View raw</a>
                                    <form method="POST" action="{{ route('admin.scrape-sources.remap', $s->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-success" type="submit"
                                                title="Re-run the mapper against the cached payload — no provider call.">Re-map</button>
                                    </form>
                                @endif
                                @php
                                    $reviewsRun = $reviewsRunsById->get($s->id);
                                    $reviewsCount = $s->property_id ? ($reviewCounts[$s->property_id] ?? 0) : 0;
                                    $reviewsInProgress = $reviewsRun && in_array($reviewsRun->status, ['RUNNING', 'queued', 'READY'], true);
                                @endphp
                                @if($s->property_id && ($reviewsRun || $reviewsCount > 0))
                                    {{-- Always-visible Reviews link with state badge once a reviews run exists. --}}
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.scrape-sources.reviews', $s->id) }}">
                                        Reviews
                                        @if($reviewsInProgress)
                                            <span class="badge badge-info">scraping…</span>
                                        @elseif($reviewsRun && $reviewsRun->status === 'FAILED')
                                            <span class="badge badge-danger">failed</span>
                                        @else
                                            ({{ $reviewsCount }})
                                        @endif
                                    </a>
                                @endif
                                @if($s->status === 'imported' && $values['importer_max_reviews'] > 0 && !$reviewsInProgress)
                                    <form method="POST" action="{{ route('admin.scrape-sources.scrape-reviews', $s->id) }}" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-warning" type="submit"
                                                title="Runs the dedicated reviews scraper (separate from the listing scraper — costs extra credits).">
                                            {{ $reviewsRun ? 'Re-import reviews' : 'Import reviews' }}
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('admin.scrape-sources.destroy', $s->id) }}" class="d-inline">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger" type="submit"
                                            onclick="return confirm('Remove this source? (The imported property — if any — is NOT deleted.)')">remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Settings --}}
    <div class="card mb-5">
        <div class="card-header"><h5 class="m-0">Settings (shared across all client installs)</h5></div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.scrape-sources.settings.update') }}">
                @csrf
                @method('PUT')

                <div class="form-row">
                    <div class="form-group col-md-3">
                        <label>
                            <input type="hidden" name="importer_enabled" value="0">
                            <input type="checkbox" name="importer_enabled" value="1" {{ $values['importer_enabled'] ? 'checked' : '' }}>
                            Enabled
                        </label>
                    </div>
                    <div class="form-group col-md-9">
                        <label>API token {{ $values['importer_token_set'] ? '(stored — leave blank to keep)' : '(required)' }}</label>
                        <input type="password" name="importer_api_token" class="form-control" value="" placeholder="{{ $values['importer_token_set'] ? '••••••••• ENCRYPTED AT REST' : 'Paste API token' }}" autocomplete="new-password">
                        <small class="text-muted">Encrypted via Laravel <code>Crypt</code> (APP_KEY-derived); never returned to this form.</small>
                    </div>
                </div>

                {{-- Actor IDs are intentionally not editable from the admin form — they identify the upstream
                     scraper service. Configure via env: IMPORTER_ACTOR_AIRBNB / IMPORTER_ACTOR_AIRBNB_REVIEWS.
                     See docs/INTERNAL-VENDORS.md. --}}

                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label>Max runs per minute</label>
                        <input type="number" name="importer_max_runs_per_minute" class="form-control" value="{{ $values['importer_max_runs_per_minute'] }}" min="1" max="1000">
                    </div>
                    <div class="form-group col-md-6">
                        <label>Max reviews per listing</label>
                        <input type="number" name="importer_max_reviews" class="form-control" value="{{ $values['importer_max_reviews'] }}" min="0" max="5000">
                        <small class="text-muted">0 = skip reviews. ~30 covers most cases; higher = more credits per import.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label>Webhook secret {{ $values['importer_webhook_secret_set'] ?? false ? '' : '(recommended)' }}</label>
                    <input type="text" name="importer_webhook_secret" class="form-control" value="" placeholder="any-random-string">
                    <small class="text-muted">If set, kicked-off runs include this in <code>X-Webhook-Secret</code> when the provider POSTs <code>{{ $webhookUrl }}</code>.</small>
                </div>

                <button class="btn btn-primary" type="submit">Save settings</button>
            </form>

            <hr>
            <form method="POST" action="{{ route('admin.scrape-sources.test-connection') }}" class="m-0">
                @csrf
                <button class="btn btn-outline-secondary btn-sm" type="submit">Test the importer connection</button>
                <small class="text-muted ml-2">Calls the importer <code>/users/me</code> with the resolved token. Use this to confirm the token authenticates before retrying an import.</small>
            </form>
        </div>
    </div>

    <div class="card mb-5">
        <div class="card-header"><h5 class="m-0">Webhook URL (for the importer)</h5></div>
        <div class="card-body">
            <input type="text" class="form-control" value="{{ $webhookUrl }}" readonly onfocus="this.select()" style="background:#f8f9fa;">
            <small class="text-muted d-block mt-2">
                The orchestrator registers this URL on every the importer run-start when a webhook secret is set.
                On localhost the webhook isn't reachable from the importer — schedule <code>php artisan importer:poll-runs</code> every minute, or use the "Check status" button above.
            </small>
        </div>
    </div>
@stop
