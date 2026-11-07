{{-- Base Vacation Rentals — inner-banner / breadcrumb component.
     Variables: $name (page title), $bannerImage (optional override), $data (CMS row), $property (optional).
     Resolution order:
       1) explicit $bannerImage passed by the caller
       2) $data->inner_banner_image (per-CMS-page admin upload — added in QA pass)
       3) $data->bannerImage (legacy column, still respected for back-compat)
       4) the design's default breadcrumb.avif
--}}
@php
    if (empty($bannerImage)) {
        $pageBanner = $data->inner_banner_image ?? $data->bannerImage ?? null;
        if (!empty($pageBanner)) {
            $bannerImage = str_starts_with($pageBanner, 'http') ? $pageBanner : asset($pageBanner);
        } else {
            $bannerImage = asset('front/themes/base/img/placeholder.svg');
        }
    }
@endphp

<div class="inner-banner position-relative" style="background-image: url('{{ $bannerImage }}');">
    <div class="overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="content">
                    <h1>{{ $name ?? ($data->name ?? '') }}</h1>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <ul class="m-0 pagination">
        <li><a href="{{ url('/') }}"><i class="fa-solid fa-house"></i> Home</a></li>
        <li><span class="angle"><i class="fa-solid fa-chevron-right"></i></span></li>
        @if(isset($data->seo_url) && $data->seo_url == 'review-your-quote-lock-in-your-dates-today')
            @if(isset($property))
                <li><a href="{{ url($property->seo_url) }}">{{ $property->name }}</a></li>
                <li><span class="angle"><i class="fa-solid fa-chevron-right"></i></span></li>
            @endif
            <li><span>Quote &amp; Book</span></li>
        @else
            <li><a href="#"><span>{{ $name ?? ($data->name ?? '') }}</span></a></li>
        @endif
    </ul>
</div>
