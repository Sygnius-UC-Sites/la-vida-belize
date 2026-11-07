@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Adventures — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Adventures near La Vida Belize in Placencia.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero"><img alt="" src="{{ asset('assets/attractions/adventures.jpg') }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">Attractions</div><h1>Adventures.</h1></div></section>
<section class="page-copy"><div class="wrap">
<p class="crumb"><a href="{{ url('/attractions') }}">Attractions</a> / Adventures</p>
<div class="spot-list">
<article class="spot-card">
<div class="spot-media"><img alt="Bunches of Fun Banana Farm Tour" src="{{ asset('assets/attractions/adventures.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Bunches of Fun Banana Farm Tour</h2><p>A quick 10-minute drive north from Placencia, the Bunches of Fun Banana Farm Tour offers an educational and entertaining look at banana farming in Belize. This tour is perfect for families and anyone interested in agriculture.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="River Tubing & Hiking at Cockscomb Wildlife Sanctuary" src="{{ asset('assets/attractions/adventures-tubing.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>River Tubing & Hiking at Cockscomb Wildlife Sanctuary</h2><p>Just a 20-minute drive from Placencia, Cockscomb Wildlife Sanctuary offers fantastic river tubing and hiking experiences. Explore the sanctuary's diverse wildlife and scenic trails for a memorable adventure.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Ziplining" src="{{ asset('assets/attractions/adventures-zip.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Ziplining</h2><p>Experience the thrill of ziplining at Bocawina National Park and Maya King, both within a 25-minute drive of Placencia. These ziplining adventures offer an exhilarating way to see the beautiful Belizean jungle from above.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Belize Zoo" src="{{ asset('assets/attractions/adventures-zoo.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Belize Zoo</h2><p>Located a 2½ hour drive north from Placencia, the Belize Zoo offers a wonderful opportunity to see a variety of native animals in a natural setting. This family-friendly attraction is a great day trip for animal lovers of all ages.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Monkey River Eco Tours" src="{{ asset('assets/attractions/adventures-tubing.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Monkey River Eco Tours</h2><p>Explore the natural beauty of Monkey River with an eco-tour that launches from Placencia. These tours provide an up-close look at the local wildlife and lush landscapes.</p><p class="spot-meta">To book a tour, call <a href="tel:+5016016359">+501-601-6359</a>.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Belize Spice Farm & Botanical Gardens" src="{{ asset('assets/attractions/adventures.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Belize Spice Farm & Botanical Gardens</h2><p>Located just 45 minutes from Placencia towards Punta Gorda, the Belize Spice Farm & Botanical Gardens offers a fascinating tour of exotic spices and beautiful gardens.</p><p class="spot-meta">For more information, call <a href="tel:+5016701338">+501-670-1338</a>.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Scuba Diving with Splash Dive Center" src="{{ asset('assets/attractions/adventures-zip.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Scuba Diving with Splash Dive Center</h2><p>Splash Dive Center offers an array of scuba diving experiences, launching from Placencia. Whether you're a beginner or an experienced diver, Splash Dive Center provides professional and safe diving excursions.</p><p class="spot-meta">Call <a href="tel:+5015233080">+501-523-3080</a>.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Snorkeling with Kendis" src="{{ asset('assets/attractions/adventures-tubing.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Snorkeling with Kendis</h2><p>Join Kendis for a day of snorkeling around the beautiful reefs and islands of Belize. Launching from the Placencia pier, these trips offer an unforgettable experience of Belize's marine life.</p><p class="spot-meta">Call Kendis at <a href="tel:+5016118216">+501-611-8216</a>.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="Fishing with Brad Bowman" src="{{ asset('assets/attractions/adventures.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>Fishing with Brad Bowman</h2><p>Experience fishing on the flats, deep-sea, or reef with expert guide Brad Bowman. Launching from Placencia, Brad offers a variety of fishing experiences tailored to both novice and experienced anglers.</p><p class="spot-meta">Call Brad at <a href="tel:+5016252030">+501-625-2030</a>.</p></div>
</article>
<article class="spot-card">
<div class="spot-media"><img alt="DTourz" src="{{ asset('assets/attractions/adventures-zoo.jpg') }}" width="640" height="420"></div>
<div class="spot-body"><h2>DTourz</h2><p>DTourz, operated by Doyle, provides a range of inland and sea excursions, picking you up directly from your property.</p><p class="spot-meta">Call Doyle at <a href="tel:+5016002318">+501-600-2318</a>.</p></div>
</article>
</div>
<p class="prose" style="margin-top:36px"><a class="text-link" href="{{ url('/attractions') }}">Back to attractions <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a></p>
</div></section>
</main>
@endsection
