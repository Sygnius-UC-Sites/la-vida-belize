@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Guest Reviews — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Guest stories from stays at La Vida Belize.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero"><img alt="" src="{{ asset('assets/image-12-df4e2463e030.webp') }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">Guest love</div><h1>Words from<br>the beach.</h1></div></section>
<section class="page-copy"><div class="wrap"><div class="quote-list">
<article class="quote-card"><blockquote>“La Vida Belize is truly a home away from home. The house is beautiful and well-maintained. The kitchen has everything you could ever need. The views are amazing and we caught fish just off the beach via the kayak.”</blockquote><strong>Jason G.</strong><small> November 2025</small></article>
<article class="quote-card"><blockquote>“After four days hiking in the mountains and jungle, we were ready to slow down. From the moment we saw the casita, we knew we chose the right spot. Perfectly tended and welcoming… from the tumbled stone path and the blooming hibiscus to the reviving plunge pool and coconut palms. The private pier, complete with palapa, was our go-to spot for spectacular sunrises and stargazing.”</blockquote><strong>Michael B.</strong><small> November 2025</small></article>
<article class="quote-card"><blockquote>“THE BEST Airbnb we ever stayed at. Came here for our honeymoon and it was everything we could’ve imagined… close enough to the restaurants and the village, but far enough away for total privacy. Pablo the groundskeeper was absolutely amazing. We picked and cracked coconuts every morning for our smoothies.”</blockquote><strong>Alyssa T.</strong><small> October 2025</small></article>
<article class="quote-card"><blockquote>“Wonderful! We loved our stay at La Vida. We wanted a week away from our cold winter and this was perfect!”</blockquote><strong>Karen N.</strong><small> March 2024</small></article>
<article class="quote-card"><blockquote>“Fabulous place. Amazing place.”</blockquote><strong>Jason C.</strong><small> February 2024</small></article>
<article class="quote-card"><blockquote>“Great experience. We loved the bungalow! It was perfect for our needs.”</blockquote><strong>Nicole Antonopoulos</strong><small> January 2024</small></article>
</div></div></section>
</main>
@endsection
