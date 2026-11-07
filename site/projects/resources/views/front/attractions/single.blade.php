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
<link rel="stylesheet" href="{{ asset('front/css/attraction-detail.css') }}">
@stop
@section("container")

@php
    $nearbyAttractions = \App\Models\Attraction::where('publish', 'published')
        ->where('id', '!=', $data->id)
        ->orderBy('ordering')
        ->take(3)
        ->get();
    $imgSrc = Str::startsWith($data->image ?? '', 'http')
        ? $data->image
        : ($data->image ? asset('uploads/attractions/' . $data->image) : '');
@endphp

<!-- HERO BANNER -->
<section class="property-hero">
    <div class="hero-overlay">
        <div class="hero-content">
            <h1>{{ $data->name }}</h1>
            <p class="hero-subtitle">{{ $data->shortDescription ?? 'Things To Do' }}</p>
        </div>
    </div>
</section>

<!-- SINGLE ATTRACTION DETAIL -->
<section class="atr-single-section">
    <div class="container">

        <!-- Breadcrumb -->
        <nav class="atr-breadcrumb">
            <a href="{{ url('attractions') }}">Things To Do</a>
            <span class="sep">/</span>
            <span class="current">{{ $data->name }}</span>
        </nav>

        <!-- Body block: image left, long description right -->
        <div class="atr-body-block">

            <div class="atr-body-img">
                @if($imgSrc)
                    <img src="{{ $imgSrc }}" alt="{{ $data->name }}" loading="lazy" />
                @endif
                @if($data->shortDescription)
                    <span class="atr-tag">{{ $data->shortDescription }}</span>
                @endif
            </div>

            <div class="atr-body-text">
                @if($data->longDescription)
                    {!! $data->longDescription !!}
                @else
                    <p>{{ $data->description }}</p>
                @endif
            </div>

        </div>

        @if($nearbyAttractions->count())
        <hr class="atr-section-divider" />

        <h2 class="atr-nearby-heading reveal">Also nearby</h2>
        <div class="atr-nearby-grid">
            @foreach($nearbyAttractions as $nearby)
            @php
                $nearbyImg = Str::startsWith($nearby->image ?? '', 'http')
                    ? $nearby->image
                    : ($nearby->image ? asset('uploads/attractions/' . $nearby->image) : '');
            @endphp
            <a href="{{ url('attractions/detail/' . $nearby->seo_url) }}" class="atr-nearby-card">
                <div class="nc-img">
                    @if($nearbyImg)
                        <img src="{{ $nearbyImg }}" alt="{{ $nearby->name }}" loading="lazy" />
                    @endif
                </div>
                <div class="nc-body">
                    @if($nearby->shortDescription)
                        <span class="nc-tag">{{ $nearby->shortDescription }}</span>
                    @endif
                    <h3 class="nc-title">{{ $nearby->name }}</h3>
                </div>
            </a>
            @endforeach
        </div>
        @endif

    </div>
</section>

@stop
@section("js")
@parent
<script src="{{ asset('front/js/attraction.js') }}"></script>
@stop
