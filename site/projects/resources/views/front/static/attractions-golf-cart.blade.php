@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Golf Cart Rental — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Golf Cart Rental near La Vida Belize in Placencia.')
@section('body-class', 'page')

@section('content')
@include('front.partials.attraction-category-page', ['forcedSlug' => 'golf-cart'])
@endsection
