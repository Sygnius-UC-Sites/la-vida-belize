@extends('front.layouts.master')

@section('title', $data?->meta_title ?? ('Our Stays - ' . ($setting_data['website'] ?? config('app.name', 'Vacation Rentals'))))
@section('keywords', $data?->meta_keywords ?? '')
@section('description', $data?->meta_description ?? 'Browse our vacation rentals and check availability.')
@section('body-class', 'stays-page-body')

@section('css')
<style>
.stays-page-body {
    background: #f8f5ec;
    color: #13241f;
}
.stays-page-body .inner-banner {
    position: relative;
    z-index: 10;
    min-height: 560px;
    display: flex;
    align-items: center;
    padding: calc(var(--nav-h, 80px) + 70px) 0 110px;
    background-position: center;
    background-size: cover;
}
.stays-page-body .inner-banner::before {
    content: "";
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(11, 40, 32, .72), rgba(11, 40, 32, .48));
    z-index: 1;
}
.stays-page-body .inner-banner .overlay {
    display: none;
}
.stays-page-body .inner-banner .container {
    position: relative;
    z-index: 2;
}
.stays-page-body .inner-banner .content {
    max-width: 940px;
    margin: 0 auto;
    text-align: center;
}
.stays-page-body .inner-banner h1 {
    margin: 0 0 30px;
    color: #fff;
    font-family: var(--heading-font, var(--font-body));
    font-size: clamp(2.5rem, 7vw, 5rem);
    font-weight: 800;
    letter-spacing: 0;
    line-height: 1.02;
}
.stays-page-body .inner-banner .booking-bar {
    max-width: 940px;
    width: 100%;
    margin: 0 auto;
    overflow: visible;
}
.stays-page-body .inner-banner .bb-field {
    min-width: 160px;
    text-align: left;
}
.stays-page-body .inner-banner .bb-input {
    border: 0;
    width: 100%;
    min-height: 42px;
    background: transparent;
    color: #0b3b3a;
    font-size: 16px;
    font-weight: 700;
    outline: 0;
}
.stays-page-body .inner-banner .btn-check-avail {
    align-self: stretch;
    min-height: 58px;
    border-radius: 14px;
    background: #0b3b3a;
    color: #fff !important;
    letter-spacing: .04em;
}
.stays-page-body .pagination {
    display: none;
}
.stays-page-body .retreats-section {
    padding: clamp(54px, 8vw, 92px) 0;
    background: #f8f5ec;
}
.stays-page-body .retreats-eyebrow {
    color: #0f8b80;
    font-size: .78rem;
    font-weight: 800;
    letter-spacing: .18em;
    text-transform: uppercase;
}
.stays-page-body .retreats-title {
    color: #13241f;
    font-size: clamp(2rem, 5vw, 3.4rem);
    font-weight: 800;
}
.stays-page-body .explore-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
}
.stays-page-body .explore-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 11px 16px;
    border: 1px solid rgba(15, 139, 128, .2);
    border-radius: 999px;
    background: #fff;
    color: #12473a;
    font-weight: 800;
}
.stays-page-body .explore-tab.active,
.stays-page-body .explore-tab:hover {
    background: #0b3b3a;
    color: #fff;
}
.stays-page-body .retreat-card {
    height: 100%;
    overflow: hidden;
    border-radius: 16px;
    background: #fff;
    color: #13241f;
    box-shadow: 0 18px 54px rgba(19, 36, 31, .11);
}
.stays-page-body .retreat-slider img {
    width: 100%;
    height: 290px;
    object-fit: cover;
}
.stays-page-body .retreat-body {
    padding: 24px;
}
.stays-page-body .rp-name {
    color: #13241f;
    font-size: 1.35rem;
    font-weight: 800;
}
.stays-page-body .rp-bar {
    width: 54px;
    height: 3px;
    margin: 12px 0;
    background: #d9a24a;
}
.stays-page-body .rp-desc {
    color: #44504d;
}
.stays-page-body .rp-specs {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin: 18px 0;
    color: #44504d;
    font-size: .9rem;
    font-weight: 700;
}
.stays-page-body .btn-rp-inquire {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 44px;
    padding: 0 20px;
    border-radius: 8px;
    background: #d9a24a;
    color: #0b2820 !important;
    font-weight: 900;
    letter-spacing: .08em;
    text-transform: uppercase;
}
.booking-bar .easepick-wrapper, .booking-bar easepick-calendar { z-index: 1000; }
@media (max-width: 767px) {
    .stays-page-body .inner-banner {
        min-height: auto;
        padding: calc(var(--nav-h, 80px) + 42px) 0 54px;
    }
    .stays-page-body .inner-banner .booking-bar {
        padding: 18px;
    }
    .stays-page-body .inner-banner .btn-check-avail {
        width: 100%;
    }
}
</style>
@endsection

@section('container')
@php
    $properties = $properties ?? collect();
    $context = $context ?? (object) ['check_in'=>null,'check_out'=>null,'adults'=>2,'children'=>0,'pets'=>0];
    $detailQuery = array_filter([
        'check_in'  => $context->check_in ?? null,
        'check_out' => $context->check_out ?? null,
        'adults'    => $context->adults ?? null,
        'children'  => ($context->children ?? 0) > 0 ? $context->children : null,
        'pets'      => ($context->pets ?? 0) > 0 ? $context->pets : null,
    ]);
    $dateRange = !empty($context->check_in)
        ? (\Carbon\Carbon::parse($context->check_in)->format('M j') . ' – ' . \Carbon\Carbon::parse($context->check_out)->format('M j'))
        : '';
@endphp

<!-- ---banner: Our Stays + search--- -->
<div class="inner-banner position-relative" style="background-image: url('{{ $data?->inner_banner_image ?: asset('front/themes/base/img/placeholder.svg') }}');">
    <div class="overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="content">
                    <h1 class="mb-4">{{ $data?->fw_banner_title ?: 'Our Stays' }}</h1>
                    <form class="booking-bar" action="{{ url('stays') }}" method="get">
                        <div class="bb-top-row">
                            <div class="bb-field">
                                <label class="bb-label">CHECK IN</label>
                                <input type="text" class="bb-input" id="bbCheckIn" name="check_in" placeholder="Add date" value="{{ $context->check_in }}" autocomplete="off" data-vr-datepicker="range" data-end-input="#bbCheckOut" readonly>
                            </div>
                            <div class="bb-divider"></div>
                            <div class="bb-field">
                                <label class="bb-label">CHECK OUT</label>
                                <input type="text" class="bb-input" id="bbCheckOut" name="check_out" placeholder="Add date" value="{{ $context->check_out }}" autocomplete="off" readonly>
                            </div>
                            <div class="bb-divider"></div>
                            <div class="bb-field">
                                <label class="bb-label">GUESTS</label>
                                @include('front.partials.guest-selector', [
                                    'prefix' => 'staysSearchGuests',
                                    'adults' => $context->adults ?? 1,
                                    'children' => $context->children ?? 0,
                                    'pets' => $context->pets ?? 0,
                                    'maxGuests' => 12,
                                    'maxPets' => 5,
                                ])
                            </div>
                            <button type="submit" class="btn-check-avail">Check Availability</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="container">
    <ul class=" m-0 pagination">
        <li><a href="{{ url('/') }}"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><span class="angle"><i class="fa-solid fa-chevron-right"></i></span></li>
        <li><a href="#"><span>Stays</span></a></li>
    </ul>
</div>

<!-- ---retreats grid (available properties)--- -->
<section class="retreats-section" id="retreats">
    <div class="container">

        <div class="retreats-heading text-center mb-5">
            <p class="retreats-eyebrow">{{ $data?->retreats_eyebrow ?: 'Our Retreats' }}</p>
            <h2 class="retreats-title">{{ $properties->count() }} {{ \Illuminate\Support\Str::plural('Retreat', $properties->count()) }} Available{!! $dateRange ? ' <span style="font-weight:400;font-size:.7em;">· ' . e($dateRange) . '</span>' : '' !!}</h2>
        </div>

        @if(isset($categories) && $categories->count())
            <div class="explore-tabs mb-4">
                @php
                    $categoryBase = array_filter([
                        'check_in' => $context->check_in ?? null,
                        'check_out' => $context->check_out ?? null,
                        'adults' => ($context->adults ?? 1) > 1 ? $context->adults : null,
                        'children' => ($context->children ?? 0) > 0 ? $context->children : null,
                        'pets' => ($context->pets ?? 0) > 0 ? $context->pets : null,
                    ]);
                @endphp
                <a class="explore-tab {{ empty($context->category) ? 'active' : '' }}" href="{{ url('stays') . ($categoryBase ? '?' . http_build_query($categoryBase) : '') }}">
                    <i class="bi bi-house-heart"></i> All Stays
                </a>
                @foreach($categories as $category)
                    @php $categoryQuery = http_build_query(array_merge($categoryBase, ['category' => $category->slug])); @endphp
                    <a class="explore-tab {{ ($context->category ?? null) === $category->slug ? 'active' : '' }}" href="{{ url('stays') . '?' . $categoryQuery }}">
                        <i class="bi bi-tag"></i> {{ $category->name }}
                    </a>
                @endforeach
            </div>
        @endif

        <div class="retreats-grid">
            <div class="row g-4">
                @forelse($properties as $rp)
                    @php $rpImgs = \App\Models\PropertyGallery::where('property_id', $rp->id)->orderBy('sorting')->get(); $sid = 'slider-' . $rp->id; @endphp
                    <div class="col-12 col-lg-6">
                        <div class="retreat-card" data-slug="{{ $rp->seo_url }}">
                            <div class="retreat-slider">
                                <div id="{{ $sid }}" class="carousel slide" data-bs-ride="false">
                                    <div class="carousel-indicators">
                                        @foreach($rpImgs as $k => $img)<button type="button" data-bs-target="#{{ $sid }}" data-bs-slide-to="{{ $k }}" class="{{ $k == 0 ? 'active' : '' }}"></button>@endforeach
                                    </div>
                                    <div class="carousel-inner">
                                        @foreach($rpImgs as $k => $img)<div class="carousel-item {{ $k == 0 ? 'active' : '' }}"><img src="{{ \Illuminate\Support\Str::startsWith((string)$img->image,'http') ? $img->image : asset($img->image) }}" alt="{{ $rp->name }}" /></div>@endforeach
                                    </div>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#{{ $sid }}" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#{{ $sid }}" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
                                </div>
                            </div>
                            <div class="retreat-body">
                                <h3 class="rp-name">{{ $rp->name }}</h3>
                                <div class="rp-bar"></div>
                                <p class="rp-desc">{{ \Illuminate\Support\Str::limit(strip_tags($rp->description), 220) }}</p>
                                <div class="rp-specs">
                                    <span><i class="bi bi-people"></i> {{ $rp->sleeps }} Guests</span>
                                    <span><i class="bi bi-door-open"></i> {{ $rp->bedroom }} Bedroom</span>
                                    <span><i class="bi bi-droplet"></i> {{ $rp->bathroom }} Bath</span>
                                </div>
                                <div class="rp-cta-row">
                                    <a href="{{ \App\Support\PropertyUrl::url($rp, $detailQuery) }}" class="btn-rp-inquire">View Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center py-5"><p>No retreats available for those dates — please try different dates.</p></div>
                @endforelse
            </div>
        </div>

    </div>
</section>
@endsection
