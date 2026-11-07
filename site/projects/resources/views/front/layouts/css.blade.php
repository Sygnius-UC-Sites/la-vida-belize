{{-- Global frontend CSS --}}

{{-- Vendor: Bootstrap, Bootstrap Icons, Font Awesome, Fancybox, Flatpickr, Leaflet --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
    crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">

@php
    $assetVersion = $setting_data['version_css_js'] ?? '20260619.1';
    $assetVer = function ($path) use ($assetVersion) {
        $path = ltrim($path, '/');
        $candidates = [
            public_path($path),
            base_path('../' . $path),
            rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/' . $path,
        ];
        foreach ($candidates as $full) {
            if (is_file($full)) return filemtime($full);
        }
        return $assetVersion;
    };
@endphp

{{-- Structural base (shared bundle — keeps property-detail / checkout / payment layouts working).
     The active client theme below re-maps its CSS variables to the client palette. --}}
<link rel="stylesheet" href="{{ asset('front/base/css/style.css') }}?ver={{ $assetVer('front/base/css/style.css') }}">
<link rel="stylesheet" href="{{ asset('front/base/css/responsive.css') }}?ver={{ $assetVer('front/base/css/responsive.css') }}">
<link rel="stylesheet" href="{{ asset('front/css/guest-selector.css') }}?ver={{ $assetVer('front/css/guest-selector.css') }}">

{{-- Toastr stays for admin-friendly flash notifications --}}
<link rel="stylesheet" href="{{ asset('toastr/toastr.css') }}?ver={{ $assetVer('toastr/toastr.css') }}">

{{-- Easepick base CSS is loaded inside its Shadow DOM via VR_DATEPICKER_CONFIG.easepickCss;
     do NOT include globally — its `*` reset and `.container` rule collide with Bootstrap. --}}
@include('front.layouts.theme')
@yield('css')
