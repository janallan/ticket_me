@props([
    'rows',
    'empty',
])

{{--
    A labelled count per row with a thin bar showing its share of the busiest row. Every row carries its
    name and number as text, so the bar is never the only way to read it. "color" is an optional badge
    color that identifies the row (a status); bars always use one neutral hue.
--}}
@php
    $max = max(1, (int) collect($rows)->max('count'));
@endphp

@if (collect($rows)->sum('count') === 0)
    <flux:text variant="subtle" class="text-sm">{{ $empty }}</flux:text>
@else
    <ul class="space-y-3">
        @foreach ($rows as $row)
            <li wire:key="breakdown-{{ $loop->index }}">
                <a href="{{ $row['href'] }}" wire:navigate class="group block space-y-1">
                    <div class="flex items-center justify-between gap-2 text-sm">
                        @if ($row['color'])
                            <flux:badge size="sm" :color="$row['color']">{{ $row['label'] }}</flux:badge>
                        @else
                            <span class="truncate text-zinc-700 group-hover:underline dark:text-zinc-200">{{ $row['label'] }}</span>
                        @endif

                        <span class="tabular-nums text-zinc-500 dark:text-zinc-400">{{ number_format($row['count']) }}</span>
                    </div>

                    <div class="h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-700" aria-hidden="true">
                        <div class="h-1.5 rounded-full bg-zinc-800 dark:bg-zinc-200" style="width: {{ round($row['count'] / $max * 100) }}%"></div>
                    </div>
                </a>
            </li>
        @endforeach
    </ul>
@endif
