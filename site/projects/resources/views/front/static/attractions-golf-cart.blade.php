@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Golf Cart Rental — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Golf Cart Rental near La Vida Belize in Placencia.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero"><img alt="" src="{{ asset('assets/attractions/golf.jpg') }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">Attractions</div><h1>Golf Cart Rental.</h1></div></section>
<section class="page-copy"><div class="wrap">
<p class="crumb"><a href="{{ url('/attractions') }}">Attractions</a> / Golf Cart Rental</p>
<div class="spot-list">
<article class="spot-card">
<div class="spot-media"><img alt="Placencia Golf Cart Rentals" src="{{ asset('assets/attractions/golf.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Placencia Golf Cart Rentals</h2><p>Placencia Golf Cart Rentals offers a convenient and fun way to explore Placencia and its surroundings. Their fleet of golf carts is well-maintained and perfect for sightseeing or running errands around town.</p><p class="spot-meta">Call <a href="tel:+5016156404">615-6404</a>.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Barefoot Rentals" src="{{ asset('assets/attractions/golf-2.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Barefoot Rentals</h2><p>Barefoot Rentals provides high-quality golf cart rentals, ideal for leisurely exploring Placencia. Their selection of golf carts is well-maintained, ensuring a reliable and enjoyable experience.</p><p class="spot-meta">Call <a href="tel:+5015233066">523-3066</a> or visit <a href="https://barefootrentalsbelize.com/" rel="noopener">Barefoot Rentals</a>.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Captain Jak’s" src="{{ asset('assets/attractions/golf.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Captain Jak’s</h2><p>Captain Jak's offers top-notch golf cart rentals, perfect for navigating the scenic beauty of Placencia. Their well-maintained fleet ensures a comfortable and convenient ride, allowing you to explore the area at your own pace.</p><p class="spot-meta">Call <a href="tel:+5016286447">628-6447</a>.</p></div>
</article>
</div>
<p class="prose" style="margin-top:36px"><a class="text-link" href="{{ url('/attractions') }}">Back to attractions <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a></p>
</div></section>
</main>
@endsection
