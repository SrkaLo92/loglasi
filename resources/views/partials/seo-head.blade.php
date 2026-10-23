{{-- SEO zaglavlje: podaci iz App\Support\Seo (Seo::for($model) u kontroleru), ili iz promenljivih šablona
     $title, $description, $canonical, $robots. Pravila: docs/moduli/seo.md --}}
@php
    $seoBuilder = \App\Support\Seo::get();
    if (isset($title)) { $seoBuilder->title($title); }
    if (isset($description)) { $seoBuilder->description($description); }
    if (isset($canonical)) { $seoBuilder->canonical($canonical); }
    if (isset($robots) && str_contains(strtolower($robots), 'noindex')) { $seoBuilder->noindex(); }
    $seo = $seoBuilder->resolve();
@endphp
<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
@if ($seo['canonical'])
<link rel="canonical" href="{{ $seo['canonical'] }}">
@endif
@foreach ($seo['og'] as $property => $content)
<meta property="{{ $property }}" content="{{ $content }}">
@endforeach
@foreach ($seo['twitter'] as $name => $content)
<meta name="{{ $name }}" content="{{ $content }}">
@endforeach
@foreach ($seo['jsonld'] as $schema)
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endforeach
