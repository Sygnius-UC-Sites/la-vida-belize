@extends('front.layouts.brad')

@section('title',       $data->meta_title    ?? ('Payment Received' . ' - ' . ($setting_data['website'] ?? config('app.name', 'Vacation Rentals'))))
@section('keywords',    $data->meta_keywords ?? '')
@section('description', $data->meta_description ?? '')
@section('body-class', 'page booking-page')

@section('head')
    @include('front.partials.booking-funnel-head')
@endsection

@section('content')
@php
    $bookingId = request('booking_id');
    $booking   = $bookingId ? \App\Models\BookingRequest::find($bookingId) : null;
    $property  = $booking ? \App\Models\Property::find($booking->property_id) : null;
    $paidAmount = $booking
        ? (float) \App\Models\Payment::where('booking_id', $booking->id)
            ->whereIn('status', ['complete', 'completed', 'succeeded', 'paid'])
            ->sum('amount')
        : 0.0;
    $amountData = $booking && $booking->amount_data ? (json_decode((string) $booking->amount_data, true) ?: []) : [];
    if ($booking && $paidAmount <= 0.01 && isset($amountData['installments']) && is_array($amountData['installments'])) {
        $firstInstallment = $amountData['installments'][0] ?? null;
        if (is_array($firstInstallment)) {
            $paidAmount = (float) ($firstInstallment['amount'] ?? 0);
        }
    }
    $bookingTotal = (float) ($booking->total_amount ?? 0);
    $hasPartialPayment = $booking && $paidAmount > 0.01 && $paidAmount + 0.01 < $bookingTotal;
@endphp

<section class="status-page">
    <div class="container">
        <div class="status-card">
            <div class="status-icon status-icon--success" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </div>
            <h1>{{ $data->name ?? 'Payment received' }}</h1>
            <p class="status-page__lead">{{ $data->description ?? "Thank you — your payment has been processed and your stay is confirmed. We've sent a receipt to your email." }}</p>

            @if($booking && $property)
                <div class="status-summary">
                    <div class="status-summary__property">
                        <h3>{{ $property->name }}</h3>
                    </div>
                    @if($booking->checkin && $booking->checkout)
                        <div class="status-summary__row">
                            <span>Check-in</span>
                            <strong>{{ \Carbon\Carbon::parse($booking->checkin)->format('M j, Y') }}</strong>
                        </div>
                        <div class="status-summary__row">
                            <span>Check-out</span>
                            <strong>{{ \Carbon\Carbon::parse($booking->checkout)->format('M j, Y') }}</strong>
                        </div>
                    @endif
                    <div class="status-summary__row status-summary__row--total">
                        <span>{{ $hasPartialPayment ? 'Paid today' : 'Total paid' }}</span>
                        <strong>${{ number_format($hasPartialPayment ? $paidAmount : $bookingTotal, 2) }}</strong>
                    </div>
                    @if($hasPartialPayment)
                        <div class="status-summary__row">
                            <span>Balance due</span>
                            <strong>${{ number_format(max(0, $bookingTotal - $paidAmount), 2) }}</strong>
                        </div>
                    @endif
                    <p class="status-summary__id">Booking #{{ $booking->id }}</p>
                </div>
                <p class="status-page__hint">A welcome email with property details, directions and check-in instructions will arrive a few days before your stay.</p>
            @endif

            <a href="{{ url('/') }}" class="btn-confirm">
                <span class="btn-confirm__label">Back to home</span>
            </a>
        </div>
    </div>
</section>
@endsection
