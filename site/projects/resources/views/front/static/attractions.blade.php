@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Attractions — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Dining, adventures, taxis, golf carts and car rentals near Placencia.')
@section('body-class', 'page')

@section('content')
@php
    $attractionCategories = \App\Models\AttractionCategory::query()
        ->where('publish', 'published')
        ->orderBy('ordering', 'asc')
        ->orderBy('id', 'asc')
        ->get();
@endphp
<main>
<section class="page-hero"><img alt="" src="{{ asset('assets/peninsula/sunset-palms.webp') }}" width="1600" height="900"><div class="wrap"><div class="eyebrow">Attractions</div><h1>Placencia and<br>everything beyond.</h1></div></section>
<section class="page-copy"><div class="wrap">
<p class="prose">La Vida is close enough to Placencia for restaurants, shops and tours, and quiet enough to come home to the beach. Choose a category to see local favorites from our guide.</p>
<div class="attraction-cats">
@foreach($attractionCategories as $cat)
<a class="explore-card" href="{{ $cat->guestUrl() }}"><img alt="{{ $cat->name }}" src="{{ $cat->imageUrl() }}" width="800" height="600"><div class="explore-content"><span class="category">{{ $cat->shortDescription }}</span><h3>{{ $cat->name }}</h3><p>{{ strip_tags((string) $cat->description) }}</p><span class="go">→</span></div></a>
@endforeach
</div>
</div></section>
</main>
@endsection
