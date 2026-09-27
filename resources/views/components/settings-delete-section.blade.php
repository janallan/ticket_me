@props([
    'heading',
    'modal',
    'canDelete' => false,
    'blockedReason' => null,
    'confirmHeading',
    'confirmDescription' => null,
])

{{-- Delete section for settings forms. Calls the Livewire "delete" action after confirmation. --}}
<section class="mt-12 max-w-lg space-y-4">
    <div>
        <flux:heading>{{ $heading }}</flux:heading>

        @if (! $canDelete && $blockedReason)
            <flux:subheading>{{ $blockedReason }}</flux:subheading>
        @endif
    </div>

    <flux:error name="delete" />

    <flux:modal.trigger :name="$modal">
        <flux:button variant="danger" :disabled="! $canDelete" data-test="delete-button">{{ $heading }}</flux:button>
    </flux:modal.trigger>

    @if ($canDelete)
        <flux:modal :name="$modal" class="max-w-lg">
            <div class="space-y-6">
                <div>
                    <flux:heading size="lg" class="pe-8">{{ $confirmHeading }}</flux:heading>
                    <flux:subheading>{{ $confirmDescription ? $confirmDescription.' ' : '' }}{{ __('This cannot be undone.') }}</flux:subheading>
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                    </flux:modal.close>

                    <flux:button variant="danger" wire:click="delete" data-test="confirm-delete-button">
                        {{ $heading }}
                    </flux:button>
                </div>
            </div>
        </flux:modal>
    @endif
</section>
