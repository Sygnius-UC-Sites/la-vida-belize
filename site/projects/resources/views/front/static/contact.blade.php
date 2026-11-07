@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Contact — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Contact La Vida Belize in Placencia. Address, phone, email, WhatsApp and a message form.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero contact-hero">
<img alt="Beachfront at La Vida Belize" src="{{ asset('assets/peninsula/sunset-palms.webp') }}" width="1600" height="900" fetchpriority="high">
<div class="wrap contact-hero-copy">
<div class="eyebrow">Contact</div>
<h1>Tell us what kind of stay you are planning.</h1>
<p class="hero-lead">Questions about La Vida Belize? We will get back to you soon.</p>
</div>
</section>
<section class="contact-body">
<div class="wrap">
<div class="contact-intro">
<div class="eyebrow">Get in touch</div>
<h2>Questions before you book?</h2>
<p>Email La Vida Belize or use the form below and we will get back to you soon.</p>
</div>
<div class="contact-split">
<aside class="contact-side">
<div class="contact-details">
<h3>Contact details</h3>
<dl>
<div>
<dt>Email</dt>
<dd><a href="mailto:info@lavidabelize.com">info@lavidabelize.com</a></dd>
</div>
<div>
<dt>Phone</dt>
<dd><a href="tel:+18778235493">+1 877-823-5493</a></dd>
</div>
<div>
<dt>WhatsApp</dt>
<dd><a href="https://wa.me/5016278977">+501-627-8977</a></dd>
</div>
<div>
<dt>Property</dt>
<dd>204 Plantation, Placencia Road<br>Placencia, Stann Creek, Belize</dd>
</div>
</dl>
</div>
<div class="contact-map">
<iframe title="La Vida Belize on Google Maps" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://maps.google.com/maps?q=La%20Vida%20Belize%2C%20204%20Plantation%2C%20Placencia%20Road%2C%20Placencia%2C%20Belize&amp;z=14&amp;output=embed"></iframe>
</div>
</aside>
<form class="inquiry-card" id="contactForm" method="post" action="{{ route('contactPost') }}">
@csrf
<input type="hidden" name="_form_ts" value="{{ \App\Services\Spam\Checks\TimeTrapCheck::generateToken() }}">
<div style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden" aria-hidden="true">
<label for="website">Website</label>
<input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
</div>
@if(session('success'))
<p class="prose">{{ session('success') }}</p>
@endif
@if(session('danger'))
<p class="prose">{{ session('danger') }}</p>
@endif
<div class="inquiry-grid">
<div class="field">
<label for="contactName">Full name *</label>
<input id="contactName" name="name" type="text" autocomplete="name" value="{{ old('name') }}" required>
</div>
<div class="field">
<label for="contactEmail">Email *</label>
<input id="contactEmail" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required>
</div>
<div class="field field-full">
<label for="contactPhone">Phone *</label>
<input id="contactPhone" name="phone" type="tel" autocomplete="tel" value="{{ old('phone') }}" required>
</div>
<div class="field field-full">
<label for="contactMessage">Message *</label>
<textarea id="contactMessage" name="message" rows="4" required placeholder="How can we help?">{{ old('message') }}</textarea>
</div>
</div>
<button class="inquiry-submit" type="submit">Send Message</button>
</form>
</div>
</div>
</section>
</main>
@endsection
