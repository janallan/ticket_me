<section class="w-full">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Ticket priorities') }}</flux:heading>
            <flux:subheading size="lg">{{ __('How urgent a ticket is') }}</flux:subheading>
        </div>

        @can('create', App\Models\TicketPriority::class)
            <flux:button variant="primary" icon="plus" :href="route('ticket-priorities.create')" wire:navigate data-test="create-ticket-priority-button">
                {{ __('New priority') }}
            </flux:button>
        @endcan
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Order') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->priorities as $priority)
                <flux:table.row :key="$priority->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="$priority->color->value">{{ $priority->name }}</flux:badge>

                            @if ($priority->is_default)
                                <flux:text variant="subtle" class="text-xs">{{ __('Default') }}</flux:text>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell align="end">{{ $priority->sort_order }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @can('update', $priority)
                            <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('ticket-priorities.edit', $priority)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:button>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="3">
                        <flux:text variant="subtle">{{ __('No priorities yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
