@extends('front.layouts.brad')

@section('title',       $data->meta_title    ?? ('Payment Failed' . ' - ' . ($setting_data['website'] ?? config('app.name', 'Vacation Rentals'))))
@section('keywords',    $data->meta_keywords ?? '')
@section('description', $data->meta_description ?? '')
@section('body-class', 'page booking-page')

@section('head')
    @include('front.partials.booking-funnel-head')
@endsection

@section('content')
<section class="status-page">
    <div class="container">
        <div class="status-card">
            <div class="status-icon status-icon--error" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6"  y1="6" x2="18" y2="18"/>
                </svg>
            </div>
            <h1>{{ $data->name ?? "Payment didn't go through" }}</h1>
            <p class="status-page__lead">{{ $data->description ?? "We couldn't process your payment. No charge was made. You can try again with the same card or a different one, or get in touch and we'll help you finish the booking." }}</p>

            <div class="status-page__actions">
                <a href="{{ url()->previous() }}" class="btn-confirm">
                    <span class="btn-confirm__label">Try again</span>
                </a>
                <a href="{{ url('contact-us') }}" class="btn-secondary">Contact us</a>
            </div>
        </div>
    </div>
</section>
@endsection
