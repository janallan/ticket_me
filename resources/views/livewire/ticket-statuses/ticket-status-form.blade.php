<section class="w-full">
    <x-slot:title>{{ $this->status ? __('Edit status') : __('New status') }}</x-slot:title>

    <div class="mb-6">
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item :href="route('ticket-statuses.index')" wire:navigate>{{ __('Ticket statuses') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $this->status ? $this->status->name : __('New status') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:heading size="xl" level="1">{{ $this->status ? __('Edit status') : __('New status') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Name the status and choose how it looks and behaves') }}</flux:subheading>
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <form wire:submit="save" class="w-full max-w-lg space-y-6">
        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />

        <x-badge-color-select :color="$color" :name="$name" />

        <flux:input wire:model="sortOrder" :label="__('Order')" type="number" min="0" required
            :description="__('Lower numbers are listed first.')" />

        <flux:checkbox wire:model="isClosed" :label="__('Counts as closed')"
            :description="__('Tickets in this status are treated as finished and hidden from open queues.')" />

        <flux:checkbox wire:model="isDefault" :label="__('Default for new tickets')"
            :disabled="$this->status?->is_default"
            :description="$this->status?->is_default
                ? __('This is the default. Make another status the default to change it.')
                : __('New tickets start in this status.')" />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit" data-test="save-ticket-status-button">
                {{ __('Save') }}
            </flux:button>

            <flux:button variant="ghost" :href="route('ticket-statuses.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>

    @if ($this->status)
        <x-settings-delete-section
            :heading="__('Delete status')"
            modal="confirm-ticket-status-deletion"
            :can-delete="auth()->user()->can('delete', $this->status)"
            :blocked-reason="__('The default status cannot be deleted. Make another status the default first.')"
            :confirm-heading="__('Delete the :name status?', ['name' => $this->status->name])"
        />
    @endif
</section>
