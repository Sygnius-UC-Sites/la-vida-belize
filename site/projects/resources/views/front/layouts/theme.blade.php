@php
    $themeFonts = trim((string) ($setting_data['frontend_theme_fonts'] ?? 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Montserrat:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap'));
    $themeCss = trim((string) ($setting_data['frontend_theme_css'] ?? 'front/themes/base/css/theme.css'));
    $clientSourceCss = trim((string) ($setting_data['frontend_source_css'] ?? 'front/themes/base/css/style.css'));

    $themeAssetVersion = $setting_data['version_css_js'] ?? '20260619.1';
    $themeAssetVer = function ($path) use ($themeAssetVersion) {
        $path = ltrim($path, '/');
        $candidates = [
            public_path($path),
            base_path('../' . $path),
            rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/' . $path,
        ];
        foreach ($candidates as $full) {
            if (is_file($full)) return filemtime($full);
        }
        return $themeAssetVersion;
    };

    $themeCssHref = $themeCss;
    if ($themeCss !== '' && !\Illuminate\Support\Str::startsWith($themeCss, ['http://', 'https://', '/'])) {
        $themeCssHref = asset($themeCss) . '?ver=' . $themeAssetVer($themeCss);
    }
@endphp

@if($themeFonts !== '')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ $themeFonts }}" rel="stylesheet">
@endif

@if($themeCss !== '')
    <link rel="stylesheet" href="{{ $themeCssHref }}">
@endif

@if($clientSourceCss !== '')
    <link rel="stylesheet" href="{{ asset($clientSourceCss) }}?ver={{ $themeAssetVer($clientSourceCss) }}">
@endif
