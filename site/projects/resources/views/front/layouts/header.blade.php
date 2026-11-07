@php
    $brandName = $setting_data['website'] ?? 'Your Brand';
    $brandLogo = $setting_data['header_logo'] ?? ($setting_data['logo'] ?? 'front/themes/base/img/logo.svg');
    $brandLogoUrl = \Illuminate\Support\Str::startsWith((string) $brandLogo, ['http://', 'https://', '/'])
        ? $brandLogo
        : asset(ltrim((string) $brandLogo, '/'));
    $isActive = fn (string $path): bool => request()->is(trim($path, '/')) || ($path === '/' && request()->path() === '/');
    $navMenu = json_decode($setting_data['nav_menu'] ?? '[]', true) ?: [];
    $navUrl = fn ($u) => \Illuminate\Support\Str::startsWith((string) $u, ['http://', 'https://']) ? $u : url($u ?: '/');
@endphp

<header class="nav" id="mainNav">
    <div class="nav__inner container">
        <a href="{{ url('/') }}" class="nav__logo" aria-label="{{ $brandName }} home">
            <img src="{{ $brandLogoUrl }}" alt="{{ $brandName }} Logo" loading="lazy">
        </a>

        <nav class="nav__links" id="navLinks" aria-label="Primary navigation">
            @foreach($navMenu as $navItem)
                <a href="{{ $navUrl($navItem['url'] ?? '/') }}" @class(['is-active' => $isActive($navItem['url'] ?? '/')])>{{ $navItem['label'] ?? '' }}</a>
            @endforeach
        </nav>

        @if(!empty($setting_data['nav_cta_label']))
            <a href="{{ $navUrl($setting_data['nav_cta_url'] ?? '/') }}" class="btn btn--gold nav__cta">{{ $setting_data['nav_cta_label'] }}</a>
        @endif

        <button class="nav__toggle" id="navToggle" type="button" aria-label="Toggle menu" aria-controls="navLinks" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
    </div>
</header>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var nav = document.getElementById('mainNav');
    var links = document.getElementById('navLinks');
    var toggle = document.getElementById('navToggle');
    var sync = function () {
        if (!nav) return;
        nav.classList.toggle('scrolled', window.scrollY > 8);
    };
    sync();
    window.addEventListener('scroll', sync, { passive: true });
    if (toggle && links) {
        toggle.addEventListener('click', function () {
            var open = links.classList.toggle('open');
            toggle.classList.toggle('open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
});
</script>
