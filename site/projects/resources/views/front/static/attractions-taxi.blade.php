@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Taxi Services — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Taxi Services near La Vida Belize in Placencia.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero"><img alt="" src="{{ asset('assets/attractions/taxi.jpg') }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">Attractions</div><h1>Taxi Services.</h1></div></section>
<section class="page-copy"><div class="wrap">
<p class="crumb"><a href="{{ url('/attractions') }}">Attractions</a> / Taxi Services</p>
<div class="spot-list">
<article class="spot-card">
<div class="spot-media"><img alt="Hola Hola Taxi Cameron" src="{{ asset('assets/attractions/taxi.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Hola Hola Taxi Cameron</h2><p>Hola Hola Taxi, operated by Cameron, is a reliable and efficient taxi service in Placencia. Known for punctuality and excellent service, Cameron ensures a comfortable and safe ride to your destination. Whether you need a ride to a local attraction or transportation for a longer journey, Hola Hola Taxi is at your service.</p><p class="spot-meta">Contact Cameron at <a href="tel:+5016687863">668-7863</a>.</p></div>
</article>
</div>
<p class="prose" style="margin-top:36px"><a class="text-link" href="{{ url('/attractions') }}">Back to attractions <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a></p>
</div></section>
</main>
@endsection
