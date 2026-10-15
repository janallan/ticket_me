@php
    $branding = app(App\Actions\AppSettings\AppBranding::class);
    $mailLogoUrl = $branding->mailLogoUrl();
@endphp
<x-mail::layout>
{{-- Header: the email logo from App settings, or the company name --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
@if ($mailLogoUrl)
<img src="{{ $mailLogoUrl }}" alt="{{ $branding->name() }}" style="max-height: 48px; max-width: 240px; height: auto; border: 0;">
@else
{{ $branding->name() }}
@endif
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer: the footer text from App settings, then the copyright line --}}
<x-slot:footer>
<x-mail::footer>
@if ($branding->mailFooter())
{!! nl2br(e($branding->mailFooter())) !!}

@endif
© {{ date('Y') }} {{ $branding->name() }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
