<section class="w-full">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Ticket statuses') }}</flux:heading>
            <flux:subheading size="lg">{{ __('The stages a ticket moves through') }}</flux:subheading>
        </div>

        @can('create', App\Models\TicketStatus::class)
            <flux:button variant="primary" icon="plus" :href="route('ticket-statuses.create')" wire:navigate data-test="create-ticket-status-button">
                {{ __('New status') }}
            </flux:button>
        @endcan
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Name') }}</flux:table.column>
            <flux:table.column>{{ __('Counts as') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Order') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->statuses as $status)
                <flux:table.row :key="$status->id">
                    <flux:table.cell>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="$status->color->value">{{ $status->name }}</flux:badge>

                            @if ($status->is_default)
                                <flux:text variant="subtle" class="text-xs">{{ __('Default') }}</flux:text>
                            @endif
                        </div>
                    </flux:table.cell>
                    <flux:table.cell>{{ $status->is_closed ? __('Closed') : __('Open') }}</flux:table.cell>
                    <flux:table.cell align="end">{{ $status->sort_order }}</flux:table.cell>
                    <flux:table.cell align="end">
                        @can('update', $status)
                            <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('ticket-statuses.edit', $status)" wire:navigate>
                                {{ __('Edit') }}
                            </flux:button>
                        @endcan
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4">
                        <flux:text variant="subtle">{{ __('No statuses yet.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
