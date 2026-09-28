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

    <div class="w-full max-w-2xl space-y-6">
        <flux:card>
            <form wire:submit="save" class="space-y-6">
                <flux:heading>{{ __('Priority details') }}</flux:heading>

                <flux:input wire:model="ticketPriorityForm.name" :label="__('Name')" type="text" required autofocus />

                <x-badge-color-select form="ticketPriorityForm" :color="$ticketPriorityForm['color']" :name="$ticketPriorityForm['name']" />

                <flux:input wire:model="ticketPriorityForm.sortOrder" :label="__('Order')" type="number" min="0" required
                    :description="__('Lower numbers are listed first.')" />

                <flux:checkbox wire:model="ticketPriorityForm.isDefault" :label="__('Default for new tickets')"
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
        </flux:card>

        @if ($this->priority)
            <x-settings-delete-section
                :heading="__('Delete priority')"
                modal="confirm-ticket-priority-deletion"
                :can-delete="auth()->user()->can('delete', $this->priority)"
                :blocked-reason="$this->priority->is_default
                    ? __('The default priority cannot be deleted. Make another priority the default first.')
                    : __('Tickets use this priority, so it cannot be deleted.')"
                :confirm-heading="__('Delete the :name priority?', ['name' => $this->priority->name])"
            />
        @endif
    </div>
</section>
