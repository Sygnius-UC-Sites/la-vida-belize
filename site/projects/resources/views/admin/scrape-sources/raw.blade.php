@extends('admin.layouts')

@section('title', 'Raw scraped payload')

@section('content_header')
    <h1>Raw scraped payload</h1>
    <p class="text-muted">
        The exact JSON the importer returned for source <code>{{ $source->source_url }}</code> on run #{{ $run->id }}.
        Use this to spot field-name mismatches between the Actor's output and our mapper, then update <code>app/Services/Scraping/Mappers/AirbnbMapper.php</code>.
    </p>
@stop

@section('content')
    <a href="{{ route('admin.scrape-sources.index') }}" class="btn btn-sm btn-outline-secondary mb-3">← Back to sources</a>

    <div class="d-flex" style="gap:8px;">
        <form method="POST" action="{{ route('admin.scrape-sources.remap', $source->id) }}" class="d-inline mb-3">
            @csrf
            <button class="btn btn-primary" type="submit">Re-map from this payload</button>
        </form>
        <small class="text-muted align-self-center">No provider call — runs the mapper against the cached JSON below and updates the linked property.</small>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Run #{{ $run->id }} · status: <code>{{ $run->status }}</code> · finished: {{ $run->finished_at ?? '—' }}</span>
            {{-- Provider-console link removed by design: external URL leaked the upstream service identity. --}}
        </div>
        <div class="card-body p-0">
            <pre style="margin:0;padding:16px;background:#272822;color:#f8f8f2;font-size:0.78em;line-height:1.45;max-height:80vh;overflow:auto;">{{ $pretty }}</pre>
        </div>
    </div>
@stop
