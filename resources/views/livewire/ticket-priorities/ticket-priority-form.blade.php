<section class="w-full">
    <x-slot:title>{{ $this->priority ? __('Edit priority') : __('New priority') }}</x-slot:title>

    <div class="mb-6">
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item :href="route('ticket-priorities.index')" wire:navigate>{{ __('Ticket priorities') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $this->priority ? $this->priority->name : __('New priority') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <flux:heading size="xl" level="1">{{ $this->priority ? __('Edit priority') : __('New priority') }}</flux:heading>
        <flux:subheading size="lg">{{ __('Name the priority and choose how it looks') }}</flux:subheading>
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <form wire:submit="save" class="w-full max-w-lg space-y-6">
        <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus />

        <x-badge-color-select :color="$color" :name="$name" />

        <flux:input wire:model="sortOrder" :label="__('Order')" type="number" min="0" required
            :description="__('Lower numbers are listed first.')" />

        <flux:checkbox wire:model="isDefault" :label="__('Default for new tickets')"
            :disabled="$this->priority?->is_default"
            :description="$this->priority?->is_default
                ? __('This is the default. Make another priority the default to change it.')
                : __('New tickets get this priority unless another is chosen.')" />

        <div class="flex items-center gap-4">
            <flux:button variant="primary" type="submit" data-test="save-ticket-priority-button">
                {{ __('Save') }}
            </flux:button>

            <flux:button variant="ghost" :href="route('ticket-priorities.index')" wire:navigate>
                {{ __('Cancel') }}
            </flux:button>
        </div>
    </form>

    @if ($this->priority)
        <x-settings-delete-section
            :heading="__('Delete priority')"
            modal="confirm-ticket-priority-deletion"
            :can-delete="auth()->user()->can('delete', $this->priority)"
            :blocked-reason="__('The default priority cannot be deleted. Make another priority the default first.')"
            :confirm-heading="__('Delete the :name priority?', ['name' => $this->priority->name])"
        />
    @endif
</section>
