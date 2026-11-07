@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: 'Car Rental — La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?: 'Car Rental near La Vida Belize in Placencia.')
@section('body-class', 'page')

@section('content')
@include('front.partials.attraction-category-page', ['forcedSlug' => 'car-rental'])
@endsection
