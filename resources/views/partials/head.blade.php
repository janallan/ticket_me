@php
    $branding = app(App\Actions\AppSettings\AppBranding::class);
    $appName = $branding->name();
    $faviconUrl = $branding->faviconUrl();
    $accentColors = $branding->accentColors();
@endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.$appName : $appName }}
</title>

@if ($faviconUrl)
    <link rel="icon" href="{{ $faviconUrl }}">
    <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance

@if ($accentColors)
    {{-- The brand colour from App settings, in light and dark mode. --}}
    <style>
        :root {
            --color-accent: {{ $accentColors['light']['accent'] }};
            --color-accent-content: {{ $accentColors['light']['content'] }};
            --color-accent-foreground: {{ $accentColors['light']['foreground'] }};
        }

        .dark {
            --color-accent: {{ $accentColors['dark']['accent'] }};
            --color-accent-content: {{ $accentColors['dark']['content'] }};
            --color-accent-foreground: {{ $accentColors['dark']['foreground'] }};
        }
    </style>
@endif
