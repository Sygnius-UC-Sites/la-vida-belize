@extends('front.layouts.brad')

@section('title',       $data->meta_title    ?? "Thanks — we'll be in touch")
@section('keywords',    $data->meta_keywords ?? '')
@section('description', $data->meta_description ?? '')
@section('body-class', 'page booking-page')

@section('head')
    @include('front.partials.booking-funnel-head')
@endsection

@section('content')
    @php
        $firstName    = trim(strtok((string) ($data1->name ?? ''), ' ')) ?: 'there';
        $supportEmail = $setting_data['email']  ?? null;
        $supportPhone = $setting_data['mobile'] ?? null;
        $submittedAt  = optional($data1->created_at)->copy() ?? \Carbon\Carbon::now();
        $reference    = '#' . str_pad($data1->id, 6, '0', STR_PAD_LEFT);
        $linkedProperty = !empty($data1->property_slug)
            ? \App\Models\Property::where('seo_url', $data1->property_slug)->first()
            : null;
        // Owner's chosen header bg, or the design default banner.
        $bannerImage = $setting_data['contact_us_thankyou_image'] ?? 'front/themes/base/img/placeholder.svg';
    @endphp

    <section class="confirmation-page">
        <div class="container">

            <div class="confirmation-hero">
                <div class="status-icon status-icon--success" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <h1>Thanks, {{ $firstName }}.</h1>
                <p class="confirmation-hero__lead">
                    Your message is in our inbox. We typically reply within
                    <strong>2&ndash;4 hours</strong> during the day at
                    <strong>{{ $data1->email }}</strong>. No need to refresh — we'll come to you.
                </p>
                <p class="confirmation-hero__id">Reference {{ $reference }}</p>
            </div>

            <div class="confirmation-layout">

                {{-- ============= LEFT — what happens next + recap ============= --}}
                <div class="confirmation-main">

                    <div class="confirmation-card">
                        <h2 class="confirmation-card__title">What happens next</h2>
                        <ol class="next-steps">
                            <li>
                                <strong>Within 2&ndash;4 hours</strong>
                                <p>We read every message ourselves — no auto-replies. You'll get a thoughtful response with availability, pricing, or whatever you asked for.</p>
                            </li>
                            <li>
                                <strong>If we suggest dates</strong>
                                <p>We'll send a quick quote and a secure payment link. You can lock in your stay in a couple of clicks.</p>
                            </li>
                            <li>
                                <strong>Once confirmed</strong>
                                <p>You get welcome notes, directions, and check-in details a few days before arrival — plus any local tips we think you'll love.</p>
                            </li>
                        </ol>
                    </div>

                    @if(!empty($data1->message))
                        <div class="confirmation-card">
                            <h2 class="confirmation-card__title">Your message</h2>
                            <blockquote class="enquiry-quote">{{ $data1->message }}</blockquote>
                            <p class="confirmation-meta">
                                Sent {{ $submittedAt->diffForHumans() }} from <strong>{{ $data1->email }}</strong>
                                @if(!empty($data1->mobile)) &middot; {{ $data1->mobile }} @endif
                            </p>
                        </div>
                    @endif

                    @if($linkedProperty)
                        <div class="confirmation-card">
                            <h2 class="confirmation-card__title">About the property you asked about</h2>
                            <div class="property-summary">
                                @if(!empty($linkedProperty->banner_image))
                                    <img src="{{ asset($linkedProperty->banner_image) }}" alt="{{ $linkedProperty->name }}" loading="lazy">
                                @endif
                                <div class="property-summary__info">
                                    <h3>{{ $linkedProperty->name }}</h3>
                                    @if(!empty($linkedProperty->address))
                                        <p>{{ $linkedProperty->address }}</p>
                                    @endif
                                    <a href="{{ url($linkedProperty->seo_url) }}"
                                       class="property-summary__cta">View property &rarr;</a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="confirmation-card confirmation-card--help">
                        <h2 class="confirmation-card__title">Want to keep exploring while you wait?</h2>
                        <div class="help-grid">
                            <a class="help-tile" href="{{ url('stays') }}">
                                <span class="help-tile__label">Browse</span>
                                <strong>All stays &rarr;</strong>
                            </a>
                            <a class="help-tile" href="{{ url('faqs') }}">
                                <span class="help-tile__label">Help</span>
                                <strong>FAQs &rarr;</strong>
                            </a>
                            @if($supportEmail)
                                <a class="help-tile" href="mailto:{{ $supportEmail }}?subject=Re%3A%20{{ $reference }}">
                                    <span class="help-tile__label">Email</span>
                                    <strong>{{ $supportEmail }}</strong>
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- ============= RIGHT — quick-glance sidebar ============= --}}
                @php
                    $rawIn  = $data1->checkin  ?? $data1->check_in  ?? null;
                    $rawOut = $data1->checkout ?? $data1->check_out ?? null;
                    $ciCarbon = $rawIn  ? \Carbon\Carbon::parse($rawIn)  : null;
                    $coCarbon = $rawOut ? \Carbon\Carbon::parse($rawOut) : null;
                    $nightsApprox = ($ciCarbon && $coCarbon) ? max(0, $ciCarbon->diffInDays($coCarbon)) : null;
                @endphp
                <aside class="confirmation-sidebar">
                    <div class="confirmation-card receipt-card">
                        <h2 class="confirmation-card__title">Your enquiry at a glance</h2>

                        {{-- Tentative dates as a proper trip-grid card so check-in / check-out
                             are immediately readable instead of squished into a sidebar kv row. --}}
                        @if($ciCarbon && $coCarbon)
                            <div class="enquiry-dates">
                                <div class="enquiry-dates__cell">
                                    <span class="enquiry-dates__label">Check-in</span>
                                    <span class="enquiry-dates__value">{{ $ciCarbon->format('D, M j') }}</span>
                                    <span class="enquiry-dates__year">{{ $ciCarbon->format('Y') }}</span>
                                </div>
                                <div class="enquiry-dates__sep" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                                </div>
                                <div class="enquiry-dates__cell">
                                    <span class="enquiry-dates__label">Check-out</span>
                                    <span class="enquiry-dates__value">{{ $coCarbon->format('D, M j') }}</span>
                                    <span class="enquiry-dates__year">{{ $coCarbon->format('Y') }}</span>
                                </div>
                            </div>
                            @if($nightsApprox !== null)
                                <p class="enquiry-dates__nights">{{ $nightsApprox }} night{{ $nightsApprox === 1 ? '' : 's' }} {{ !empty($data1->guests) ? '· '.$data1->guests.' guest'.((string)$data1->guests === '1' ? '' : 's') : '' }}</p>
                            @endif
                        @elseif(!empty($data1->guests))
                            <div class="enquiry-dates enquiry-dates--guests-only">
                                <div class="enquiry-dates__cell enquiry-dates__cell--full">
                                    <span class="enquiry-dates__label">Guests</span>
                                    <span class="enquiry-dates__value">{{ $data1->guests }}</span>
                                </div>
                            </div>
                        @endif

                        {{-- Stacked spec rows. Label sits on top in tiny uppercase, value
                             below in bold — reads cleanly even in a 320px-wide sidebar
                             (the previous `180px / 1fr` grid was clipping values). --}}
                        <ul class="spec-list">
                            <li class="spec-list__row">
                                <span class="spec-list__label">Reference</span>
                                <span class="spec-list__value spec-list__value--mono">{{ $reference }}</span>
                            </li>
                            <li class="spec-list__row">
                                <span class="spec-list__label">Submitted</span>
                                <span class="spec-list__value">{{ $submittedAt->format('M j, Y') }} <span class="spec-list__sub">at {{ $submittedAt->format('g:i A') }}</span></span>
                            </li>
                            <li class="spec-list__row">
                                <span class="spec-list__label">Reply will go to</span>
                                <span class="spec-list__value">{{ $data1->email }}</span>
                            </li>
                            @if(!empty($data1->mobile))
                                <li class="spec-list__row">
                                    <span class="spec-list__label">Phone</span>
                                    <span class="spec-list__value">{{ $data1->mobile }}</span>
                                </li>
                            @endif
                        </ul>

                        <div class="response-meter">
                            <div class="response-meter__bar"><span style="width:80%"></span></div>
                            <div class="response-meter__caption">
                                <strong>2&ndash;4 hr</strong> typical reply window
                            </div>
                        </div>

                        <a href="{{ url('/') }}" class="btn-confirm confirmation-cta">
                            <span class="btn-confirm__label">Back to home</span>
                        </a>
                        <p class="confirmation-meta confirmation-meta--center">
                            Tip: check your <strong>Spam</strong> or <strong>Promotions</strong> folder if our reply
                            doesn't show up.
                        </p>
                    </div>
                </aside>

            </div>
        </div>
    </section>

    {{-- Same back-trap as booking-confirmed: refresh / Back from this page
         shouldn't drop the guest back into the form (they'd think they need
         to resubmit). Send them home instead. --}}
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <script>
        (function () {
            var safeUrl = @json(url('/'));
            try { sessionStorage.setItem('booking_completed', String(Date.now())); } catch (_) {}
            if (window.history.pushState) window.history.pushState({ submitted: true }, '', window.location.href);
            window.addEventListener('popstate', function () { window.location.replace(safeUrl); });
            window.addEventListener('pageshow', function (e) { if (e.persisted) window.location.replace(safeUrl); });
        })();
    </script>
@endsection
