@extends("front.layouts.master")
@section("title", $data->meta_title ?? $data->name)
@section("keywords", $data->meta_keywords ?? '')
@section("description", $data->meta_description ?? '')
@section("css")
@parent
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400;1,600&family=Jost:wght@200;300;400;500;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="{{ asset('front/base/css/style.css') }}">
<link rel="stylesheet" href="{{ asset('front/base/css/responsive.css') }}">
@stop
@section("container")

@php
    $attractions = \App\Models\Attraction::where('category_id', $data->id)->orderBy('ordering', 'asc')->get();
@endphp

<!-- HERO BANNER -->
<section class="property-hero">
    <div class="hero-overlay">
        <div class="hero-content">
            <h1>{{ $data->name ?? 'Attractions' }}</h1>
            <p class="hero-subtitle">{{ $data->shortDescription ?? 'Handpicked experiences around Playa Los Cerritos.' }}</p>
        </div>
    </div>
</section>

<!-- ATTRACTION DETAILS -->
<section class="atr-detail-section" id="attractions">
    <div class="container">

        <span class="section-eyebrow reveal">Explore Nearby</span>
        <h2 class="section-heading reveal">{{ $data->name ?? 'Local Highlights' }}</h2>

        @forelse($attractions as $index => $attraction)
        @php $isEven = $index % 2 !== 0; @endphp
        <div class="atr-detail-row {{ $isEven ? 'row-reverse' : '' }}">
            <div class="row align-items-center g-5">
                <div class="col-lg-6 {{ $isEven ? 'order-lg-2' : '' }}">
                    <div class="atr-img-wrap">
                        @if($attraction->image)
                            <img src="{{ asset('uploads/attractions/' . $attraction->image) }}"
                                alt="{{ $attraction->name }}" loading="lazy" />
                        @endif
                        @if($attraction->category)
                            <span class="atr-tag">{{ $attraction->category->name ?? '' }}</span>
                        @endif
                    </div>
                </div>
                <div class="col-lg-6 {{ $isEven ? 'order-lg-1' : '' }}">
                    <div class="atr-info">
                        <h3 class="atr-title">{{ $attraction->name }}</h3>
                        <p class="atr-desc">{{ strip_tags($attraction->shortDescription ?? $attraction->description ?? '') }}</p>
                        <a href="{{ url('attraction/' . $attraction->seo_url) }}" class="atr-link">
                            Learn more
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <line x1="5" y1="12" x2="19" y2="12" />
                                <polyline points="12 5 19 12 12 19" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="text-center py-5">
            <p>No attractions found in this category.</p>
        </div>
        @endforelse

    </div>
</section>

@stop
@section("js")
@parent
<script src="{{ asset('front/js/attraction.js') }}"></script>
@stop
