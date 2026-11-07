@extends('front.layouts.brad')

@section('title',       $data->meta_title    ?? ("You're booked" . ' - ' . ($setting_data['website'] ?? config('app.name', 'Vacation Rentals'))))
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
    // PMS bookings store the PMS room's id in property_id — resolve the
    // published site property linked to that room so the receipt shows the
    // full stay details instead of the defensive empty state.
    if ($booking && !$property) {
        $property = \App\Models\Property::where('pms_room_id', $booking->property_id)->first();
    }

    $guestName = trim(($booking->first_name ?? '') . ' ' . ($booking->last_name ?? '')) ?: ($booking->name ?? '');
    $nights = $booking
        ? (int) ($booking->total_night
            ?: \Carbon\Carbon::parse($booking->checkin)->diffInDays(\Carbon\Carbon::parse($booking->checkout)))
        : 0;
    $adults   = (int) ($booking->adults ?? 0);
    $children = (int) ($booking->child  ?? 0);
    $pets     = (int) ($booking->total_pets ?? 0);
    $totalGuests = $adults + $children;

    // Decode amount_data for the receipt breakdown — booking stores it as
    // a JSON snapshot of installments / line items at the moment of payment.
    $amountData = is_string($booking->amount_data ?? null)
        ? (json_decode($booking->amount_data, true) ?: [])
        : (is_array($booking->amount_data ?? null) ? $booking->amount_data : []);
    $paymentScheduleRows = [];
    if (isset($amountData['installments']) && is_array($amountData['installments'])) {
        foreach ($amountData['installments'] as $row) {
            if (!is_array($row) || empty($row['amount'])) continue;
            $sequence = (int) ($row['sequence'] ?? (count($paymentScheduleRows) + 1));
            $paymentScheduleRows[] = [
                'label' => $row['label'] ?? ('Installment ' . $sequence),
                'amount' => (float) $row['amount'],
                'date_due' => $row['date_due'] ?? $row['due_date'] ?? null,
                'is_current' => ($row['is_current'] ?? false) || $sequence === 1,
            ];
        }
    } elseif (array_is_list($amountData)) {
        foreach ($amountData as $idx => $row) {
            if (!is_array($row) || empty($row['amount'])) continue;
            $paymentScheduleRows[] = [
                'label' => $row['label'] ?? $row['message'] ?? ('Installment ' . ($idx + 1)),
                'amount' => (float) $row['amount'],
                'date_due' => $row['date_due'] ?? $row['due_date'] ?? $row['date'] ?? null,
                'is_current' => ($row['is_current'] ?? false) || ($idx === 0 && ($row['status'] ?? null) === 'complete'),
            ];
        }
    }

    $cancelType  = $property?->cancellation_policy_type ?? 'moderate';
    $cancelText  = $property?->cancellation_policy ?: \DB::table('basic_settings')
        ->where('name', "cancellation_template_{$cancelType}")->value('value');

    $previewImage = $property?->banner_image
        ?: ($property?->feature_image ?: ($property?->image ?? null));

    $supportEmail = $setting_data['email']  ?? null;
    $supportPhone = $setting_data['mobile'] ?? null;
@endphp

@if(!$booking || !$property)
    {{-- Defensive empty state when the URL is missing or invalid. --}}
    <section class="status-page">
        <div class="container">
            <div class="status-card">
                <div class="status-icon status-icon--success" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </div>
                <h1>{{ $data->name ?? "You're booked!" }}</h1>
                <p class="status-page__lead">{{ $data->description ?? 'Your reservation is confirmed.' }}</p>
                <a href="{{ url('/') }}" class="btn-confirm"><span class="btn-confirm__label">{{ Helper::label($data,'bc_back_home','Back to home') }}</span></a>
            </div>
        </div>
    </section>
@else
<section class="confirmation-page">
    <div class="container">

        {{-- Top success banner — friendly, premium-feeling. --}}
        <div class="confirmation-hero">
            <div class="status-icon status-icon--success" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </div>
            <h1>You're booked, {{ explode(' ', $guestName)[0] ?: 'guest' }}.</h1>
            <p class="confirmation-hero__lead">
                Your reservation at <strong>{{ $property->name }}</strong> is confirmed.
                A receipt has been emailed to <strong>{{ $booking->email }}</strong>.
            </p>
            <p class="confirmation-hero__id">Confirmation #{{ str_pad($booking->id, 6, '0', STR_PAD_LEFT) }}</p>
        </div>

        <div class="confirmation-layout">

            {{-- ============= LEFT — trip details + guest + next steps ============= --}}
            <div class="confirmation-main">

                {{-- Property + dates card --}}
                <div class="confirmation-card">
                    <h2 class="confirmation-card__title">{{ Helper::label($data,'bc_stay_title','Your stay') }}</h2>
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
                            <span class="trip-grid__label">{{ Helper::label($data,'bc_checkin','Check-in') }}</span>
                            <strong>{{ \Carbon\Carbon::parse($booking->checkin)->format('D, M j, Y') }}</strong>
                            @if(!empty($property->check_in_time))
                                <small>From {{ $property->check_in_time }}</small>
                            @endif
                        </div>
                        <div class="trip-grid__cell">
                            <span class="trip-grid__label">{{ Helper::label($data,'bc_checkout','Check-out') }}</span>
                            <strong>{{ \Carbon\Carbon::parse($booking->checkout)->format('D, M j, Y') }}</strong>
                            @if(!empty($property->check_out_time))
                                <small>By {{ $property->check_out_time }}</small>
                            @endif
                        </div>
                        <div class="trip-grid__cell">
                            <span class="trip-grid__label">{{ Helper::label($data,'bc_nights','Nights') }}</span>
                            <strong>{{ $nights }}</strong>
                        </div>
                        <div class="trip-grid__cell">
                            <span class="trip-grid__label">{{ Helper::label($data,'bc_guests','Guests') }}</span>
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

                {{-- Guest information --}}
                <div class="confirmation-card">
                    <h2 class="confirmation-card__title">{{ Helper::label($data,'bc_guest_info_title','Guest information') }}</h2>
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

                {{-- What's next --}}
                <div class="confirmation-card">
                    <h2 class="confirmation-card__title">{{ Helper::label($data,'bc_next_title','What happens next') }}</h2>
                    <ol class="next-steps">
                        <li>
                            <strong>Today</strong>
                            <p>You'll receive a confirmation email at <strong>{{ $booking->email }}</strong> with these details and a link back to this page.</p>
                        </li>
                        <li>
                            <strong>3 days before check-in</strong>
                            <p>{!! Helper::label($data,'bc_next_step1','We send a welcome email with directions, gate codes, Wi-Fi credentials, and tips for your stay.') !!}</p>
                        </li>
                        <li>
                            <strong>Day of arrival</strong>
                            <p>Self check-in from {{ $property->check_in_time ?? '3:00 PM' }}. Your host is on call for anything you need.</p>
                        </li>
                        <li>
                            <strong>After your stay</strong>
                            <p>{!! Helper::label($data,'bc_next_step2',"We'll follow up with a quick review request. Your refundable deposit (if any) is released within 7 days.") !!}</p>
                        </li>
                    </ol>
                </div>

                {{-- Cancellation policy --}}
                @if($cancelText)
                    <div class="confirmation-card">
                        <h2 class="confirmation-card__title">{{ Helper::label($data,'bc_cancel_title','Cancellation policy') }}</h2>
                        <span class="cancel-pill">{{ ucfirst($cancelType) }}</span>
                        <p class="confirmation-policy">{{ $cancelText }}</p>
                    </div>
                @endif

                {{-- Need help --}}
                <div class="confirmation-card confirmation-card--help">
                    <h2 class="confirmation-card__title">{{ Helper::label($data,'bc_help_title','Need help with your reservation?') }}</h2>
                    <div class="help-grid">
                        @if($supportEmail)
                            <a class="help-tile" href="mailto:{{ $supportEmail }}?subject=Booking%20%23{{ $booking->id }}">
                                <span class="help-tile__label">{{ Helper::label($data,'bc_help_email','Email us') }}</span>
                                <strong>{{ $supportEmail }}</strong>
                            </a>
                        @endif
                        @if($supportPhone)
                            <a class="help-tile" href="tel:{{ preg_replace('/[^+0-9]/', '', $supportPhone) }}">
                                <span class="help-tile__label">{{ Helper::label($data,'bc_help_call','Call us') }}</span>
                                <strong>{{ $supportPhone }}</strong>
                            </a>
                        @endif
                        <a class="help-tile" href="{{ url('faqs') }}">
                            <span class="help-tile__label">{{ Helper::label($data,'bc_help_faqs','FAQs') }}</span>
                            <strong>Browse common questions →</strong>
                        </a>
                    </div>
                </div>
            </div>

            {{-- ============= RIGHT — receipt sidebar ============= --}}
            <aside class="confirmation-sidebar">
                <div class="confirmation-card receipt-card">
                    <h2 class="confirmation-card__title">{{ Helper::label($data,'bc_receipt_title','Receipt') }}</h2>

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
                    @if((float) ($booking->heating_pool_fee ?? 0) > 0)
                        <div class="receipt-row"><span>Pool heating</span><span>${{ number_format((float)$booking->heating_pool_fee, 2) }}</span></div>
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
                    @if((float) ($property->security_deposit_amount ?? 0) > 0 && (bool)($property->security_deposit_charge_at_booking ?? false))
                        <div class="receipt-row"><span>Refundable deposit</span><span>${{ number_format((float)$property->security_deposit_amount, 2) }}</span></div>
                    @endif

                    @php
                        // What was ACTUALLY charged. Installment bookings collect only
                        // the deposit today — showing total_amount as "Total paid" told
                        // guests they'd paid the full amount. Sum real payment records;
                        // when none exist (legacy flows) fall back to the old display.
                        $paidAmount = (float) \App\Models\Payment::where('booking_id', $booking->id)
                            ->whereIn('status', ['complete', 'completed', 'succeeded', 'paid'])
                            ->sum('amount');
                        if ($paidAmount <= 0.01 && !empty($paymentScheduleRows)) {
                            $currentRows = array_filter($paymentScheduleRows, fn ($row) => !empty($row['is_current']));
                            if (!empty($currentRows)) {
                                $paidAmount = (float) array_sum(array_map(fn ($row) => (float) ($row['amount'] ?? 0), $currentRows));
                            } elseif ((float) ($booking->deposit_due_amount ?? 0) > 0) {
                                $paidAmount = (float) $booking->deposit_due_amount;
                            } elseif (count($paymentScheduleRows) > 1) {
                                $paidAmount = (float) ($paymentScheduleRows[0]['amount'] ?? 0);
                            }
                        }
                        $hasPartial = $paidAmount > 0.01
                            && $paidAmount + 0.01 < (float) $booking->total_amount;
                        $balanceDue = max(0, (float) $booking->total_amount - $paidAmount);
                    @endphp

                    <div class="receipt-total">
                        <span>{{ $hasPartial ? 'Total' : 'Total paid' }}</span>
                        <span>${{ number_format((float)$booking->total_amount, 2) }}</span>
                    </div>
                    @if($hasPartial)
                        <div class="receipt-row" style="font-weight:600;">
                            <span>{{ Helper::label($data,'bc_paid_today','Paid today') }}</span>
                            <span>${{ number_format($paidAmount, 2) }}</span>
                        </div>
                        <div class="receipt-row">
                            <span>{{ Helper::label($data,'bc_balance_due','Balance due') }}</span>
                            <span>${{ number_format($balanceDue, 2) }}</span>
                        </div>
                    @endif

                    {{-- Optional: payment-schedule installments if the booking has them --}}
                    @if(count($paymentScheduleRows) > 1)
                        <div class="receipt-installments">
                            <strong>Payment schedule</strong>
                            <ul>
                                @foreach($paymentScheduleRows as $row)
                                    @if(!empty($row['amount']))
                                        @php
                                            // PMS rows: {amount, date_due, label, is_current}; native
                                            // rows: {amount, date|due_date}. Lodgify due dates can be
                                            // literal text ("On agreement") — never Carbon::parse raw.
                                            $rawDue = $row['date'] ?? $row['due_date'] ?? $row['date_due'] ?? null;
                                            $dueTs  = $rawDue ? strtotime($rawDue) : false;
                                            $isPaid = !empty($row['is_current']);
                                        @endphp
                                        <li>
                                            <span>
                                                {{ $row['label'] ?? ('Installment ' . $loop->iteration) }}
                                                @if($dueTs) — due {{ date('M j, Y', $dueTs) }} @endif
                                                @if($isPaid) <span style="color:#2d8a4e;">✓ paid</span> @endif
                                            </span>
                                            <span>${{ number_format((float)$row['amount'], 2) }}</span>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(!empty($booking->payment_method))
                        <p class="receipt-payment-method">
                            Paid via {{ ucfirst($booking->payment_method) }}
                            @if(!empty($booking->discount_coupon))
                                · Coupon <strong>{{ strtoupper($booking->discount_coupon) }}</strong> applied
                            @endif
                        </p>
                    @endif
                </div>

                <button type="button" class="btn-secondary confirmation-print" onclick="window.print()" aria-label="Print or save this confirmation">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="6 9 6 2 18 2 18 9"/>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                        <rect x="6" y="14" width="12" height="8"/>
                    </svg>
                    <span>{{ Helper::label($data,'bc_print','Print or save as PDF') }}</span>
                </button>

                <a href="{{ url('/') }}" class="btn-confirm confirmation-cta">
                    <span class="btn-confirm__label">{{ Helper::label($data,'bc_back_home','Back to home') }}</span>
                </a>
            </aside>
        </div>
    </div>
</section>

{{-- Back-navigation trap (3 layers):
       1. `history.pushState` adds a duplicate entry on top so the very next
          Back hit is a popstate we control rather than dropping back to the
          stale checkout form.
       2. `popstate` redirects to home when triggered from the same tab.
       3. `pageshow` with event.persisted catches bfcache restores (when
          the browser serves this page from its back-forward cache without
          running scripts) — Safari, Chrome and Firefox all hit this path on
          back navigation when Cache-Control allows caching. We detect the
          restore and redirect home immediately. --}}
<meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
<meta http-equiv="Pragma" content="no-cache">
<meta http-equiv="Expires" content="0">
<script>
(function () {
    var safeUrl = @json(url('/'));

    // Drop a sessionStorage breadcrumb so the *checkout* page can detect
    // back-navigation from a confirmed booking and bounce the guest to
    // home itself. (We can't intercept back navigation FROM this page —
    // JS runs on the arrived page, not the leaving one. Cleared the
    // moment the user reaches home so a future fresh booking still works.)
    try { sessionStorage.setItem('booking_completed', String(Date.now())); } catch (_) {}

    if (window.history.replaceState) {
        window.history.replaceState({ booked: true }, '', window.location.href);
    }
    if (window.history.pushState) {
        window.history.pushState({ booked: true }, '', window.location.href);
    }

    window.addEventListener('popstate', function () {
        window.location.replace(safeUrl);
    });

    // bfcache restore: fires on every page-show, with `persisted=true` only
    // when the browser is restoring this page from its back-forward cache.
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.replace(safeUrl);
        }
    });

    // QA D-007: removed the unconditional beforeunload "Leave site?" prompt —
    // it fired even when the guest clicked this page's own navigation. The
    // popstate/pageshow guards above already redirect back-navigation / bfcache
    // restores to the home page, so the prompt was friction with no protection.
})();
</script>
@endif
@endsection
