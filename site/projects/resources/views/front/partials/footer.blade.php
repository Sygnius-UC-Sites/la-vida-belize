@php
    $isHome = request()->is('/');
    $isStayDetail = request()->is('stay/*') || request()->is('condos/*') || request()->is('houses/*');
    $hasLocalBooking = $isHome || request()->is('properties') || request()->is('groups');
    $mobileBookHref = $isStayDetail ? '#booking-widget' : ($hasLocalBooking ? '#booking' : url('/') . '#booking');
@endphp
<footer class="footer"><div class="wrap">
<div class="footer-main">
<div class="footer-brand">
<a class="logo" href="{{ url('/') }}"><img class="logo-mark" src="{{ asset('assets/logo.png') }}" width="160" height="160" alt="La Vida Belize"></a>
<p class="footer-blurb">Three unique accommodations, a peaceful beachfront setting and a little place to call your own near Placencia, Belize.</p>
</div>
<div><h3>Discover</h3>
<a href="{{ url('/') }}">Home</a>
<a href="{{ url('/about-us') }}">About Us</a>
<a href="{{ url('/about-owner') }}">About Owner</a>
<a href="{{ url('/properties') }}">Properties</a>
<a href="{{ url('/attractions') }}">Attractions</a>
<a href="{{ url('/experience') }}">Experience</a>
<a href="{{ url('/groups') }}">Group Stays</a>
</div>
<div><h3>Your stay</h3>
<a href="{{ url('/') }}#booking">Book Now</a>
<a href="{{ url('/reviews') }}">Guest Reviews</a>
<a href="{{ url('/faq') }}">FAQs</a>
<a href="{{ url('/contact') }}">Contact Us</a>
</div>
<div class="footer-contact"><h3>Find us in paradise</h3>
<p class="footer-blurb" style="margin-top:0">204 Plantation, Placencia Road<br>Placencia, Stann Creek, Belize</p>
<a href="mailto:info@lavidabelize.com">info@lavidabelize.com</a>
<a href="tel:+18778235493">+1 (877) 823-5493</a>
</div>
</div>
<div class="footer-bottom">
<span>© <span id="year"></span> La Vida Belize.</span>
<span><a href="{{ url('/privacy') }}">Privacy</a> · <a href="{{ url('/terms') }}">Terms</a></span>
<a class="back-top" href="#top" aria-label="Back to top">↑</a>
</div>
</div></footer>
<a class="mobile-book" href="{{ $mobileBookHref }}">Check dates &amp; book your stay <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
@include('front.partials.booking-popovers')
