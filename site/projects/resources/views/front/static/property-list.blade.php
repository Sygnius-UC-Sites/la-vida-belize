@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Properties — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Casa, Casita and Studio beachfront rentals near Placencia, Belize.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero has-search" id="booking">
<img alt="" src="{{ asset('assets/image-06-d8a97bf200a4.webp') }}" width="1600" height="900">
<div class="wrap page-hero-copy"><div class="eyebrow">Properties</div><h1>Casa, Casita<br>and Studio.</h1></div>
<div class="hero-bottom"><form class="search-panel wrap" id="bookingForm">
<div class="search-cell"><label for="checkinTrigger">Check-in</label><button aria-controls="datePopover" aria-expanded="false" aria-haspopup="dialog" class="booking-trigger" id="checkinTrigger" type="button">Add date</button><input id="checkin" name="checkin" type="hidden"></div>
<div class="search-cell"><label for="checkoutTrigger">Check-out</label><button aria-controls="datePopover" aria-expanded="false" aria-haspopup="dialog" class="booking-trigger" id="checkoutTrigger" type="button">Add date</button><input id="checkout" name="checkout" type="hidden"></div>
<div class="search-cell"><label for="guestTrigger">Guests</label><button aria-controls="guestPopover" aria-expanded="false" aria-haspopup="dialog" class="booking-trigger" id="guestTrigger" type="button">1 guest <span aria-hidden="true" class="booking-chevron">⌄</span></button><input id="guests" name="guests" type="hidden" value="1"></div>
<div class="search-cell"><label for="rental">Accommodation</label><select aria-label="Choose accommodation" id="rental"><option value="all">All accommodations</option><option value="casa">The Casa</option><option value="casita">The Casita</option><option value="studio">The Studio</option><option value="group">Whole property enquiry</option></select></div>
<button class="search-submit" type="submit">Check availability <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
</form></div>
</section>
<section class="stays page-copy"><div class="wrap">
<p class="prose">Choose the home that fits your trip. All three share La Vida’s beach, pier and tropical grounds. Check dates on each stay for live rates, then continue to checkout here.</p>
<div class="stays-gallery" style="margin-top:36px">
<article class="stay-card"><div class="stay-image tall"><img alt="The Casa" src="{{ asset('assets/image-06-d8a97bf200a4.webp') }}" width="900" height="700"><span class="stay-tag">From $180 / night</span></div><div class="stay-body"><h3 class="stay-name">The Casa</h3><p class="stay-description">Spacious beachfront home with its own pool. Ideal for families and friends who want room to gather.</p><div class="stay-facts"><span>6 guests</span><span>4 beds</span><span>2 baths</span></div><a class="button primary" href="{{ url('/stay/la-vida-belize-casa') }}">Check dates <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div></article>
<article class="stay-card"><div class="stay-image tall"><img alt="The Casita" src="{{ asset('assets/image-07-b7515f3cf8d1.webp') }}" width="900" height="700"><span class="stay-tag">From $173 / night</span></div><div class="stay-body"><h3 class="stay-name">The Casita</h3><p class="stay-description">A charming hideaway with a private plunge pool and wooden deck, a few steps from the palms and sea.</p><div class="stay-facts"><span>3 guests</span><span>2 beds</span><span>1 bath</span></div><a class="button primary" href="{{ url('/stay/la-vida-belize-casita') }}">Check dates <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div></article>
<article class="stay-card"><div class="stay-image tall"><img alt="The Studio" src="{{ asset('assets/image-08-4dfe2a0731af.webp') }}" width="900" height="700"><span class="stay-tag">From $89 / night</span></div><div class="stay-body"><h3 class="stay-name">The Studio</h3><p class="stay-description">Comfortable and simple, with an open living area and shaded patio. A home base for two.</p><div class="stay-facts"><span>2 guests</span><span>1 bed</span><span>1 bath</span></div><a class="button primary" href="{{ url('/stay/la-vida-belize-studio') }}">Check dates <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a></div></article>
</div>
<p style="margin-top:28px">Need more than one home? <a class="text-link" href="{{ url('/groups') }}">Ask about a group stay <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a></p>
</div></section>
</main>
@endsection

@section('modals')
@include('front.partials.gallery-modal')
@endsection
