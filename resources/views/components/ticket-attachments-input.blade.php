@props([
    'model' => 'attachments',
    'files' => [],
])

{{-- File picker for ticket attachments. Binds to the Livewire "attachments" property and needs a "removeAttachment" action. --}}
@php
    $limits = config('tickets.attachments');
@endphp

<flux:field>
    <flux:label>{{ __('Attachments') }}</flux:label>
    <flux:description>
        {{ __('Up to :count files, :size each.', ['count' => $limits['max_files'], 'size' => Illuminate\Support\Number::fileSize($limits['max_size_kb'] * 1024)]) }}
    </flux:description>

    <flux:input type="file" wire:model="{{ $model }}" multiple
        accept="{{ collect($limits['mimes'])->map(fn ($extension) => '.'.$extension)->implode(',') }}" />

    <div wire:loading wire:target="{{ $model }}">
        <flux:text variant="subtle" class="text-sm">{{ __('Uploading...') }}</flux:text>
    </div>

    @if (count($files) > 0)
        <ul class="mt-2 space-y-1">
            @foreach ($files as $index => $file)
                <li class="flex items-center justify-between gap-2 text-sm" wire:key="pending-attachment-{{ $index }}">
                    <span class="flex min-w-0 items-center gap-2">
                        <flux:icon.paper-clip variant="micro" class="shrink-0 text-zinc-400" />
                        <span class="truncate">{{ $file->getClientOriginalName() }}</span>
                    </span>

                    <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removeAttachment({{ $index }})" :aria-label="__('Remove')" />
                </li>
            @endforeach
        </ul>
    @endif

    <flux:error name="{{ $model }}" />
    @foreach (array_keys($files) as $index)
        <flux:error name="{{ $model }}.{{ $index }}" />
    @endforeach
</flux:field>
