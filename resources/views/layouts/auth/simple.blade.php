<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    @php
        $branding = app(App\Actions\AppSettings\AppBranding::class);
        $backgroundUrl = $branding->signInBackgroundUrl();
    @endphp
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        <div @class(['bg-background flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10', 'bg-cover bg-center' => $backgroundUrl])
            @if ($backgroundUrl) style="background-image: url('{{ $backgroundUrl }}')" data-test="sign-in-background" @endif>
            <div @class([
                'flex w-full max-w-sm flex-col gap-2',
                'rounded-2xl bg-white/95 p-6 shadow-xl backdrop-blur dark:bg-zinc-900/95' => $backgroundUrl,
            ])>
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <x-brand-logo class="mb-1 h-10" />
                </a>

                @if ($branding->signInHeading() || $branding->signInMessage())
                    <div class="mb-2 text-center" data-test="sign-in-welcome">
                        @if ($branding->signInHeading())
                            <flux:heading size="lg">{{ $branding->signInHeading() }}</flux:heading>
                        @endif

                        @if ($branding->signInMessage())
                            <flux:text class="mt-1 whitespace-pre-line">{{ $branding->signInMessage() }}</flux:text>
                        @endif
                    </div>
                @endif

                <div class="flex flex-col gap-6">
                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
