@props([
    'tickets',
    'empty',
    'show' => 'status',
])

{{-- A short list of tickets on the dashboard. "show" picks the detail on the right: the status, or how long the ticket has been open. --}}
@if ($tickets->isEmpty())
    <flux:text variant="subtle" class="text-sm">{{ $empty }}</flux:text>
@else
    <ul class="divide-y divide-zinc-100 dark:divide-zinc-700">
        @foreach ($tickets as $ticket)
            <li wire:key="dashboard-ticket-{{ $ticket->id }}" class="flex items-center justify-between gap-4 py-2 first:pt-0 last:pb-0">
                <div class="min-w-0">
                    <flux:link :href="route('tickets.show', $ticket)" wire:navigate variant="subtle" class="block truncate font-medium">
                        {{ $ticket->subject }}
                    </flux:link>
                    <flux:text class="text-xs">
                        <span class="font-mono">{{ $ticket->number }}</span> · {{ $ticket->department->name }}
                    </flux:text>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <flux:badge size="sm" :color="$ticket->priority->color->value">{{ $ticket->priority->name }}</flux:badge>

                    @if ($show === 'age')
                        <flux:text class="text-xs tabular-nums" :title="$ticket->created_at?->toDayDateTimeString()">
                            {{ $ticket->created_at?->diffForHumans(short: true) }}
                        </flux:text>
                    @else
                        <flux:badge size="sm" :color="$ticket->status->color->value">{{ $ticket->status->name }}</flux:badge>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
