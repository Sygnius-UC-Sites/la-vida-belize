<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="description" content="@yield('description')" />
<meta name="keywords" content="@yield('keywords')" />
<title>@yield('title', config('app.name', 'Vacation Rentals') . ' - Stay Somewhere Extraordinary')</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
    integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
    crossorigin="anonymous" referrerpolicy="no-referrer" />
  <script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@5.0/dist/fancybox/fancybox.umd.js"></script>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
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
  <link rel="stylesheet" href="{{ asset('front/base/css/style.css') }}?ver={{ $assetVer('front/base/css/style.css') }}" />
  <link rel="stylesheet" href="{{ asset('front/base/css/responsive.css') }}?ver={{ $assetVer('front/base/css/responsive.css') }}" />
  <link rel="stylesheet" href="{{ asset('front/css/guest-selector.css') }}?ver={{ $assetVer('front/css/guest-selector.css') }}" />

<link rel="stylesheet" href="{{ asset('toastr/toastr.css') }}?ver={{ $assetVer('toastr/toastr.css') }}" />
@include('front.layouts.theme')
@yield('css')
