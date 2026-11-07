@php
    $stickyBookUrl = url('/stays');
    $isPropertyDetail = request()->is('stay/*') || request()->is('properties/*');
@endphp

<a
    href="{{ $isPropertyDetail ? '#booking-widget' : $stickyBookUrl }}"
    class="vr-sticky-book-now"
    data-vr-sticky-book
>
    Book Now
</a>

<style>
    .vr-sticky-book-now {
        position: fixed;
        right: 22px;
        bottom: 22px;
        z-index: 1035;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 124px;
        min-height: 48px;
        padding: 13px 22px;
        border-radius: 999px;
        background: #111827;
        color: #fff;
        font-weight: 700;
        line-height: 1;
        text-decoration: none;
        box-shadow: 0 16px 38px rgba(17, 24, 39, .24);
        transition: transform .18s ease, box-shadow .18s ease, background .18s ease;
    }

    .vr-sticky-book-now:hover,
    .vr-sticky-book-now:focus {
        color: #fff;
        background: #0f766e;
        text-decoration: none;
        transform: translateY(-2px);
        box-shadow: 0 18px 42px rgba(15, 118, 110, .28);
    }

    @media (max-width: 767px) {
        .vr-sticky-book-now {
            right: 14px;
            bottom: 14px;
            min-width: 112px;
            min-height: 44px;
            padding: 12px 18px;
            font-size: 14px;
        }
    }
</style>

<script>
    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-vr-sticky-book]');
        if (!link || link.getAttribute('href') !== '#booking-widget') return;

        var target = document.getElementById('booking-widget');
        if (!target) return;

        event.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
</script>
