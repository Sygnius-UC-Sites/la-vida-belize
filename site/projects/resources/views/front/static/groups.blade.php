@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Group Stays — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Book Casa, Casita and Studio together for up to 11 guests.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero"><img alt="" src="{{ asset('assets/image-13-1a4592828319.webp') }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">Group stays</div><h1>Bring the whole crew.</h1></div></section>
<section class="page-copy"><div class="wrap">
<div class="split-block">
<div class="prose">
<p>Casa, Casita and Studio together sleep 11 guests, with three front doors and one shared beachfront. It is a natural fit for family reunions, celebrations and friends who want to travel together without giving up a little privacy.</p>
<p>Tell us your dates and how many homes you need. We will help you hold the combination that works, or the full property when it is available.</p>
<a class="button primary" href="mailto:info@lavidabelize.com?subject=La%20Vida%20Belize%20group%20stay">Ask about a group stay <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
</div>
<img alt="Palm-lined beach" src="{{ asset('assets/image-13-1a4592828319.webp') }}" width="1000" height="800">
</div>
<div class="group-facts" style="display:flex;gap:40px;flex-wrap:wrap">
<div><strong style="font:400 33px var(--serif);display:block">3</strong><span>Distinct retreats</span></div>
<div><strong style="font:400 33px var(--serif);display:block">11</strong><span>Combined guest capacity</span></div>
<div><strong style="font:400 33px var(--serif);display:block">1</strong><span>Beachfront setting</span></div>
</div>
</div></section>
<section class="inline-book"><div class="hero-bottom" id="booking"><form class="search-panel wrap" id="bookingForm">
<div class="search-cell"><label for="checkinTrigger">Check-in</label><button aria-controls="datePopover" aria-expanded="false" aria-haspopup="dialog" class="booking-trigger" id="checkinTrigger" type="button">Add date</button><input id="checkin" name="checkin" type="hidden"></div>
<div class="search-cell"><label for="checkoutTrigger">Check-out</label><button aria-controls="datePopover" aria-expanded="false" aria-haspopup="dialog" class="booking-trigger" id="checkoutTrigger" type="button">Add date</button><input id="checkout" name="checkout" type="hidden"></div>
<div class="search-cell"><label for="guestTrigger">Guests</label><button aria-controls="guestPopover" aria-expanded="false" aria-haspopup="dialog" class="booking-trigger" id="guestTrigger" type="button">1 guest <span aria-hidden="true" class="booking-chevron">⌄</span></button><input id="guests" name="guests" type="hidden" value="1"></div>
<div class="search-cell"><label for="rental">Accommodation</label><select aria-label="Choose accommodation" id="rental"><option value="all">All accommodations</option><option value="casa">The Casa</option><option value="casita">The Casita</option><option value="studio">The Studio</option><option value="group">Whole property enquiry</option></select></div>
<button class="search-submit" type="submit">Check availability <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
</form></div></section>
</main>
@endsection
