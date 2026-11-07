@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'FAQs — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Booking, pets, amenities and stay details for La Vida Belize.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero"><img alt="" src="{{ asset('assets/image-10-0f2192a0362e.webp') }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">Practicalities</div><h1>Frequently asked<br>questions.</h1></div></section>
<section class="page-copy"><div class="wrap">
<article class="faq-item"><h2>How do I book?</h2><p>Use the availability search on this site to choose dates, guests and a home. You will continue to our Hostaway reservation pages for live rates and payment. You can also email info@lavidabelize.com or call +1 877-823-5493.</p></article>
<article class="faq-item"><h2>What payments do you accept?</h2><p>On the website we accept major credit cards. We also accept PayPal, Cash App, Zelle and Venmo for US guests, and bank transfers for Belize guests. Contact us with questions.</p></article>
<article class="faq-item"><h2>Where is La Vida Belize?</h2><p>204 Plantation, Placencia Road, Placencia, Stann Creek, Belize — on the Placencia Peninsula, with Maya Beach and Placencia Village a short drive away.</p></article>
<article class="faq-item"><h2>Which homes can I book?</h2><p>The Casa (up to 6 guests), the Casita (up to 3) and the Studio (up to 2). Together they sleep 11. Ask us if you would like more than one home.</p></article>
<article class="faq-item"><h2>Are pets allowed?</h2><p>We love pets, but we cannot accommodate them at the property. Service animals are permitted — please tell us in advance.</p></article>
<article class="faq-item"><h2>What amenities are included?</h2><p>Wi-Fi, air conditioning, fully equipped kitchens, private bathrooms, and access to the beach, pier and pools. Kayaks, bicycles and snorkeling gear are available. Details vary by home.</p></article>
<article class="faq-item"><h2>Is there a minimum stay?</h2><p>Minimum stay can vary by season and accommodation. Live dates on the booking pages show what is available for your trip.</p></article>
<article class="faq-item"><h2>What is the cancellation policy?</h2><p>The policy can vary by time of year and length of stay. Please review the terms on the reservation page for your dates, or contact us.</p></article>
<article class="faq-item"><h2>What is nearby?</h2><p>Snorkeling, diving, fishing, village restaurants, Maya sites, wildlife tours and jungle adventures. Many operators pick up at La Vida. See our Explore page for dining and outing ideas.</p></article>
</div></section>
</main>
@endsection
