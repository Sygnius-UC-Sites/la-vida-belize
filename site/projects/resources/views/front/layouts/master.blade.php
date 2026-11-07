<!DOCTYPE html>
<html lang="en">
<head>
@include("front.layouts.head")
@yield("header-section")
</head>
@php
    $themeBodyClass = trim((string) ($setting_data['frontend_theme_class'] ?? 'client-theme-base'));
    $pageBodyClass = trim($__env->yieldContent('body-class'));
@endphp
<body class="{{ trim($themeBodyClass . ' ' . $pageBodyClass) }}">
@include("front.layouts.header")
@yield("container")
@include("front.partials.sticky-book-now")
@include("front.layouts.footer")
@yield("footer-section")
</body>
</html>
