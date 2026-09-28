<section class="w-full">
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Tickets') }}</flux:heading>
            <flux:subheading size="lg">{{ __('Requests you opened, and the ones you work') }}</flux:subheading>
        </div>

        @can('create', App\Models\Ticket::class)
            <flux:button variant="primary" icon="plus" :href="route('tickets.create')" wire:navigate data-test="create-ticket-button">
                {{ __('New ticket') }}
            </flux:button>
        @endcan
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center">
        <div class="lg:flex-[2]">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search number, subject or description')"
                :aria-label="__('Search')"
                clearable
            />
        </div>

        <div class="lg:flex-1">
            <flux:select wire:model.live="status" :aria-label="__('Status')">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach ($this->statuses as $statusOption)
                    <flux:select.option :value="$statusOption->id" wire:key="status-filter-{{ $statusOption->id }}">{{ $statusOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="lg:flex-1">
            <flux:select wire:model.live="priority" :aria-label="__('Priority')">
                <flux:select.option value="">{{ __('All priorities') }}</flux:select.option>
                @foreach ($this->priorities as $priorityOption)
                    <flux:select.option :value="$priorityOption->id" wire:key="priority-filter-{{ $priorityOption->id }}">{{ $priorityOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="lg:flex-1">
            <flux:select wire:model.live="department" :aria-label="__('Department')">
                <flux:select.option value="">{{ __('All departments') }}</flux:select.option>
                @foreach ($this->departments as $departmentOption)
                    <flux:select.option :value="$departmentOption->id" wire:key="department-filter-{{ $departmentOption->id }}">{{ $departmentOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap items-center gap-x-8 gap-y-3" data-test="ticket-list-switches">
        <flux:switch wire:model.live="openedByMe" :label="__('Opened by me')" align="left" />

        @if ($this->canWorkTickets)
            <flux:switch wire:model.live="assignedToMe" :label="__('Assigned to me')" align="left" />
            <flux:switch wire:model.live="unassigned" :label="__('Unassigned')" align="left" />
        @endif

        <flux:switch wire:model.live="showClosed" :label="__('Show closed')" align="left" />
    </div>

    <flux:table :paginate="$this->tickets">
        <flux:table.columns>
            <flux:table.column>{{ __('Number') }}</flux:table.column>
            <flux:table.column>{{ __('Subject') }}</flux:table.column>
            <flux:table.column>{{ __('Department') }}</flux:table.column>
            <flux:table.column>{{ __('Priority') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column>{{ __('Assignee') }}</flux:table.column>
            <flux:table.column>{{ __('Last activity') }}</flux:table.column>
            <flux:table.column />
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($this->tickets as $ticket)
                <flux:table.row :key="$ticket->id">
                    <flux:table.cell class="whitespace-nowrap font-mono text-xs">{{ $ticket->number }}</flux:table.cell>
                    <flux:table.cell variant="strong" class="max-w-md truncate">
                        <flux:link :href="route('tickets.show', $ticket)" wire:navigate variant="subtle">{{ $ticket->subject }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell>{{ $ticket->department->name }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$ticket->priority->color->value">{{ $ticket->priority->name }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge size="sm" :color="$ticket->status->color->value">{{ $ticket->status->name }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell>
                        @if ($ticket->assignee)
                            {{ $ticket->assignee->name }}
                        @else
                            <flux:text variant="subtle">{{ __('Unassigned') }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-nowrap">
                        <span title="{{ $ticket->last_activity_at?->toDayDateTimeString() }}">{{ $ticket->last_activity_at?->diffForHumans() }}</span>
                    </flux:table.cell>
                    <flux:table.cell align="end">
                        <flux:button size="sm" variant="ghost" icon="eye" :href="route('tickets.show', $ticket)" wire:navigate data-test="view-ticket-button">
                            {{ __('View') }}
                        </flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="8">
                        <flux:text variant="subtle">{{ __('No tickets match these filters.') }}</flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</section>
