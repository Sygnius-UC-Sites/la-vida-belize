@extends('front.layouts.brad')
@section("title", $data->meta_title ?? 'Sign rental agreement')
@section("keywords", $data->meta_keywords ?? '')
@section("description", $data->meta_description ?? '')
@section('body-class', 'page booking-page')

@section('head')
    @include('front.partials.booking-funnel-head')
    <link rel="stylesheet" type="text/css" href="{{ asset('drag-drop-image-uploader/src/image-uploader.css') }}">
    <style>
        /* Phase 6 — signing-page-specific extras layered on top of the
           shared confirmation-page chrome. The chrome supplies hero, cards,
           sidebar, .enquiry-dates pill, .btn-confirm, etc. — we only style
           pieces that are unique to this page (the agreement preview pane,
           signature canvas, ID upload). */
        .agreement-preview {
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 22px 26px;
            max-height: 520px;
            overflow-y: auto;
            background: #fafafb;
            color: #1f2937;
            font-size: 15px;
            line-height: 1.65;
        }
        .agreement-preview h2,
        .agreement-preview h3 { color: #0b2820; margin-top: 1.2em; }
        .agreement-preview p { margin: 8px 0; }
        .agreement-preview .house-rules-block {
            margin-top: 24px;
            padding: 14px 18px;
            background: #faf3e6;
            border-left: 4px solid #d9a24a;
            border-radius: 0 8px 8px 0;
        }
        .agreement-preview .house-rules-block h3 { margin-top: 0; color: #7a5818; }

        .consent-block {
            display: block;
            margin: 18px 0 22px;
            padding: 14px 18px;
            background: #faf3e6;
            border: 1px solid #e8c896;
            border-radius: 10px;
            color: #1f2937;
            font-size: 14px;
            line-height: 1.55;
            cursor: pointer;
        }
        .consent-block input[type="checkbox"] {
            margin-right: 8px;
            vertical-align: top;
            transform: translateY(2px);
        }

        .sign-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 720px) { .sign-grid { grid-template-columns: 1fr; } }
        .sign-grid h4 {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6b7280;
            margin: 0 0 8px 0;
        }
        #sig-canvas {
            width: 100%;
            height: 160px;
            border: 1.5px dashed #d6d6dc;
            border-radius: 10px;
            background: #fff;
            display: block;
            cursor: crosshair;
        }
        #sig-canvas:hover { border-color: #d9a24a; }
        .sign-buttons {
            margin-top: 16px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn-clear {
            background: #fff;
            color: #6b7280;
            border: 1.5px solid #d6d6dc;
            padding: 12px 18px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-clear:hover { border-color: #b42318; color: #b42318; }
        .sig-text-area textarea {
            display: none; /* hidden — only carries the data-URL to backend */
        }

        /* Sidebar property image + key stats */
        .property-summary-card .pro-img img {
            width: 100%; height: 180px;
            object-fit: cover;
            border-radius: 10px;
        }
        .property-summary-card .stats {
            display: flex; flex-wrap: wrap;
            gap: 12px;
            color: #6b7280;
            font-size: 13px;
            margin-top: 8px;
        }
        .property-summary-card .stats span { white-space: nowrap; }
    </style>
@endsection

@section('content')

    @php
        $currency       = ModelHelper::getDataFromSetting('payment_currency') ?: '$';
        $reference      = '#' . str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT);
        $ciCarbon       = $booking->checkin  ? \Carbon\Carbon::parse($booking->checkin)  : null;
        $coCarbon       = $booking->checkout ? \Carbon\Carbon::parse($booking->checkout) : null;
        $totalGuests    = (int) ($booking->total_guests ?? 0);
        $adults         = (int) ($booking->adults ?? 0);
        $children       = (int) ($booking->child ?? 0);
        $pets           = (int) ($booking->total_pets ?? 0);

        $templateHtml = $property->rental_agreement_template_html
            ?: \ModelHelper::getDataFromSetting('rental_agreement_template_html');
        $houseRulesHtml = $property->rental_agreement_house_rules_html
            ?: \ModelHelper::getDataFromSetting('rental_agreement_house_rules_html');
        $propertyImage = $property->feature_image ?? $property->banner_image ?? $property->image_url ?? null;
        if ($propertyImage && \Illuminate\Support\Str::startsWith($propertyImage, '//')) {
            $propertyImage = 'https:' . $propertyImage;
        }
        $propertyImageSrc = $propertyImage
            ? (\Illuminate\Support\Str::startsWith($propertyImage, ['http://', 'https://']) ? $propertyImage : asset($propertyImage))
            : null;
        $propertySleeps = $property->sleeps ?? $property->max_people ?? $property->accommodates ?? null;
        $propertyBedrooms = $property->bedroom ?? $property->bedrooms ?? null;
        $propertyBathrooms = $property->bathroom ?? $property->bathrooms ?? null;
        $propertySubheading = $property->heading ?? $property->property_type ?? null;

        // Live preview context — same merge tokens that will be substituted
        // into the signed PDF, so the guest sees their actual values inline.
        $previewCtx = new \App\Services\Booking\RentalAgreementContext(
            guest_name:           (string)($booking->name ?? ''),
            guest_email:          (string)($booking->email ?? ''),
            guest_phone:          $booking->mobile,
            property_name:        (string)($property->name ?? ''),
            property_address:     $property->address ?? null,
            owner_name:           \ModelHelper::getDataFromSetting('owner_name') ?: null,
            owner_company:        \ModelHelper::getDataFromSetting('website') ?: null,
            booking_id:           (int)$booking->id,
            check_in:             (string)$booking->checkin,
            check_out:            (string)$booking->checkout,
            nights:               (int)($booking->total_night ?? 1),
            adults:               $adults,
            children:             $children,
            pets:                 $pets,
            total_amount:         (float)($booking->total_amount ?? 0),
            damage_deposit:       0.0,
            currency:             (string)$currency,
            template_html:        (string)($templateHtml ?? ''),
            house_rules_html:     (string)($houseRulesHtml ?? ''),
            signature_data_url:   '',
            signed_at_iso:        '',
            signed_ip:            '',
            signed_user_agent:    '',
            signed_consent_text:  '',
        );

        // Verbatim consent text — captured into rental_agreement_consent_text
        // at signing time + printed in the PDF audit block. Update with care:
        // legal copy. Hash carries an HMAC tying the displayed text to this
        // request, so swapping the text client-side is detectable server-side.
        $consentText = "I have read and understand the Rental Agreement and House Rules above. I agree to be bound by these terms. I understand that my electronic signature is the legal equivalent of a handwritten signature.";
    @endphp

    <section class="confirmation-page">
        <div class="container">

            {{-- ─── Hero ─── --}}
            <div class="confirmation-hero">
                <div class="status-icon status-icon--pending" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 20h9"/>
                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                    </svg>
                </div>
                <h1>Sign your rental agreement</h1>
                <p class="confirmation-hero__lead">
                    Please review the agreement below and add your signature.
                    A signed PDF — including your IP, timestamp, and audit trail — will be emailed
                    to <strong>{{ $booking->email }}</strong> the moment you submit.
                </p>
                <p class="confirmation-hero__id">Booking {{ $reference }}</p>
            </div>

            <div class="confirmation-layout">

                {{-- ============== LEFT — agreement + signature ============== --}}
                <div class="confirmation-main">

                    {!! Form::open(["route"=>"rental-aggrement-data-save","files"=>true,"onsubmit"=>"return checkSignature()"]) !!}
                    <input type="hidden" name="booking_id" value="{{ $booking['id'] }}">

                    {{-- Agreement body + house rules --}}
                    <div class="confirmation-card">
                        <h2 class="confirmation-card__title">Rental Agreement</h2>
                        <div class="agreement-preview">
                            @if(!empty($templateHtml))
                                {!! $previewCtx->applyMergeFields($templateHtml) !!}
                            @else
                                <p style="color:#b42318;"><em>No rental agreement template configured. Please contact the property owner before signing.</em></p>
                            @endif

                            @if(!empty($houseRulesHtml))
                                <div class="house-rules-block">
                                    <h3>House Rules</h3>
                                    {!! $previewCtx->applyMergeFields($houseRulesHtml) !!}
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Consent --}}
                    <div class="confirmation-card">
                        <h2 class="confirmation-card__title">Your consent</h2>
                        <label class="consent-block">
                            <input type="checkbox" name="consent" value="1" required>
                            <span>{{ $consentText }}</span>
                        </label>
                        <input type="hidden" name="consent_text" value="{{ $consentText }}">
                        <input type="hidden" name="consent_hash" value="{{ hash_hmac('sha256', $consentText, config('app.key')) }}">
                    </div>

                    {{-- Signature + ID upload --}}
                    <div class="confirmation-card">
                        <h2 class="confirmation-card__title">Sign &amp; verify identity</h2>
                        <div class="sign-grid">
                            <div>
                                <h4>E-signature</h4>
                                <canvas id="sig-canvas"></canvas>
                                <p class="confirmation-meta" style="margin-top:6px;">Use your finger on touch devices, or your trackpad / mouse on desktop.</p>
                            </div>
                            <div>
                                <h4>Photo of your driving licence (or photo ID)</h4>
                                <div class="input-images-2"></div>
                                <p class="confirmation-meta" style="margin-top:6px;">JPG, PNG or WebP. We use this only to verify the booking; never shared.</p>
                            </div>
                        </div>

                        <div class="sign-buttons">
                            @if($booking->operation == "check-booking")
                                <button class="btn-confirm" id="sig-submitBtn" type="submit">
                                    <span class="btn-confirm__label">Submit signed agreement</span>
                                </button>
                            @else
                                <button class="btn-confirm" id="sig-submitBtn" type="submit">
                                    <span class="btn-confirm__label">Submit &amp; pay now</span>
                                </button>
                            @endif
                            <button type="button" class="btn-clear" id="sig-clearBtn">Clear signature</button>
                        </div>

                        {{-- Hidden — JS writes the canvas data-URL into this textarea on submit. --}}
                        <div class="sig-text-area">
                            <textarea id="sig-dataUrl" name="signature" rows="5">Data URL for your signature will go here!</textarea>
                        </div>
                    </div>

                    {!! Form::close() !!}
                </div>

                {{-- ============== RIGHT — sticky trip summary sidebar ============== --}}
                <aside class="confirmation-sidebar">
                    <div class="confirmation-card receipt-card property-summary-card">
                        <h2 class="confirmation-card__title">Your stay</h2>

                        @if($propertyImageSrc)
                            <div class="pro-img" style="margin-bottom:12px;">
                                <img src="{{ $propertyImageSrc }}"
                                     alt="{{ $property->name }}" loading="lazy">
                            </div>
                        @endif

                        <div style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:4px;">{{ $property->name }}</div>
                        @if(!empty($propertySubheading))
                            <p class="confirmation-meta" style="margin-bottom:8px;">{{ $propertySubheading }}</p>
                        @endif
                        <div class="stats">
                            @if($propertySleeps)<span>{{ $propertySleeps }} sleeps</span>@endif
                            @if($propertyBedrooms)<span>{{ $propertyBedrooms }} bedrooms</span>@endif
                            @if($propertyBathrooms)<span>{{ $propertyBathrooms }} bathrooms</span>@endif
                        </div>

                        @if($ciCarbon && $coCarbon)
                            <div class="enquiry-dates" style="margin-top:18px;">
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
                            <p class="enquiry-dates__nights">
                                @php
                                    // Build the inline guest summary in PHP so we don't trip Blade's
                                    // back-to-back @endif@if parser bug.
                                    $parts = [];
                                    if ($adults > 0)   $parts[] = $adults . ' adult' . ($adults === 1 ? '' : 's');
                                    if ($children > 0) $parts[] = $children . ' ' . \Illuminate\Support\Str::plural('child', $children);
                                    if ($pets > 0)     $parts[] = $pets . ' pet' . ($pets === 1 ? '' : 's');
                                @endphp
                                {{ $booking->total_night ?? $ciCarbon->diffInDays($coCarbon) }} nights
                                @if($totalGuests > 0)
                                    · {{ $totalGuests }} guest{{ $totalGuests === 1 ? '' : 's' }}
                                    @if(!empty($parts))
                                        ({{ implode(', ', $parts) }})
                                    @endif
                                @endif
                            </p>
                        @endif

                        {{-- Compact total — full price breakdown lives on booking-confirmed,
                             this page is about signing not pricing. --}}
                        @if(($booking->total_amount ?? 0) > 0)
                            <ul class="spec-list">
                                <li class="spec-list__row">
                                    <span class="spec-list__label">Booking</span>
                                    <span class="spec-list__value spec-list__value--mono">{{ $reference }}</span>
                                </li>
                                <li class="spec-list__row">
                                    <span class="spec-list__label">Total</span>
                                    <span class="spec-list__value">{{ $currency }}{{ number_format((float) $booking->total_amount, 2) }}</span>
                                </li>
                                @if(!empty($booking->payment_status))
                                    <li class="spec-list__row">
                                        <span class="spec-list__label">Payment</span>
                                        <span class="spec-list__value">{{ ucfirst(str_replace('-', ' ', $booking->payment_status)) }}</span>
                                    </li>
                                @endif
                            </ul>
                        @endif
                    </div>
                </aside>

            </div>
        </div>
    </section>

@endsection

@section('scripts')
    @include('front.partials.payment-gateway-jquery')
    <script src="{{ asset('drag-drop-image-uploader/src/image-uploader.js') }}"></script>
    <script>
        $('.input-images-2').imageUploader({
            label:'Drag &amp; drop or click to browse',
            maxFiles:1,
            imagesInputName: "image",
            extensions:['.jpg', '.jpeg', '.png', '.gif', '.svg','.webp'],
            mimes:['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml' ,'image/webp']
        });

        /* Signature canvas — verbatim from previous page; behaviour unchanged.
           Captures mouse + touch events, writes data-URL into the hidden textarea
           on submit. checkSignature() blocks submit if the canvas is blank. */
        (function() {
            window.requestAnimFrame = (function(callback) {
                return window.requestAnimationFrame ||
                    window.webkitRequestAnimationFrame ||
                    window.mozRequestAnimationFrame ||
                    window.oRequestAnimationFrame ||
                    window.msRequestAnimaitonFrame ||
                    function(callback) { window.setTimeout(callback, 1000 / 60); };
            })();

            var canvas = document.getElementById("sig-canvas");
            // Resize the canvas to match its CSS box so coordinates match what the
            // user sees (without this fix the canvas internal width is its default
            // 300, but CSS stretches it to 100% — strokes get squished horizontally).
            function resizeCanvas() {
                var rect = canvas.getBoundingClientRect();
                canvas.width  = rect.width;
                canvas.height = rect.height;
                ctx.strokeStyle = "#0b2820";
                ctx.lineWidth   = 2.5;
                ctx.lineCap     = 'round';
                ctx.lineJoin    = 'round';
            }
            var ctx = canvas.getContext("2d");
            resizeCanvas();
            window.addEventListener('resize', resizeCanvas);

            var drawing = false;
            var mousePos = { x: 0, y: 0 };
            var lastPos = mousePos;

            canvas.addEventListener("mousedown", function(e) {
                drawing = true;
                lastPos = getMousePos(canvas, e);
            }, false);
            canvas.addEventListener("mouseup",   function() { drawing = false; }, false);
            canvas.addEventListener("mousemove", function(e) { mousePos = getMousePos(canvas, e); }, false);

            canvas.addEventListener("touchmove", function(e) {
                var touch = e.touches[0];
                canvas.dispatchEvent(new MouseEvent("mousemove", { clientX: touch.clientX, clientY: touch.clientY }));
            }, false);
            canvas.addEventListener("touchstart", function(e) {
                mousePos = getTouchPos(canvas, e);
                var touch = e.touches[0];
                canvas.dispatchEvent(new MouseEvent("mousedown", { clientX: touch.clientX, clientY: touch.clientY }));
            }, false);
            canvas.addEventListener("touchend", function() {
                canvas.dispatchEvent(new MouseEvent("mouseup", {}));
            }, false);

            function getMousePos(c, e) {
                var rect = c.getBoundingClientRect();
                return { x: e.clientX - rect.left, y: e.clientY - rect.top };
            }
            function getTouchPos(c, e) {
                var rect = c.getBoundingClientRect();
                return { x: e.touches[0].clientX - rect.left, y: e.touches[0].clientY - rect.top };
            }
            function renderCanvas() {
                if (drawing) {
                    ctx.beginPath();
                    ctx.moveTo(lastPos.x, lastPos.y);
                    ctx.lineTo(mousePos.x, mousePos.y);
                    ctx.stroke();
                    lastPos = mousePos;
                }
            }
            // Prevent page scroll while drawing on touch.
            document.body.addEventListener("touchstart", function(e) { if (e.target == canvas) e.preventDefault(); }, false);
            document.body.addEventListener("touchend",   function(e) { if (e.target == canvas) e.preventDefault(); }, false);
            document.body.addEventListener("touchmove",  function(e) { if (e.target == canvas) e.preventDefault(); }, false);

            (function drawLoop() {
                requestAnimFrame(drawLoop);
                renderCanvas();
            })();

            function clearCanvas() { canvas.width = canvas.width; resizeCanvas(); }

            var sigText  = document.getElementById("sig-dataUrl");
            var clearBtn = document.getElementById("sig-clearBtn");
            var submitBtn = document.getElementById("sig-submitBtn");

            clearBtn.addEventListener("click", function() {
                clearCanvas();
                sigText.innerHTML = "Data URL for your signature will go here!";
            }, false);
            submitBtn.addEventListener("click", function() {
                sigText.innerHTML = canvas.toDataURL();
            }, false);
        })();

        function checkSignature() {
            var canvas = document.getElementById('sig-canvas');
            if (isCanvasEmpty(canvas)) {
                if (typeof toastr !== 'undefined') toastr.error('Please add your signature before submitting.');
                else alert('Please add your signature before submitting.');
                return false;
            }
            return true;
        }
        function isCanvasEmpty(canvas) {
            const blank = document.createElement('canvas');
            blank.width  = canvas.width;
            blank.height = canvas.height;
            return canvas.toDataURL() === blank.toDataURL();
        }
    </script>
@endsection
