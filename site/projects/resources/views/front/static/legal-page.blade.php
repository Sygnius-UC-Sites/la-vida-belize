@extends('front.layouts.brad')

@section('title', optional($data ?? null)->meta_title ?? 'Legal · La Vida Belize')
@section('description', optional($data ?? null)->meta_description ?? '')
@section('body-class', 'page')

@section('content')
<main>
<section class="page-copy legal"><div class="wrap">
<div class="eyebrow">Legal</div>
<h1>{{ optional($data ?? null)->fw_banner_title ?: (optional($data ?? null)->name ?: 'Legal') }}</h1>
{!! optional($data ?? null)->description ?: optional($data ?? null)->longDescription !!}
</div></section>
</main>
@endsection
