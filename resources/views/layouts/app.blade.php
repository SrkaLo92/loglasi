<!doctype html>
<html lang="sr-Latn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title ?? 'loglasi.rs: besplatni oglasi za Loznicu i okolinu' }}</title>
@isset($description)<meta name="description" content="{{ $description }}">@endisset
@isset($canonical)<link rel="canonical" href="{{ $canonical }}">@endisset
@isset($robots)<meta name="robots" content="{{ $robots }}">@endisset
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
