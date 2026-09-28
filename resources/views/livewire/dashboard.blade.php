<section class="w-full">
    @php
        $counts = $this->counts;
        $worksTickets = $this->scope !== 'mine';

        $tiles = $worksTickets
            ? [
                ['label' => __('Open'), 'value' => $counts['open'], 'icon' => 'inbox', 'href' => route('tickets.index')],
                ['label' => __('Unassigned'), 'value' => $counts['unassigned'], 'icon' => 'user-plus', 'href' => route('tickets.index', ['unassigned' => 1])],
                ['label' => __('Assigned to me'), 'value' => $counts['assignedToMe'], 'icon' => 'user', 'href' => route('tickets.index', ['assignedToMe' => 1])],
                ['label' => __('Closed in the last 7 days'), 'value' => $counts['closedThisWeek'], 'icon' => 'check-circle', 'href' => route('tickets.index', ['showClosed' => 1])],
            ]
            : [
                ['label' => __('My open tickets'), 'value' => $counts['open'], 'icon' => 'inbox', 'href' => route('tickets.index', ['openedByMe' => 1])],
                ['label' => __('Closed in the last 7 days'), 'value' => $counts['closedThisWeek'], 'icon' => 'check-circle', 'href' => route('tickets.index', ['openedByMe' => 1, 'showClosed' => 1])],
                ['label' => __('All my tickets'), 'value' => $counts['total'], 'icon' => 'rectangle-stack', 'href' => route('tickets.index', ['openedByMe' => 1, 'showClosed' => 1])],
            ];
    @endphp

    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
            <flux:subheading size="lg">
                {{ match ($this->scope) {
                    'all' => __('Every ticket, across all departments'),
                    'departments' => __('Tickets in your departments, and the ones you opened'),
                    default => __('The tickets you opened'),
                } }}
            </flux:subheading>
        </div>

        @can('create', App\Models\Ticket::class)
            <flux:button variant="primary" icon="plus" :href="route('tickets.create')" wire:navigate>
                {{ __('New ticket') }}
            </flux:button>
        @endcan
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <div @class(['mb-8 grid gap-4 sm:grid-cols-2', 'lg:grid-cols-4' => $worksTickets, 'lg:grid-cols-3' => ! $worksTickets]) data-test="dashboard-tiles">
        @foreach ($tiles as $tile)
            <a href="{{ $tile['href'] }}" wire:navigate wire:key="tile-{{ $loop->index }}"
                class="block rounded-xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--color-accent)">
                <flux:card class="h-full space-y-2 transition hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                    <div class="flex items-center justify-between gap-2">
                        <flux:text class="text-sm">{{ $tile['label'] }}</flux:text>
                        <flux:icon :name="$tile['icon']" variant="mini" class="text-zinc-400" />
                    </div>
                    <div class="text-3xl font-semibold tabular-nums text-zinc-900 dark:text-white" data-test="tile-value">{{ number_format($tile['value']) }}</div>
                </flux:card>
            </a>
        @endforeach
    </div>

    <div class="grid gap-8 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            @if ($worksTickets)
                <flux:card class="space-y-4" data-test="dashboard-assigned-to-me">
                    <div class="flex items-center justify-between gap-2">
                        <flux:heading>{{ __('Assigned to me') }}</flux:heading>
                        <flux:link :href="route('tickets.index', ['assignedToMe' => 1])" wire:navigate class="text-sm">{{ __('View all') }}</flux:link>
                    </div>

                    <x-dashboard-ticket-list :tickets="$this->assignedToMe" :empty="__('Nothing is assigned to you.')" show="status" />
                </flux:card>

                <flux:card class="space-y-4" data-test="dashboard-oldest-unassigned">
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <flux:heading>{{ __('Waiting longest for someone') }}</flux:heading>
                            <flux:text class="text-sm">{{ __('Open tickets nobody has picked up yet, oldest first.') }}</flux:text>
                        </div>
                        <flux:link :href="route('tickets.index', ['unassigned' => 1])" wire:navigate class="shrink-0 text-sm">{{ __('View all') }}</flux:link>
                    </div>

                    <x-dashboard-ticket-list :tickets="$this->oldestUnassigned" :empty="__('Every open ticket has someone on it.')" show="age" />
                </flux:card>
            @else
                <flux:card class="space-y-4" data-test="dashboard-recent-requests">
                    <div class="flex items-center justify-between gap-2">
                        <flux:heading>{{ __('My recent tickets') }}</flux:heading>
                        <flux:link :href="route('tickets.index', ['openedByMe' => 1, 'showClosed' => 1])" wire:navigate class="text-sm">{{ __('View all') }}</flux:link>
                    </div>

                    <x-dashboard-ticket-list :tickets="$this->recentRequests" :empty="__('You have not opened any tickets yet.')" show="status" />
                </flux:card>
            @endif
        </div>

        <aside class="space-y-6 lg:col-span-1">
            <flux:card class="space-y-4" data-test="dashboard-by-status">
                <flux:heading>{{ $worksTickets ? __('Open tickets by status') : __('My open tickets by status') }}</flux:heading>

                <x-dashboard-breakdown :rows="$this->openByStatus->map(fn ($status) => [
                    'label' => $status->name,
                    'color' => $status->color->value,
                    'count' => $status->tickets_count,
                    'href' => route('tickets.index', ['status' => $status->id]),
                ])" :empty="__('No open tickets.')" />
            </flux:card>

            @if ($worksTickets)
                <flux:card class="space-y-4" data-test="dashboard-by-department">
                    <flux:heading>{{ __('Open tickets by department') }}</flux:heading>

                    <x-dashboard-breakdown :rows="$this->openByDepartment->map(fn ($department) => [
                        'label' => $department->name,
                        'color' => null,
                        'count' => $department->open_tickets_count,
                        'href' => route('tickets.index', ['department' => $department->id]),
                    ])" :empty="__('No open tickets.')" />
                </flux:card>
            @endif
        </aside>
    </div>
</section>
