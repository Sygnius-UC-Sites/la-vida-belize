@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?: ((optional($category ?? null)->name ?: 'Attractions') . ' — La Vida Belize'))
@section('description', optional($data ?? null)->meta_description ?: optional($category ?? null)->meta_description)
@section('body-class', 'page')

@section('content')
@include('front.partials.attraction-category-page')
@endsection
