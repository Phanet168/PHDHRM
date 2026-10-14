<!DOCTYPE html>
<html lang="km" class="public-document">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if (config('site.favicon_url'))
        <link rel="icon" href="{{ config('site.favicon_url') }}">
    @endif
    <title>{{ ! empty($title) ? $title . ' · ' . config('site.name_km') : (config('site.seo_title') ?: config('site.name_km')) }}</title>
    @php
        $pageDescription = ($metaDescription ?? null) ?: config('site.seo_description');
        $pageOgTitle = ! empty($title) ? $title : config('site.seo_title', config('site.name_km'));
        $pageOgImage = ($ogImage ?? null) ? (str_starts_with($ogImage, 'http') ? $ogImage : asset($ogImage)) : null;
    @endphp
    @if ($pageDescription)
        <meta name="description" content="{{ $pageDescription }}">
    @endif
    @if (! empty($canonical))
        <link rel="canonical" href="{{ $canonical }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageOgTitle }}">
    @if ($pageDescription)
        <meta property="og:description" content="{{ $pageDescription }}">
    @endif
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($pageOgImage)
        <meta property="og:image" content="{{ $pageOgImage }}">
    @endif
    <meta name="twitter:card" content="{{ $pageOgImage ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $pageOgTitle }}">
    @if ($pageDescription)
        <meta name="twitter:description" content="{{ $pageDescription }}">
    @endif
    @if ($pageOgImage)
        <meta name="twitter:image" content="{{ $pageOgImage }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-ink antialiased">
    <x-page-loader />
    <div class="public-site">
        <x-header />

        <div class="public-main">
            @isset($slot)
                {{ $slot }}
            @else
                @yield('content')
            @endisset
        </div>

        <x-footer />
    </div>

    <x-back-to-top />
</body>
</html>
