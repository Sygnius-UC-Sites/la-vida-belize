@extends('admin.layouts')

@section('title', 'Imported reviews')

@section('content_header')
    <h1>Imported reviews</h1>
    <p class="text-muted">
        Property: <strong>{{ $property->name ?? '(deleted)' }}</strong>
        — Source: <code>{{ $source->source_url }}</code>
    </p>
@stop

@section('content')
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if(session('danger'))  <div class="alert alert-danger">{{ session('danger') }}</div> @endif

    <a href="{{ route('admin.scrape-sources.index') }}" class="btn btn-sm btn-outline-secondary mb-3">← Back to sources</a>

    @if($reviews->isEmpty())
        <div class="alert alert-info">
            No reviews imported yet. Bump <strong>Max reviews per listing</strong> in the Settings card to a non-zero number, then click <strong>Re-scrape</strong> on this source.
        </div>
    @else
        <form method="POST" action="{{ route('admin.scrape-sources.reviews.bulk', $source->id) }}">
            @csrf
            <div class="card mb-3">
                <div class="card-body py-2 d-flex align-items-center" style="gap:8px;">
                    <strong>Bulk action:</strong>
                    <button type="submit" name="action" value="publish" class="btn btn-sm btn-success">Publish selected</button>
                    <button type="submit" name="action" value="hide"    class="btn btn-sm btn-warning">Hide selected</button>
                    <button type="submit" name="action" value="delete"  class="btn btn-sm btn-danger"
                            onclick="return confirm('Delete selected reviews? This is permanent.')">Delete selected</button>
                    <span class="ml-auto text-muted">
                        Total: {{ $reviews->count() }} ·
                        Published: {{ $reviews->where('status','published')->count() }} ·
                        Hidden: {{ $reviews->where('status','hidden')->count() }}
                    </span>
                </div>
            </div>

            <table class="table table-sm">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="check-all"></th>
                        <th>When</th>
                        <th>Reviewer</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($reviews as $r)
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="{{ $r->id }}" class="row-check"></td>
                        <td><small>{{ $r->review_date ? \Carbon\Carbon::parse($r->review_date)->format('Y-m-d') : '—' }}</small><br>
                            <small class="text-muted">{{ $r->language ?: '' }}</small></td>
                        <td>
                            @if($r->reviewer_photo_local)
                                <img src="{{ asset($r->reviewer_photo_local) }}" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;float:left;margin-right:6px;">
                            @endif
                            <strong>{{ $r->reviewer_name ?: '(anonymous)' }}</strong>
                            @if($r->reviewer_location)<div><small class="text-muted">{{ $r->reviewer_location }}</small></div>@endif
                        </td>
                        <td>
                            @if($r->rating)
                                <span style="color:#f5c518;">{!! str_repeat('★', $r->rating) . str_repeat('☆', max(0, 5 - $r->rating)) !!}</span>
                            @else — @endif
                        </td>
                        <td>
                            <div style="max-width:600px;">{{ $r->comment }}</div>
                            @if($r->comment_translated)
                                <details><summary class="text-muted"><small>Translated</small></summary>
                                    <small class="text-muted">{{ $r->comment_translated }}</small>
                                </details>
                            @endif
                            @if($r->response_text)
                                <div class="mt-2 pl-3" style="border-left:3px solid #ddd;">
                                    <small class="text-muted"><strong>Host response:</strong> {{ $r->response_text }}</small>
                                </div>
                            @endif
                        </td>
                        <td>
                            @switch($r->status)
                                @case('published') <span class="badge badge-success">published</span> @break
                                @case('hidden')    <span class="badge badge-secondary">hidden</span> @break
                                @default           <span class="badge badge-info">imported</span>
                            @endswitch
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </form>

        <script>
        document.getElementById('check-all').addEventListener('change', function(e) {
            document.querySelectorAll('.row-check').forEach(cb => cb.checked = e.target.checked);
        });
        </script>
    @endif
@stop
