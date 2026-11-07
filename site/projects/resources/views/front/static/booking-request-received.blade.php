@extends('front.layouts.brad')

@section('title',       $data->meta_title    ?? ('Request received' . ' - ' . ($setting_data['website'] ?? config('app.name', 'Vacation Rentals'))))
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

    $guestName = trim(($booking->first_name ?? '') . ' ' . ($booking->last_name ?? '')) ?: ($booking->name ?? '');
    $nights = $booking
        ? (int) ($booking->total_night
            ?: \Carbon\Carbon::parse($booking->checkin)->diffInDays(\Carbon\Carbon::parse($booking->checkout)))
        : 0;
    $adults   = (int) ($booking->adults ?? 0);
    $children = (int) ($booking->child  ?? 0);
    $pets     = (int) ($booking->total_pets ?? 0);
    $totalGuests = $adults + $children;

    $cancelType = $property?->cancellation_policy_type ?? 'moderate';
    $cancelText = $property?->cancellation_policy ?: \DB::table('basic_settings')
        ->where('name', "cancellation_template_{$cancelType}")->value('value');

    $previewImage = $property?->banner_image
        ?: ($property?->feature_image ?: ($property?->image ?? null));

    $supportEmail = $setting_data['email']  ?? null;
    $supportPhone = $setting_data['mobile'] ?? null;
@endphp

@if(!$booking || !$property)
    <section class="status-page">
        <div class="container">
            <div class="status-card">
                <div class="status-icon status-icon--pending" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </div>
                <h1>{{ $data->name ?? 'Request received' }}</h1>
                <p class="status-page__lead">{{ $data->description ?? "Thanks for your interest. We've sent your request to the host and will get back to you within 24 hours." }}</p>
                <a href="{{ url('/') }}" class="btn-confirm"><span class="btn-confirm__label">Back to home</span></a>
            </div>
        </div>
    </section>
@else
<section class="confirmation-page">
    <div class="container">

        <div class="confirmation-hero">
            <div class="status-icon status-icon--pending" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <polyline points="12 6 12 12 16 14"/>
                </svg>
            </div>
            <h1>Request sent, {{ explode(' ', $guestName)[0] ?: 'guest' }}.</h1>
            <p class="confirmation-hero__lead">
                Your booking request for <strong>{{ $property->name }}</strong> is with the host.
                We'll reply within 24 hours at <strong>{{ $booking->email }}</strong> with approval and a secure payment link — <strong>no charge yet</strong>.
            </p>
            <p class="confirmation-hero__id">Request #{{ str_pad($booking->id, 6, '0', STR_PAD_LEFT) }}</p>
        </div>

        <div class="confirmation-layout">

            {{-- ============= LEFT — request details ============= --}}
            <div class="confirmation-main">

                <div class="confirmation-card">
                    <h2 class="confirmation-card__title">Your trip</h2>
                    <div class="property-summary">
                        @if($previewImage)
                            <img src="{{ \Illuminate\Support\Str::startsWith($previewImage, 'http') ? $previewImage : asset($previewImage) }}"
                                 alt="{{ $property->name }}" loading="lazy">
                        @endif
                        <div class="property-summary__info">
                            <h3>{{ $property->name }}</h3>
                            @if(!empty($property->address))
                                <p>{{ $property->address }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="trip-grid">
                        <div class="trip-grid__cell">
                            <span class="trip-grid__label">Check-in</span>
                            <strong>{{ \Carbon\Carbon::parse($booking->checkin)->format('D, M j, Y') }}</strong>
                        </div>
                        <div class="trip-grid__cell">
                            <span class="trip-grid__label">Check-out</span>
                            <strong>{{ \Carbon\Carbon::parse($booking->checkout)->format('D, M j, Y') }}</strong>
                        </div>
                        <div class="trip-grid__cell">
                            <span class="trip-grid__label">Nights</span>
                            <strong>{{ $nights }}</strong>
                        </div>
                        <div class="trip-grid__cell">
                            <span class="trip-grid__label">Guests</span>
                            <strong>{{ $totalGuests }} {{ \Illuminate\Support\Str::plural('guest', $totalGuests) }}</strong>
                            @php
                                $guestParts = [$adults . ' ' . \Illuminate\Support\Str::plural('adult', $adults)];
                                if ($children > 0) $guestParts[] = $children . ' ' . \Illuminate\Support\Str::plural('child', $children);
                                if ($pets > 0)     $guestParts[] = $pets . ' ' . \Illuminate\Support\Str::plural('pet', $pets);
                            @endphp
                            <small>{{ implode(', ', $guestParts) }}</small>
                        </div>
                    </div>
                </div>

                <div class="confirmation-card">
                    <h2 class="confirmation-card__title">Guest information</h2>
                    <dl class="kv-list">
                        <div class="kv-row"><dt>Name</dt><dd>{{ $guestName ?: '—' }}</dd></div>
                        <div class="kv-row"><dt>Email</dt><dd>{{ $booking->email ?: '—' }}</dd></div>
                        <div class="kv-row"><dt>Phone</dt><dd>{{ $booking->mobile ?: '—' }}</dd></div>
                        @if(!empty($booking->source))
                            <div class="kv-row"><dt>Heard about us via</dt><dd>{{ ucfirst(str_replace('_', ' ', $booking->source)) }}</dd></div>
                        @endif
                        @if(!empty($booking->message))
                            <div class="kv-row"><dt>Note to host</dt><dd>{{ $booking->message }}</dd></div>
                        @endif
                    </dl>
                </div>

                <div class="confirmation-card">
                    <h2 class="confirmation-card__title">What happens next</h2>
                    <ol class="next-steps">
                        <li>
                            <strong>Within 24 hours</strong>
                            <p>The host reviews your request and replies. <strong>If approved</strong>, you'll receive an email at <strong>{{ $booking->email }}</strong> with a secure payment link to confirm.</p>
                        </li>
                        <li>
                            <strong>Once you pay</strong>
                            <p>Your reservation is confirmed and locked in. We send a receipt and the welcome email schedule kicks off.</p>
                        </li>
                        <li>
                            <strong>3 days before check-in</strong>
                            <p>We send directions, gate codes, Wi-Fi credentials, and tips for your stay.</p>
                        </li>
                    </ol>
                </div>

                @if($cancelText)
                    <div class="confirmation-card">
                        <h2 class="confirmation-card__title">Cancellation policy</h2>
                        <span class="cancel-pill">{{ ucfirst($cancelType) }}</span>
                        <p class="confirmation-policy">{{ $cancelText }}</p>
                    </div>
                @endif

                <div class="confirmation-card confirmation-card--help">
                    <h2 class="confirmation-card__title">Need help while you wait?</h2>
                    <div class="help-grid">
                        @if($supportEmail)
                            <a class="help-tile" href="mailto:{{ $supportEmail }}?subject=Booking%20request%20%23{{ $booking->id }}">
                                <span class="help-tile__label">Email us</span>
                                <strong>{{ $supportEmail }}</strong>
                            </a>
                        @endif
                        @if($supportPhone)
                            <a class="help-tile" href="tel:{{ preg_replace('/[^+0-9]/', '', $supportPhone) }}">
                                <span class="help-tile__label">Call us</span>
                                <strong>{{ $supportPhone }}</strong>
                            </a>
                        @endif
                        <a class="help-tile" href="{{ url('faqs') }}">
                            <span class="help-tile__label">FAQs</span>
                            <strong>Browse common questions →</strong>
                        </a>
                    </div>
                </div>
            </div>

            {{-- ============= RIGHT — estimate sidebar ============= --}}
            <aside class="confirmation-sidebar">
                <div class="confirmation-card receipt-card">
                    <h2 class="confirmation-card__title">Estimated total</h2>
                    <p class="form-note" style="margin:0 0 12px;">No charge yet — this is your projected total when the host approves.</p>

                    @if($nights > 0 && (float) $booking->gross_amount > 0)
                        <div class="receipt-row">
                            <span>{{ '$' . number_format((float)$booking->gross_amount / max(1, $nights), 2) }} × {{ $nights }} {{ \Illuminate\Support\Str::plural('night', $nights) }}</span>
                            <span>${{ number_format((float)$booking->gross_amount, 2) }}</span>
                        </div>
                    @endif
                    @if((float) ($booking->guest_fee ?? 0) > 0)
                        <div class="receipt-row"><span>Extra guest fee</span><span>${{ number_format((float)$booking->guest_fee, 2) }}</span></div>
                    @endif
                    @if((float) ($booking->pet_fee ?? 0) > 0)
                        <div class="receipt-row"><span>Pet fee</span><span>${{ number_format((float)$booking->pet_fee, 2) }}</span></div>
                    @endif
                    @if((float) ($property->cleaning_fee ?? 0) > 0)
                        <div class="receipt-row"><span>Cleaning fee</span><span>${{ number_format((float)$property->cleaning_fee, 2) }}</span></div>
                    @endif
                    @if((float) ($booking->discount ?? 0) > 0)
                        <div class="receipt-row receipt-row--discount">
                            <span>{{ !empty($booking->discount_coupon) ? 'Coupon (' . strtoupper($booking->discount_coupon) . ')' : 'Discount' }}</span>
                            <span>−${{ number_format((float)$booking->discount, 2) }}</span>
                        </div>
                    @endif
                    @if((float) ($booking->tax ?? 0) > 0)
                        <div class="receipt-row"><span>Tax</span><span>${{ number_format((float)$booking->tax, 2) }}</span></div>
                    @endif

                    <div class="receipt-total">
                        <span>Estimated total</span>
                        <span>${{ number_format((float)$booking->total_amount, 2) }}</span>
                    </div>
                    <p class="receipt-payment-method">You'll be charged once the host approves.</p>
                </div>

                <button type="button" class="btn-secondary confirmation-print" onclick="window.print()" aria-label="Print or save this request">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="6 9 6 2 18 2 18 9"/>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                        <rect x="6" y="14" width="12" height="8"/>
                    </svg>
                    <span>Print or save as PDF</span>
                </button>

                <a href="{{ url('/') }}" class="btn-confirm confirmation-cta">
                    <span class="btn-confirm__label">Back to home</span>
                </a>
            </aside>
        </div>
    </div>
</section>

{{-- Same back-navigation safeguards as booking-confirmed: drop a sessionStorage
     breadcrumb so checkout would bounce the guest to home if they tried to go
     back, plus a beforeunload prompt and bfcache pageshow guard. The request
     row is idempotency-keyed so even an accidental re-POST is a no-op. --}}
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<script>
(function () {
    var safeUrl = @json(url('/'));
    try { sessionStorage.setItem('booking_completed', String(Date.now())); } catch (_) {}
    if (window.history.replaceState) window.history.replaceState({ booked: true }, '', window.location.href);
    if (window.history.pushState)    window.history.pushState({ booked: true }, '', window.location.href);
    window.addEventListener('popstate', function () { window.location.replace(safeUrl); });
    window.addEventListener('pageshow', function (e) { if (e.persisted) window.location.replace(safeUrl); });
    // QA D-007: removed the unconditional beforeunload prompt — it fired a scary
    // browser "Leave site?" dialog even when the guest clicked this page's own
    // "Back to home" link. The popstate/pageshow guards above already bounce
    // back-navigation / bfcache restores to the home page, so the prompt added
    // friction with no real protection (the request row is idempotency-keyed).
})();
</script>
@endif
@endsection
