@props([
    'theme' => 'auto',
])

{{--
    The company logo: the one uploaded in App settings, or the TicketMe wordmark. "auto" follows the
    light/dark appearance; "dark" always uses the dark-mode logo, for panels that are dark in both modes.
    Size it with a height class, such as class="h-8".
--}}
@php
    $branding = app(App\Actions\AppSettings\AppBranding::class);
    $name = $branding->name();
@endphp

@if ($theme === 'dark')
    <img src="{{ $branding->logoDarkUrl() }}" alt="{{ $name }}" {{ $attributes->class('w-auto max-w-full object-contain') }}>
@else
    <img src="{{ $branding->logoLightUrl() }}" alt="{{ $name }}" {{ $attributes->class('w-auto max-w-full object-contain dark:hidden') }}>
    <img src="{{ $branding->logoDarkUrl() }}" alt="{{ $name }}" {{ $attributes->class('hidden w-auto max-w-full object-contain dark:block') }}>
@endif
