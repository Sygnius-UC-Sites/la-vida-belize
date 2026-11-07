@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'About Owner — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Contact Brad, owner of La Vida Belize in Placencia.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero"><img alt="" src="{{ asset('assets/image-13-1a4592828319.webp') }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">About owner</div><h1>About Brad.</h1></div></section>
<section class="page-copy"><div class="wrap">
<p class="prose">Brad is the owner of La Vida Belize. Reach him by phone or email, or book a stay when you are ready.</p>
<div class="contact-grid" style="margin-top:36px">
<div class="contact-card"><h3>Phone</h3><p><a href="tel:+18778235493">+1 877-823-5493</a></p></div>
<div class="contact-card"><h3>Email</h3><p><a href="mailto:info@lavidabelize.com">info@lavidabelize.com</a></p></div>
</div>
<a class="button primary" href="{{ url('/') }}#booking" style="margin-top:36px">Book Now <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
</div></section>
</main>
@endsection
