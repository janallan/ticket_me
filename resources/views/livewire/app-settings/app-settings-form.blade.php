<section class="w-full">
    @php
        $settings = $this->settings;
        $accent = $appSettingsForm['accentColor'];
        $validAccent = preg_match('/^#[0-9a-fA-F]{6}$/', $accent) === 1;
        $previewPalette = $validAccent ? App\Actions\AppSettings\AppBranding::accentPalette($accent) : null;
        $previewDarkPalette = $validAccent ? App\Actions\AppSettings\AppBranding::accentPalette($accent, dark: true) : null;
    @endphp

    <div class="mb-6">
        <flux:heading size="xl" level="1">{{ __('App settings') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Your company\'s name, logos, colour, emails and sign-in page. Empty fields use the TicketMe defaults.') }}</flux:subheading>
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <div class="w-full max-w-3xl space-y-6">
        <flux:radio.group wire:model.live="tab" variant="segmented" data-test="app-settings-tabs">
            @foreach (App\Livewire\AppSettings\AppSettingsForm::TABS as $tabKey => $tabLabel)
                <flux:radio :value="$tabKey" :label="__($tabLabel)" wire:key="tab-{{ $tabKey }}" />
            @endforeach
        </flux:radio.group>

        <form wire:submit="save" class="space-y-6">
            @if ($tab === 'branding')
                <flux:card class="space-y-6" data-test="branding-card">
                    <flux:heading>{{ __('Identity') }}</flux:heading>

                    <flux:input wire:model="appSettingsForm.name" :label="__('Company name')"
                        :placeholder="config('app.name')"
                        :description="__('Shown in page titles, on the sign-in page and in emails.')" />

                    <x-app-settings-upload field="logoLight" :label="__('Logo for light mode')"
                        :description="__('PNG, JPG or WebP, up to 1 MB. A wide logo about 40 pixels tall works best.')"
                        :current-url="$settings->fileUrl('logo_light_path')" :default-url="asset('images/ticketme-long.svg')"
                        :pending="$appSettingsForm['logoLight']" />

                    <x-app-settings-upload field="logoDark" :label="__('Logo for dark mode')"
                        :description="__('Light text for dark backgrounds. If empty, the light-mode logo is used.')"
                        :current-url="$settings->fileUrl('logo_dark_path')"
                        :default-url="$settings->fileUrl('logo_light_path') ?? asset('images/ticketme-long-dark.svg')"
                        :pending="$appSettingsForm['logoDark']" dark-preview />

                    <x-app-settings-upload field="favicon" :label="__('Favicon')"
                        :description="__('The browser tab icon. PNG or ICO, up to 256 KB. A square image works best.')"
                        :current-url="$settings->fileUrl('favicon_path')" :default-url="asset('favicon.svg')"
                        :pending="$appSettingsForm['favicon']" accept="image/png,image/x-icon,.ico" preview-class="size-8" />
                </flux:card>

                <flux:card class="space-y-4" data-test="brand-colour-card">
                    <div>
                        <flux:heading>{{ __('Brand colour') }}</flux:heading>
                        <flux:text class="text-sm">{{ __('Used for primary buttons, links and highlights, in light and dark mode.') }}</flux:text>
                    </div>

                    <div class="flex flex-wrap items-end gap-3">
                        <input type="color" aria-label="{{ __('Pick a colour') }}"
                            value="{{ $previewPalette['accent'] ?? '#262626' }}"
                            x-on:input="$wire.set('appSettingsForm.accentColor', $event.target.value)"
                            class="h-10 w-14 cursor-pointer rounded-lg border border-zinc-200 bg-white p-1 dark:border-zinc-700 dark:bg-zinc-800">

                        <div class="w-40">
                            <flux:input wire:model.live.debounce.400ms="appSettingsForm.accentColor" :label="__('Hex')" placeholder="#ec3013" />
                        </div>

                        <flux:button variant="ghost" icon="arrow-uturn-left" wire:click="resetAccentColor">{{ __('Reset to default') }}</flux:button>
                    </div>

                    <flux:error name="appSettingsForm.accentColor" />

                    @if ($previewPalette)
                        <div class="grid gap-3 sm:grid-cols-2" data-test="accent-preview">
                            @foreach (['light' => [$previewPalette, 'border-zinc-200 bg-white', 'text-zinc-500'], 'dark' => [$previewDarkPalette, 'border-zinc-700 bg-[#262626]', 'text-zinc-400']] as $mode => [$palette, $panelClass, $labelClass])
                                <div class="flex flex-wrap items-center gap-4 rounded-lg border p-4 {{ $panelClass }}" wire:key="accent-preview-{{ $mode }}">
                                    <span class="w-full text-xs uppercase tracking-wide {{ $labelClass }}">{{ $mode === 'light' ? __('Light mode') : __('Dark mode') }}</span>
                                    <span class="inline-flex h-10 items-center rounded-lg px-4 text-sm font-medium"
                                        style="background: {{ $palette['accent'] }}; color: {{ $palette['foreground'] }}">{{ __('Primary button') }}</span>
                                    <span class="text-sm font-medium underline" style="color: {{ $palette['content'] }}">{{ __('A link') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex flex-wrap items-center gap-4 rounded-lg border border-dashed border-zinc-200 p-4 dark:border-zinc-700" data-test="accent-preview">
                            <flux:text class="text-xs uppercase tracking-wide">{{ __('Preview') }}</flux:text>
                            <flux:button variant="primary" size="sm" type="button">{{ __('Primary button') }}</flux:button>
                            <flux:link href="#" class="text-sm">{{ __('A link') }}</flux:link>
                            <flux:text variant="subtle" class="text-xs">{{ __('Default colours') }}</flux:text>
                        </div>
                    @endif
                </flux:card>
            @endif

            @if ($tab === 'email')
                <flux:card class="space-y-6" data-test="email-card">
                    <flux:heading>{{ __('Emails') }}</flux:heading>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="appSettingsForm.mailFromName" :label="__('Sender name')"
                            :placeholder="$appSettingsForm['name'] ?: config('mail.from.name')" />
                        <flux:input wire:model="appSettingsForm.mailFromAddress" type="email" :label="__('Sender address')"
                            :placeholder="config('mail.from.address')" />
                    </div>

                    <x-app-settings-upload field="mailLogo" :label="__('Email logo')"
                        :description="__('Shown at the top of every email. PNG or JPG work in all email clients. If empty, the light-mode logo is used, or the company name.')"
                        :current-url="$settings->fileUrl('mail_logo_path')" :default-url="$settings->fileUrl('logo_light_path')"
                        :pending="$appSettingsForm['mailLogo']" />

                    <flux:textarea wire:model="appSettingsForm.mailFooter" :label="__('Footer text')" rows="3"
                        :description="__('Shown under every email, for example your address or a support phone number.')" />
                </flux:card>
            @endif

            @if ($tab === 'sign-in')
                <flux:card class="space-y-6" data-test="sign-in-card">
                    <flux:heading>{{ __('Sign-in page') }}</flux:heading>

                    <flux:input wire:model="appSettingsForm.signInHeading" :label="__('Heading')" :placeholder="__('Welcome to the helpdesk')" />

                    <flux:textarea wire:model="appSettingsForm.signInMessage" :label="__('Message')" rows="3"
                        :description="__('A short line under the heading, for example who to contact for an account.')" />

                    <x-app-settings-upload field="signInBackground" :label="__('Background image')"
                        :description="__('PNG, JPG or WebP, up to 4 MB. Shown behind the sign-in form.')"
                        :current-url="$settings->fileUrl('sign_in_background_path')"
                        :pending="$appSettingsForm['signInBackground']" preview-class="h-20" />
                </flux:card>
            @endif

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" data-test="save-app-settings-button">{{ __('Save') }}</flux:button>
                <flux:text variant="subtle" class="text-sm">{{ __('Saves every tab.') }}</flux:text>
            </div>
        </form>
    </div>
</section>
