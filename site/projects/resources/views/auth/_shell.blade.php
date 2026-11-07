{{--
    Shared shell for all auth pages (login, forgot password, password reset).
    Renders the dark navy marketing panel on the left and yields a `formpane`
    section on the right for the page-specific form. Used via:

        @extends('auth._shell', ['title' => 'Sign in'])
        @section('formpane') ... @endsection
--}}
@php
    $authBrand = $setting_data['website'] ?? config('app.name', 'Admin');
    $authFavicon = $setting_data['favicon'] ?? 'front/images/favicon.png';
    $supportUrl = trim((string) ($setting_data['support_url'] ?? ''));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $authBrand }} · {{ $authBrand }}</title>
    <link rel="icon" href="{{ asset($authFavicon) }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

    <style>
        :root {
            --ca-ink:        #0f1c33;
            --ca-ink-soft:   #2a3855;
            --ca-ink-mute:   #5a6478;
            --ca-ink-quiet:  #94a0b3;
            --ca-line:       #e3e7ee;
            --ca-line-strong:#cdd4e0;
            --ca-canvas:     #ffffff;
            --ca-surface-soft:#f5f5f7;
            --ca-chrome:     #15253f;
            --ca-accent:     #1e3a5f;
            --ca-accent-deep:#15253f;
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0; padding: 0;
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--ca-ink);
            background: var(--ca-canvas);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
        }
        a { color: var(--ca-accent); text-decoration: none; transition: color 140ms ease; }
        a:hover { color: var(--ca-accent-deep); }

        .login-shell { display: grid; grid-template-columns: 1fr 1fr; min-height: 100vh; }
        @media (max-width: 960px) {
            .login-shell { grid-template-columns: 1fr; }
            .login-marketing { display: none; }
        }

        .login-marketing {
            background: var(--ca-chrome);
            color: #ffffff;
            padding: 56px 64px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }
        .login-marketing::before {
            content: ""; position: absolute;
            inset: -40% -20% auto auto; width: 600px; height: 600px;
            background: radial-gradient(closest-side, rgba(255,255,255,0.06), transparent 70%);
            pointer-events: none;
        }
        .login-marketing::after {
            content: ""; position: absolute;
            inset: auto auto -30% -20%; width: 540px; height: 540px;
            background: radial-gradient(closest-side, rgba(255,255,255,0.04), transparent 70%);
            pointer-events: none;
        }

        .login-brand { display: flex; align-items: center; gap: 11px; margin-bottom: auto; position: relative; z-index: 1; }
        .login-brand-mark { width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; color: #ffffff; }
        .login-brand-mark svg { width: 100%; height: 100%; }
        .login-brand-name { font-size: 12.5px; font-weight: 700; letter-spacing: .16em; text-transform: uppercase; color: #ffffff; }

        .login-headline { position: relative; z-index: 1; margin-top: 80px; }
        .login-headline h1 { font-size: 38px; font-weight: 600; line-height: 1.15; letter-spacing: -.02em; margin: 0 0 18px; color: #ffffff; max-width: 460px; }
        .login-headline p { font-size: 15px; line-height: 1.6; color: rgba(255,255,255,0.72); margin: 0; max-width: 420px; }

        .login-features { position: relative; z-index: 1; display: flex; flex-direction: column; gap: 14px; margin-top: 36px; max-width: 440px; }
        .login-feature { display: flex; align-items: flex-start; gap: 12px; color: rgba(255,255,255,0.86); font-size: 13.5px; }
        .login-feature::before { content: ""; display: inline-block; width: 5px; height: 5px; border-radius: 50%; background: #ffffff; opacity: .6; margin-top: 7px; flex-shrink: 0; }

        .login-byline { position: relative; z-index: 1; margin-top: auto; padding-top: 60px; border-top: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; gap: 10px; }
        .login-byline-label { font-size: 10.5px; font-weight: 600; letter-spacing: .14em; text-transform: uppercase; color: rgba(255,255,255,0.55); }
        .login-byline-row { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
        .login-byline-name { font-size: 17px; font-weight: 600; color: #ffffff; letter-spacing: -.005em; }
        .login-byline-tagline { font-size: 13px; color: rgba(255,255,255,0.65); }
        .login-byline-cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
            padding: 9px 16px;
            background: rgba(255, 255, 255, 0.06);
            border: 1px solid rgba(255, 255, 255, 0.16);
            border-radius: 999px;
            color: rgba(255, 255, 255, 0.92) !important;
            font-size: 12.5px;
            font-weight: 500;
            letter-spacing: .01em;
            align-self: flex-start;
            transition: background 160ms ease, border-color 160ms ease, transform 160ms ease;
            text-decoration: none !important;
        }
        .login-byline-cta:hover {
            background: rgba(255, 255, 255, 0.14);
            border-color: rgba(255, 255, 255, 0.30);
            color: #ffffff !important;
            transform: translateX(2px);
        }
        .login-byline-cta span { transition: transform 160ms ease; }
        .login-byline-cta:hover span { transform: translateX(2px); }

        .login-formpane { background: var(--ca-canvas); padding: 56px 64px; display: flex; flex-direction: column; justify-content: center; position: relative; }
        @media (max-width: 600px) { .login-formpane { padding: 36px 24px; } }
        .login-formcard { width: 100%; max-width: 380px; margin: 0 auto; }
        .login-formcard h2 { font-size: 26px; font-weight: 600; letter-spacing: -.018em; margin: 0 0 8px; color: var(--ca-ink); }
        .login-formcard .login-eyebrow { font-size: 11px; font-weight: 700; letter-spacing: .14em; text-transform: uppercase; color: var(--ca-ink-quiet); margin-bottom: 6px; }
        .login-formcard .login-sub { font-size: 13.5px; color: var(--ca-ink-mute); line-height: 1.55; margin: 0 0 32px; }

        .login-form .login-field { margin-bottom: 16px; }
        .login-form label { display: block; font-size: 12px; font-weight: 600; color: var(--ca-ink); margin-bottom: 6px; }
        .login-form input[type="email"],
        .login-form input[type="password"],
        .login-form input[type="text"] {
            width: 100%; height: 42px; padding: 0 14px;
            background: var(--ca-canvas); border: 1px solid var(--ca-line); border-radius: 8px;
            font-family: inherit; font-size: 14px; color: var(--ca-ink);
            transition: border-color 140ms ease, box-shadow 200ms ease;
        }
        .login-form input::placeholder { color: var(--ca-ink-quiet); }
        .login-form input:hover { border-color: var(--ca-line-strong); }
        .login-form input:focus { outline: none; border-color: var(--ca-accent); box-shadow: 0 0 0 3px rgba(30,58,95,.14); }

        .login-form .login-row { display: flex; align-items: center; justify-content: space-between; margin: 18px 0 24px; font-size: 13px; }
        .login-form .login-remember { display: inline-flex; align-items: center; gap: 8px; color: var(--ca-ink-soft); cursor: pointer; user-select: none; }
        .login-form .login-remember input { width: 16px; height: 16px; accent-color: var(--ca-accent); margin: 0; }

        .login-form .login-submit {
            width: 100%; height: 44px;
            background: var(--ca-accent); border: 1px solid var(--ca-accent);
            border-radius: 8px; color: #ffffff;
            font-family: inherit; font-size: 14px; font-weight: 600; letter-spacing: .01em;
            cursor: pointer;
            transition: background 140ms ease, transform 140ms ease, box-shadow 200ms ease;
            box-shadow: 0 1px 2px rgba(15,28,51,.14), 0 4px 12px -4px rgba(15,28,51,.25);
        }
        .login-form .login-submit:hover { background: var(--ca-accent-deep); transform: translateY(-1px); box-shadow: 0 1px 2px rgba(15,28,51,.14), 0 8px 18px -6px rgba(15,28,51,.35); }
        .login-form .login-submit:active { transform: translateY(0) scale(.99); }

        .login-error { background: rgba(190,24,24,.06); border: 1px solid rgba(190,24,24,.22); color: #be1818; border-radius: 8px; padding: 9px 12px; font-size: 13px; margin-bottom: 16px; }
        .login-success { background: rgba(4,120,87,.06); border: 1px solid rgba(4,120,87,.22); color: #047857; border-radius: 8px; padding: 9px 12px; font-size: 13px; margin-bottom: 16px; }

        .login-foot { margin-top: 28px; padding-top: 22px; border-top: 1px solid var(--ca-line); font-size: 12px; color: var(--ca-ink-mute); display: flex; flex-direction: column; gap: 4px; }
        .login-foot strong { color: var(--ca-ink-soft); font-weight: 600; }

        .login-back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--ca-ink-mute);
            margin-bottom: 24px;
        }
        .login-back-link:hover { color: var(--ca-accent); }
        .login-back-link::before { content: "←"; font-size: 14px; }
    </style>
    @yield('extra_css')
</head>
<body>

<div class="login-shell">

    <aside class="login-marketing">
        <div class="login-brand">
            <span class="login-brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <rect x="2"  y="13" width="9"   height="9"   rx="2"  fill="currentColor"/>
                    <rect x="9.5" y="7.5" width="7" height="7" rx="1.6" fill="currentColor" opacity=".75"/>
                    <rect x="15.5" y="3.5" width="5" height="5" rx="1.2" fill="currentColor" opacity=".55"/>
                </svg>
            </span>
            <span class="login-brand-name">{{ $authBrand }}</span>
        </div>

        <div class="login-headline">
            <h1>The vacation-rental control room.</h1>
            <p>Manage listings, calendars, bookings, payments, and guest comms — from a single, considered workspace.</p>
        </div>

        <div class="login-features">
            <div class="login-feature">Property listings, photos, amenities, pricing rules</div>
            <div class="login-feature">iCal sync with Airbnb, VRBO, Booking.com</div>
            <div class="login-feature">Booking lifecycle, payments, rental agreements</div>
            <div class="login-feature">Stay-cycle email automation</div>
        </div>

        <div class="login-byline">
            <span class="login-byline-label">PMS &amp; website management</span>
            <div class="login-byline-row">
                <span class="login-byline-name">{{ $authBrand }}</span>
                <span class="login-byline-tagline">— vacation-rental operations workspace</span>
            </div>
            @if($supportUrl !== '')
                <a href="{{ $supportUrl }}" target="_blank" rel="noopener" class="login-byline-cta">
                    Contact support
                    <span aria-hidden="true">→</span>
                </a>
            @endif
        </div>
    </aside>

    <main class="login-formpane">
        <div class="login-formcard">
            @yield('formpane')
        </div>
    </main>

</div>

</body>
</html>
