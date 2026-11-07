@extends('front.layouts.brad')

@section('title',       $cms->meta_title    ?? 'Link Expired')
@section('keywords',    $cms->meta_keywords ?? '')
@section('description', $cms->meta_description ?? '')
@section('body-class', 'page booking-page magic-link-expired-body')

@section('head')
    @include('front.partials.booking-funnel-head')
@endsection

@section('content')

<section class="magic-link-expired" style="padding:80px 0;">
    <div class="container" style="max-width:640px;text-align:center;">

        <h1 style="font-size:2em;margin-bottom:16px;">Link Expired</h1>

        <p style="font-size:1.1em;color:#555;margin-bottom:24px;">
            {{ $cms->description ?? 'This payment link is no longer valid. It may have expired, been used already, or the URL was modified.' }}
        </p>

        <p style="margin-bottom:32px;">
            Please contact us and we'll send you a fresh payment link right away.
        </p>

        <p>
            <a href="{{ url('contact-us') }}"
               style="display:inline-block;padding:14px 32px;background:#1a73e8;color:#fff;border-radius:6px;text-decoration:none;font-weight:bold;">
                Contact Us
            </a>
        </p>

    </div>
</section>

@endsection
