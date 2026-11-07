@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Dining — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Dining near La Vida Belize in Placencia.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero"><img alt="" src="{{ asset('assets/attractions/dining.jpg') }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">Attractions</div><h1>Dining.</h1></div></section>
<section class="page-copy"><div class="wrap">
<p class="crumb"><a href="{{ url('/attractions') }}">Attractions</a> / Dining</p>
<div class="spot-list">
<article class="spot-card">
<div class="spot-media"><img alt="Tutti-Frutti Gelato" src="{{ asset('assets/attractions/dining.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Tutti-Frutti Gelato</h2><p>Tutti-Frutti Gelato in Placencia is a must-try for anyone with a sweet tooth. This popular spot offers delicious gelato in a variety of flavors, perfect for cooling down on a warm day.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="ChaChi’s" src="{{ asset('assets/attractions/dining-chachis.jpeg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>ChaChi’s</h2><p>Located 25 minutes south of Placencia, ChaChi’s offers pizza and live music in a lively atmosphere. It’s a fun spot for a night out with friends or family.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Rumfish y Vino" src="{{ asset('assets/attractions/dining-rumfish.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Rumfish y Vino</h2><p>Rumfish y Vino in Placencia offers a great menu and excellent food, making it a popular choice for dining out. Located 25 minutes south of Placencia, it’s worth the drive for a delicious meal.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Rick’s Café" src="{{ asset('assets/attractions/dining-greenparrot.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Rick’s Café</h2><p>Rick’s Café on the Placencia Sidewalk offers a variety of dishes in a casual setting. A 20-minute drive south from La Vida Belize, it’s a great spot for a relaxed meal.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="The Bonefish Grill" src="{{ asset('assets/attractions/dining-bonefish.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>The Bonefish Grill</h2><p>Located at Maya Beach at the Azur del Mar, just a 5-minute drive south of Placencia, The Bonefish Grill is known for its friendly service and superb food. It’s a great choice for a delicious meal close to your stay.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Rum Bum’s" src="{{ asset('assets/attractions/dining-rumfish.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Rum Bum’s</h2><p>For a taste of local Belizean dishes, head to Rum Bum’s. This spot offers a variety of traditional flavors in a relaxed and friendly atmosphere.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Ceiba Beach Resort" src="{{ asset('assets/attractions/dining.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Ceiba Beach Resort</h2><p>Ceiba Beach Resort offers elevated local flavors of Belize in an elegant setting. Enjoy a sophisticated dining experience with dishes that highlight the best of Belizean cuisine.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="The Green Parrot" src="{{ asset('assets/attractions/dining-greenparrot.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>The Green Parrot</h2><p>Close to La Vida Belize and just a 5-minute drive south to Maya Beach, The Green Parrot features a nice setting and excellent food. It's a great place to enjoy a meal with a view.</p><p class="spot-meta"><a href="tel:+5015234321">Phone 523-4321</a></p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Inky’s Grill" src="{{ asset('assets/attractions/dining-chachis.jpeg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Inky’s Grill</h2><p>Inky’s Grill offers superb food in a lovely setting, just about 6 miles south of Placencia. Don’t miss out on their Tutti-Frutti Gelato for a sweet treat after your meal.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Jaguar Lanes" src="{{ asset('assets/attractions/dining-bonefish.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Jaguar Lanes</h2><p>Located a 20-minute drive south of Placencia, Jaguar Lanes is known for its great pizza and a variety of other dishes. This casual spot is perfect for a laid-back meal with family and friends.</p></div>
</article>
</div>
<p class="prose" style="margin-top:36px"><a class="text-link" href="{{ url('/attractions') }}">Back to attractions <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a></p>
</div></section>
</main>
@endsection
