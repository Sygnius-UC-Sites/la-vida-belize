@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Car Rental — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Car Rental near La Vida Belize in Placencia.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero"><img alt="" src="{{ asset('assets/attractions/car.jpg') }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">Attractions</div><h1>Car Rental.</h1></div></section>
<section class="page-copy"><div class="wrap">
<p class="crumb"><a href="{{ url('/attractions') }}">Attractions</a> / Car Rental</p>
<div class="spot-list">
<article class="spot-card">
<div class="spot-media"><img alt="Barefoot Car Rental" src="{{ asset('assets/attractions/car.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Barefoot Car Rental</h2><p>Barefoot Car Rental, based in Placencia, is known for its friendly service and extensive selection of vehicles. Whether you need a car for a short trip around town or a rugged vehicle for exploring off-the-beaten-path locations, Barefoot Car Rental has the perfect option for you.</p><p class="spot-meta">Contact Megs at <a href="tel:+5015233066">523-3066</a> or visit <a href="https://barefootrentalsbelize.com/" rel="noopener">Barefoot Car Rental</a>.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Budget Placencia and Belize City" src="{{ asset('assets/attractions/car-2.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Budget Placencia and Belize City</h2><p>Budget Car Rental offers convenient locations in both Placencia and Belize City, providing a variety of vehicles to meet your transportation needs. Known for their competitive pricing and excellent customer service, Budget ensures a hassle-free rental experience.</p><p class="spot-meta">Contact Neimah at <a href="tel:+5016319777">631-9777</a> or visit <a href="https://www.budget-belize.com/" rel="noopener">Budget Car Rental</a>.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Crystal Auto Rental" src="{{ asset('assets/attractions/car.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Crystal Auto Rental</h2><p>Crystal Auto Rental is a reliable choice for those seeking car rental services in Belize City. With a wide selection of vehicles, they cater to city driving and exploring farther afield.</p><p class="spot-meta">Belize <a href="tel:+5012231600">501-223-1600</a> · US <a href="tel:+19363071325">936-307-1325</a> · WhatsApp <a href="https://wa.me/5016707617">501-670-7617</a> · <a href="https://www.crystal-belize.com/" rel="noopener">crystal-belize.com</a></p></div>
</article>
</div>
<p class="prose" style="margin-top:36px"><a class="text-link" href="{{ url('/attractions') }}">Back to attractions <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a></p>
</div></section>
</main>
@endsection
