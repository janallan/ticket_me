@props([
    'attachments',
])

{{-- Download links for a ticket's or message's attachments. --}}
@if ($attachments->isNotEmpty())
    <ul {{ $attributes->class('mt-3 flex flex-wrap gap-2') }}>
        @foreach ($attachments as $attachment)
            <li wire:key="attachment-{{ $attachment->id }}">
                <flux:button size="sm" variant="filled" icon="paper-clip" :href="route('tickets.attachments.show', $attachment)">
                    <span class="max-w-48 truncate">{{ $attachment->original_name }}</span>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $attachment->humanSize() }}</span>
                </flux:button>
            </li>
        @endforeach
    </ul>
@endif
