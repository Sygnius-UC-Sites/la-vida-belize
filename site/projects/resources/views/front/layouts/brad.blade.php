<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#123c3a">
<meta name="description" content="@yield('description', optional($data ?? null)->meta_description)">
<title>@yield('title', optional($data ?? null)->meta_title ?? ($setting_data['website'] ?? 'La Vida Belize'))</title>
@if(!empty(optional($data ?? null)->meta_keywords))
<meta name="keywords" content="{{ $data->meta_keywords }}">
@endif
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,500;0,600;1,400;1,500&display=swap" rel="stylesheet">
<link rel="icon" href="{{ asset('assets/favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="{{ asset('css/styles.css') }}?v={{ @filemtime(public_path('css/styles.css')) ?: 1 }}">
@yield('head')
</head>
<body class="@yield('body-class', 'page')" id="top">
@include('front.partials.header')
@yield('content')
@include('front.partials.footer')
@yield('modals')
<script src="{{ asset('js/main.js') }}?v={{ @filemtime(public_path('js/main.js')) ?: 1 }}" defer></script>
@yield('scripts')
</body>
</html>
