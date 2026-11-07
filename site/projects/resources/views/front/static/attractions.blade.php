@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Attractions — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Dining, adventures, taxis, golf carts and car rentals near Placencia.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero"><img alt="" src="{{ asset('assets/peninsula/sunset-palms.webp') }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">Attractions</div><h1>Placencia and<br>everything beyond.</h1></div></section>
<section class="page-copy"><div class="wrap">
<p class="prose">La Vida is close enough to Placencia for restaurants, shops and tours, and quiet enough to come home to the beach. Choose a category to see local favorites from our guide.</p>
<div class="attraction-cats">
<a class="explore-card" href="{{ url('/attractions/dining') }}"><img alt="Dining near Placencia" src="{{ asset('assets/attractions/dining.jpg') }}" width="800" height="600"><div class="explore-content"><span class="category">Eat</span><h3>Dining</h3><p>Gelato, pizza, beach grills and village tables a short drive from the property.</p><span class="go">→</span></div></a>
<a class="explore-card" href="{{ url('/attractions/adventures') }}"><img alt="Adventures in Belize" src="{{ asset('assets/attractions/adventures.jpg') }}" width="800" height="600"><div class="explore-content"><span class="category">Do</span><h3>Adventures</h3><p>Banana farms, jungle parks, reefs, rivers and day trips inland.</p><span class="go">→</span></div></a>
<a class="explore-card" href="{{ url('/attractions/taxi') }}"><img alt="Taxi services" src="{{ asset('assets/attractions/taxi.jpg') }}" width="800" height="600"><div class="explore-content"><span class="category">Get around</span><h3>Taxi Services</h3><p>Reliable local drivers for village runs and longer journeys.</p><span class="go">→</span></div></a>
<a class="explore-card" href="{{ url('/attractions/golf-cart') }}"><img alt="Golf cart rental" src="{{ asset('assets/attractions/golf.jpg') }}" width="800" height="600"><div class="explore-content"><span class="category">Get around</span><h3>Golf Cart Rental</h3><p>The easy way to move between beach bars, Maya Beach and town.</p><span class="go">→</span></div></a>
<a class="explore-card" href="{{ url('/attractions/car-rental') }}"><img alt="Car rental" src="{{ asset('assets/attractions/car.jpg') }}" width="800" height="600"><div class="explore-content"><span class="category">Get around</span><h3>Car Rental</h3><p>Useful for jungle days, the zoo, or exploring farther along the coast.</p><span class="go">→</span></div></a>
</div>
</div></section>
</main>
@endsection
