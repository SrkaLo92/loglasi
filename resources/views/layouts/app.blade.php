<!doctype html>
<html lang="sr-Latn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
@include('partials.seo-head')
<link rel="stylesheet" href="{{ asset_v('css/osnova.css') }}">
@stack('head')
</head>
<body class="{{ $bodyClass ?? '' }}">
<a class="lg-skip" href="#sadrzaj">Pređi na sadržaj</a>
@include('partials.header')
<main id="sadrzaj">
@yield('content')
</main>
@include('partials.footer')
@stack('scripts')
</body>
</html>
