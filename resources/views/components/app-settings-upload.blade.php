@props([
    'field',
    'label',
    'description' => null,
    'currentUrl' => null,
    'defaultUrl' => null,
    'pending' => null,
    'accept' => 'image/png,image/jpeg,image/webp',
    'previewClass' => 'h-10',
    'darkPreview' => false,
])

{{--
    An App settings upload: a preview of the new file, the uploaded one or the default, a file picker bound
    to "appSettingsForm.<field>", and a Remove button when a file is uploaded.
--}}
@php
    $previewUrl = $pending?->isPreviewable() ? $pending->temporaryUrl() : ($currentUrl ?? $defaultUrl);
@endphp

<flux:field>
    <flux:label>{{ $label }}</flux:label>

    @if ($description)
        <flux:description>{{ $description }}</flux:description>
    @endif

    <div class="flex flex-wrap items-center gap-4">
        <div @class([
            'flex min-h-16 min-w-32 items-center justify-center rounded-lg border px-4 py-3',
            'border-zinc-200 bg-white dark:border-zinc-700' => ! $darkPreview,
            'border-zinc-700 bg-zinc-900' => $darkPreview,
        ])>
            @if ($previewUrl)
                <img src="{{ $previewUrl }}" alt="" class="{{ $previewClass }} w-auto max-w-56 object-contain" data-test="upload-preview-{{ $field }}">
            @else
                <flux:text variant="subtle" class="text-xs">{{ __('None') }}</flux:text>
            @endif
        </div>

        <div class="space-y-2">
            <flux:input type="file" wire:model="appSettingsForm.{{ $field }}" accept="{{ $accept }}" size="sm" />

            <div class="flex items-center gap-2">
                @if ($pending)
                    <flux:badge size="sm" color="amber">{{ __('New file, not saved yet') }}</flux:badge>
                @elseif ($currentUrl)
                    <flux:badge size="sm" color="green">{{ __('Uploaded') }}</flux:badge>
                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="removeUpload('{{ $field }}')"
                        wire:confirm="{{ __('Remove this file and go back to the default?') }}" data-test="remove-{{ $field }}">
                        {{ __('Remove') }}
                    </flux:button>
                @else
                    <flux:text variant="subtle" class="text-xs">{{ $defaultUrl ? __('Using the default.') : __('Not set.') }}</flux:text>
                @endif
            </div>
        </div>
    </div>

    <flux:error name="appSettingsForm.{{ $field }}" />
</flux:field>
