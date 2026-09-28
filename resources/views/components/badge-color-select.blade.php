@props([
    'form',
    'color' => 'zinc',
    'name' => '',
])

{{-- Color picker for ticket statuses and priorities. Binds to "<form>.color" on the Livewire form array and previews "<form>.name" as it is typed. --}}
<flux:field>
    <flux:label>{{ __('Color') }}</flux:label>

    <div class="flex items-center gap-3">
        <div class="flex-1">
            <flux:select wire:model.live="{{ $form }}.color" required>
                @foreach (App\Enums\BadgeColor::cases() as $badgeColor)
                    <flux:select.option :value="$badgeColor->value" wire:key="color-{{ $badgeColor->value }}">{{ $badgeColor->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <flux:badge :color="$color" class="max-w-48">
            <span class="truncate" x-data x-text="($wire.{{ $form }}.name ?? '').trim() || @js(__('Preview'))">{{ filled($name) ? $name : __('Preview') }}</span>
        </flux:badge>
    </div>

    <flux:error name="{{ $form }}.color" />
</flux:field>
