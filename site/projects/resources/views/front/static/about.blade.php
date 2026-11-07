@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'About Us — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'About La Vida Belize, a small beachfront retreat in Placencia.')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-hero"><img alt="" src="{{ asset('assets/image-14-2c6c09ac4a38.webp') }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">About us</div><h1>A home in Placencia.</h1></div></section>
<section class="page-copy"><div class="wrap">
<div class="split-block">
<div class="prose">
<p>La Vida Belize is a small beachfront retreat in Placencia, created for travelers who want a private coastal stay rather than a large resort. The Caribbean is steps from the accommodations, with sandy beach, ocean views, and a setting meant for easy indoor-outdoor living.</p>
<p>The homes are crafted from Belizean hardwoods sourced in the nearby Maya Mountains. Thoughtful design, fully equipped kitchens, air conditioning and French doors toward the sea make each stay feel like a house you can settle into.</p>
<p>Guests share kayaks, bicycles, snorkeling on nearby coral heads, and a short drive to Maya Beach and Placencia Village. Tour operators often provide courtesy pick-up from the property.</p>
<p>Whether you are planning a couple’s getaway, a family week, or a gathering of friends, Casa, Casita and Studio let you choose the space that fits — or take more than one home and still keep the island feeling.</p>
<a class="button primary" href="{{ url('/contact') }}">Say hello <svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
</div>
<img alt="Casita among palms" src="{{ asset('assets/image-14-2c6c09ac4a38.webp') }}" width="1000" height="800">
</div>
</div></section>
</main>
@endsection
