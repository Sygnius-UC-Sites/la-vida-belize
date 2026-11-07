@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'The Experience — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Beach, pier, pools, kayaks and a private Caribbean retreat in Placencia.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero"><img alt="" src="{{ asset('assets/image-09-6a1b93b083df.webp') }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">The experience</div><h1>Beach, pier,<br>pool and palapa.</h1></div></section>
<section class="page-copy"><div class="wrap">
<div class="split-block">
<div class="prose">
<div class="eyebrow">Beachfront living</div>
<h2>The Caribbean is waiting outside.</h2>
<p>La Vida Belize sits on the Placencia Peninsula with the sea a few steps from your door. Spend the morning on the private beach, cool off in a pool, walk the pier, or take a kayak into clear water.</p>
<p>Accommodations are built with Belizean hardwoods from the nearby Maya Mountains. French doors open toward the sea, kitchens are fully equipped, and every room is air-conditioned.</p>
</div>
<img alt="Pool and outdoor living" src="{{ asset('assets/image-09-6a1b93b083df.webp') }}" width="1000" height="800">
</div>
<div class="amenity-list">
<div class="amenity-card"><span class="eyebrow">01</span><h3>Private beach &amp; pier</h3><p>A peaceful stretch of sand and a wooden pier with palapa — a favorite for sunrise, sunset and stargazing.</p></div>
<div class="amenity-card"><span class="eyebrow">02</span><h3>Pools</h3><p>The Casa has a swimming pool and outdoor living deck. The Casita has a private plunge pool.</p></div>
<div class="amenity-card"><span class="eyebrow">03</span><h3>Kayaks &amp; bikes</h3><p>Complimentary tandem kayaks and bicycles for exploring the coast at your own pace.</p></div>
<div class="amenity-card"><span class="eyebrow">04</span><h3>Snorkel &amp; fish</h3><p>Practice snorkeling on coral heads just off the beach. Fishing is possible from the kayak, with tarpon across the road in the lagoon.</p></div>
</div>
<div class="split-block">
<img alt="Sunset on the beach" src="{{ asset('assets/image-12-df4e2463e030.webp') }}" width="1000" height="800">
<div class="prose">
<div class="eyebrow">A day here</div>
<h2>Let the light set the pace.</h2>
<p>Sunrise coffee as the palms begin to move. Midday on the water or by the pool. Evening on the pier as the sky turns gold, then a short drive to Maya Beach or Placencia Village for dinner.</p>
<p>Many tour operators offer courtesy pick-up from La Vida, so reef trips, jungle days and village evenings are easy to arrange when you want them.</p>
<a class="button primary" href="{{ url('/') }}#booking">Check dates <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
</div>
</div>
</div></section>
</main>
@endsection
