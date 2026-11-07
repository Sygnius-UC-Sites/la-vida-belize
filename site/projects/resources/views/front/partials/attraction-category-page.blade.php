@php
    $forcedSlug = $forcedSlug ?? null;
    $category = $category ?? ($forcedSlug
        ? \App\Models\AttractionCategory::where('seo_url', $forcedSlug)->where('publish', 'published')->first()
        : null);
    $attractions = $attractions ?? ($category
        ? \App\Models\Attraction::where('category_id', $category->id)
            ->where('publish', 'published')
            ->orderBy('ordering', 'asc')
            ->orderBy('id', 'asc')
            ->get()
        : collect());
    $heroSrc = $category
        ? ($category->bannerUrl() ?: \App\Models\Attraction::mediaUrl('assets/peninsula/sunset-palms.webp'))
        : asset('assets/peninsula/sunset-palms.webp');
    $heading = $category ? rtrim((string) $category->name, '.') . '.' : 'Attractions.';
@endphp
<main>
<section class="page-hero"><img alt="" src="{{ $heroSrc }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">Attractions</div><h1>{{ $heading }}</h1></div></section>
<section class="page-copy"><div class="wrap">
<p class="crumb"><a href="{{ url('/attractions') }}">Attractions</a> / {{ $category->name ?? 'Guide' }}</p>
<div class="spot-list">
@foreach($attractions as $spot)
<article class="spot-card">
<div class="spot-media">@if($spot->imageUrl())<img alt="{{ $spot->name }}" src="{{ $spot->imageUrl() }}" width="640" height="420">@endif</div>
<div class="spot-body"><h2>{{ $spot->name }}</h2><p>{{ strip_tags((string) ($spot->description ?: $spot->shortDescription)) }}</p>@if(trim(strip_tags((string) $spot->longDescription)) !== ''){!! $spot->longDescription !!}@elseif($spot->mobile || $spot->map_url)<p class="spot-meta">@if($spot->mobile)<a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $spot->mobile) }}">{{ $spot->mobile }}</a>@endif @if($spot->map_url)<a href="{{ $spot->map_url }}" rel="noopener">{{ parse_url($spot->map_url, PHP_URL_HOST) ?: 'Website' }}</a>@endif</p>@endif</div>
</article>
@endforeach
</div>
<p class="prose" style="margin-top:36px"><a class="text-link" href="{{ url('/attractions') }}">Back to attractions <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a></p>
</div></section>
</main>
