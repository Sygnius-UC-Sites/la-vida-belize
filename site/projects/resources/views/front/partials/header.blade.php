@php
    $isHome = request()->is('/');
    $isStayDetail = request()->is('stay/*') || request()->is('condos/*') || request()->is('houses/*');
    $hasLocalBooking = $isHome || request()->is('properties') || request()->is('groups');
    $logoHref = $isHome ? '#home' : url('/');
    $bookHref = $isStayDetail ? '#booking-widget' : ($hasLocalBooking ? '#booking' : url('/') . '#booking');
    $navCurrent = function (string $path): bool {
        if ($path === '/') {
            return request()->is('/');
        }
        return request()->is(trim($path, '/')) || request()->is(trim($path, '/') . '/*');
    };
    $arrow = '<svg class="arrow" viewBox="0 0 16 16" aria-hidden="true"><path d="M3 8h9M8 3.5 12.5 8 8 12.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>';
@endphp
<div class="topline">AN INTIMATE BEACHFRONT RETREAT <span>✦</span> PLACENCIA PENINSULA, BELIZE</div>
<header class="header" id="header">
<nav aria-label="Main navigation" class="nav">
<a class="logo" href="{{ $logoHref }}"><img class="logo-mark" src="{{ asset('assets/logo.png') }}" width="160" height="160" alt="La Vida Belize"></a>
<div class="nav-links" id="navLinks">
<a href="{{ url('/') }}" @class(['is-current' => $navCurrent('/')])>Home</a>
<a href="{{ url('/about-us') }}" @class(['is-current' => $navCurrent('/about-us')])>About Us</a>
<a href="{{ url('/about-owner') }}" @class(['is-current' => $navCurrent('/about-owner')])>About Owner</a>
<a href="{{ url('/properties') }}" @class(['is-current' => $navCurrent('/properties') || request()->is('stay/*')])>Properties</a>
<a href="{{ url('/faq') }}" @class(['is-current' => $navCurrent('/faq')])>FAQs</a>
<a href="{{ url('/attractions') }}" @class(['is-current' => $navCurrent('/attractions')])>Attractions</a>
<a href="{{ url('/contact') }}" @class(['is-current' => $navCurrent('/contact')])>Contact Us</a>
</div>
<a class="nav-cta" href="{{ $bookHref }}">Book Now {!! $arrow !!}</a>
<button aria-controls="navLinks" aria-expanded="false" aria-label="Open menu" class="menu-toggle" id="menuToggle">☰</button>
</nav>
</header>
