@extends('admin.layouts')

@section('title', 'Payment Gateway Settings')

@section('content_header')
    <h1>Payment Gateways</h1>
    <p class="text-muted">
        Pick <strong>one</strong> card processor (Stripe, AuthorizeNet, or Square) for credit / debit cards.
        You can also enable <strong>PayPal</strong> as a second option alongside it.
        Sandbox is the safe default — switch a gateway to production after verifying its credentials.
    </p>
@stop

@section('content')
    @if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    {!! Form::open(['route' => 'admin.payment-gateways.update', 'method' => 'PUT']) !!}

    {{-- ============ Card processor choice ============ --}}
@php
    // Feature flags: only the gateways sold to this customer are offered
    // (dev panel → "Payment gateways"). Developers see all.
    $gwShow = fn ($g) => \App\Helper\FeatureFlags::isDeveloper()
        || \App\Helper\FeatureFlags::gatewayEnabled($g);
@endphp
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title m-0">Card processor — pick one</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small">Only one card processor runs the credit/debit-card form at checkout. Select <em>None</em> if you only want PayPal (or no payments at all).</p>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="card_gateway" value="" id="card-none" {{ $cardGateway === '' ? 'checked' : '' }}>
                <label class="form-check-label" for="card-none"><strong>None</strong> — no card payments</label>
            </div>
            @if($gwShow('stripe'))
            <div class="form-check">
                <input class="form-check-input" type="radio" name="card_gateway" value="stripe" id="card-stripe" {{ $cardGateway === 'stripe' ? 'checked' : '' }}>
                <label class="form-check-label" for="card-stripe"><strong>Stripe</strong> <span class="text-muted small">— modern Elements card form, low fees, recommended</span></label>
            </div>
            @endif
            @if($gwShow('authorize'))
            <div class="form-check">
                <input class="form-check-input" type="radio" name="card_gateway" value="authorize" id="card-authorize" {{ $cardGateway === 'authorize' ? 'checked' : '' }}>
                <label class="form-check-label" for="card-authorize"><strong>AuthorizeNet</strong> <span class="text-muted small">— hosted card form, redirect-based</span></label>
            </div>
            @endif
            @if($gwShow('square'))
            <div class="form-check">
                <input class="form-check-input" type="radio" name="card_gateway" value="square" id="card-square" {{ $cardGateway === 'square' ? 'checked' : '' }}>
                <label class="form-check-label" for="card-square"><strong>Square</strong> <span class="text-muted small">— good for US owners with an existing Square POS</span></label>
            </div>
            @endif
        </div>
    </div>

    {{-- ============ Credentials per card processor ============ --}}
    {{-- Stripe credentials --}}
    @if($gwShow('stripe'))
    <div class="card mb-3 gw-credentials" data-card-gateway="stripe" {!! $cardGateway === 'stripe' ? '' : 'style=display:none' !!}>
        <div class="card-header">
            <h5 class="card-title m-0">Stripe credentials</h5>
        </div>
        <div class="card-body">
            <div class="form-group">
                {!! Form::label('stripe_publish_key', 'Publishable key (pk_*)') !!}
                {!! Form::text('stripe_publish_key', $values['stripe_publish_key'], ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('stripe_secret_key', 'Secret key (sk_*)') !!}
                {!! Form::text('stripe_secret_key', $values['stripe_secret_key'], ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('stripe_webhook_secret', 'Webhook signing secret (whsec_*)') !!}
                {!! Form::text('stripe_webhook_secret', $values['stripe_webhook_secret'], ['class' => 'form-control']) !!}
                <small class="text-muted">Required before go-live so Stripe can confirm payment, refund, and dispute events.</small>
            </div>
        </div>
    </div>

    {{-- AuthorizeNet credentials --}}
    @endif
    @if($gwShow('authorize'))
    <div class="card mb-3 gw-credentials" data-card-gateway="authorize" {!! $cardGateway === 'authorize' ? '' : 'style=display:none' !!}>
        <div class="card-header">
            <h5 class="card-title m-0">AuthorizeNet credentials</h5>
        </div>
        <div class="card-body">
            <div class="form-group">
                {!! Form::label('AUTHORIZED_MERCHANT_LOGIN_ID', 'Merchant login ID') !!}
                {!! Form::text('AUTHORIZED_MERCHANT_LOGIN_ID', $values['AUTHORIZED_MERCHANT_LOGIN_ID'], ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('AUTHORIZED_MERCHANT_TRANSACTION_KEY', 'Transaction key') !!}
                {!! Form::text('AUTHORIZED_MERCHANT_TRANSACTION_KEY', $values['AUTHORIZED_MERCHANT_TRANSACTION_KEY'], ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY', 'Public client key (Accept.js)') !!}
                {!! Form::text('AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY', $values['AUTHORIZED_MERCHANT_PUBLIC_CLIENT_KEY'], ['class' => 'form-control']) !!}
                <small class="text-muted">Generate this in the Authorize.Net merchant dashboard. Required for client-side card tokenization.</small>
            </div>
            <div class="form-group">
                {!! Form::label('authorize_environment', 'Environment') !!}
                {!! Form::select('authorize_environment', ['sandbox' => 'Sandbox', 'production' => 'Production'], $values['authorize_environment'] ?: 'sandbox', ['class' => 'form-control', 'style' => 'max-width:240px;']) !!}
            </div>
        </div>
    </div>

    {{-- Square credentials --}}
    @endif
    @if($gwShow('square'))
    <div class="card mb-3 gw-credentials" data-card-gateway="square" {!! $cardGateway === 'square' ? '' : 'style=display:none' !!}>
        <div class="card-header">
            <h5 class="card-title m-0">Square credentials</h5>
        </div>
        <div class="card-body">
            <div class="form-group">
                {!! Form::label('square_ACCESS_TOKEN', 'Access token') !!}
                {!! Form::text('square_ACCESS_TOKEN', $values['square_ACCESS_TOKEN'], ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('square_Application_id', 'Application ID') !!}
                {!! Form::text('square_Application_id', $values['square_Application_id'], ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('square_location_id', 'Location ID') !!}
                {!! Form::text('square_location_id', $values['square_location_id'], ['class' => 'form-control']) !!}
            </div>
            <div class="form-group">
                {!! Form::label('square_environment', 'Environment') !!}
                {!! Form::select('square_environment', ['sandbox' => 'Sandbox', 'production' => 'Production'], $values['square_environment'] ?: 'sandbox', ['class' => 'form-control', 'style' => 'max-width:240px;']) !!}
            </div>
        </div>
    </div>

    @endif
    {{-- ============ PayPal — independent option ============ --}}
    @if($gwShow('paypal'))
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title m-0">
                <input type="checkbox" name="paypal_enabled" value="1" id="paypal-enabled" {{ $paypalEnabled ? 'checked' : '' }}>
                <label for="paypal-enabled" class="m-0">PayPal — additional option</label>
            </h5>
        </div>
        <div class="card-body">
            <p class="text-muted small">Optional: enable PayPal alongside your card processor. Guests will see two payment options at checkout (Card and PayPal) and can choose either.</p>
            <div class="form-group">
                {!! Form::label('paypal_access_token', 'Client ID') !!}
                {!! Form::text('paypal_access_token', $values['paypal_access_token'], ['class' => 'form-control']) !!}
                <small class="text-muted">Generate from your PayPal developer dashboard.</small>
            </div>
        </div>
    </div>

    @endif

    <div class="mt-3 mb-5">
        <button type="submit" class="btn btn-primary">Save</button>
    </div>

    {!! Form::close() !!}

    <script>
    // Show/hide credential cards based on which card processor is active.
    (function () {
        var radios = document.querySelectorAll('input[name="card_gateway"]');
        var cards  = document.querySelectorAll('.gw-credentials');
        function sync() {
            var checked = document.querySelector('input[name="card_gateway"]:checked');
            var value = checked ? checked.value : '';
            cards.forEach(function (c) {
                c.style.display = (c.dataset.cardGateway === value) ? '' : 'none';
            });
        }
        radios.forEach(function (r) { r.addEventListener('change', sync); });
        sync();
    })();
    </script>
@endsection
