@props(['title' => null])
@php
    $seo = app(\App\Services\SeoMetadata::class)->forRequest(request(), $title);
@endphp
<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<meta name="robots" content="{{ $seo['robots'] }}">
@if ($seo['canonical'])
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('seo.name') }}">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:image" content="{{ $seo['image'] }}">
<meta property="og:image:alt" content="{{ $seo['title'] }}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">
@endif
@if ($seo['structuredData'] !== [])
    <script type="application/ld+json">{!! json_encode($seo['structuredData'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
@endif
