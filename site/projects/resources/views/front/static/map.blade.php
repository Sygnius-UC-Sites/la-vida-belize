@extends("front.layouts.master")

{{-- Master clean clone placeholder. Joe's original design removed (broken + cross-client copy).
     Replace with the client's design. All copy is CMS-driven and data-safe. --}}

@section("title", optional($data ?? null)->meta_title ?? 'Find Us · ' . ($setting_data['website'] ?? 'Your Brand'))
@section("description", optional($data ?? null)->meta_description ?? '')

@section("container")
    <section class="panel" style="min-height:50vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:6rem 1.5rem;">
        <div>
            <div class="section-num" style="opacity:.6;margin-bottom:1rem;">{{ $setting_data['website'] ?? 'Your Brand' }}</div>
            <h1 style="margin:0 0 1rem;">{{ optional($data ?? null)->fw_banner_title ?? 'Find Us' }}</h1>
            <p style="max-width:36rem;margin:0 auto 2rem;opacity:.8;">{{ optional($data ?? null)->shortDescription ?? 'Master clean clone placeholder — replace with the client design.' }}</p>
            <a href="{{ url('/stays') }}" class="btn">Browse stays</a>
        </div>
    </section>
@endsection
