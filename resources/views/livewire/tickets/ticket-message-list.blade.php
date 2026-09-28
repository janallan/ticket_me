<div class="space-y-6" data-test="ticket-thread">
    @if ($this->olderMessageCount > 0)
        {{-- Loading older entries adds them above the thread, so keep the entry that was at the top of
             the thread in the same place on screen instead of letting the page jump. --}}
        <div class="flex justify-center" x-data="{
            loadOlder() {
                const anchor = document.querySelector('[data-thread-entry]');
                const top = anchor?.getBoundingClientRect().top;

                $wire.showOlderMessages().then(() => requestAnimationFrame(() => {
                    if (anchor?.isConnected) {
                        window.scrollBy(0, anchor.getBoundingClientRect().top - top);
                    }
                }));
            },
        }">
            <flux:button size="sm" variant="ghost" icon="chevron-up" x-on:click="loadOlder" data-test="show-older-messages-button">
                {{ __('Show older (:count more)', ['count' => $this->olderMessageCount]) }}
            </flux:button>
        </div>
    @endif

    @foreach ($this->messages as $message)
        @if ($message->isLog())
            <div wire:key="message-{{ $message->id }}" data-thread-entry data-test="ticket-log" class="flex gap-3 px-2 text-sm">
                <flux:icon.pencil-square variant="micro" class="mt-0.5 shrink-0 text-zinc-400" />

                <div class="min-w-0 space-y-1">
                    <flux:text variant="subtle" class="text-xs">
                        {{ __(':name changed the ticket', ['name' => $message->author->name]) }}
                        · <span title="{{ $message->created_at?->toDayDateTimeString() }}">{{ $message->created_at?->diffForHumans() }}</span>
                    </flux:text>

                    <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800/50">
                        <flux:text class="text-xs font-medium">{{ __('Changes (original values)') }}</flux:text>
                        <flux:text class="whitespace-pre-line break-words text-xs">{{ $message->body }}</flux:text>
                    </div>
                </div>
            </div>
        @else
            <flux:card wire:key="message-{{ $message->id }}" data-thread-entry data-test="ticket-message"
                @class([
                    'space-y-1 px-4 py-3',
                    'border-amber-300! bg-amber-50! dark:border-amber-500/40! dark:bg-amber-500/10!' => $message->is_internal,
                ])>
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading>{{ $message->author->name }}</flux:heading>

                    <flux:text variant="subtle" class="text-xs">
                        · <span title="{{ $message->created_at?->toDayDateTimeString() }}">{{ $message->created_at?->diffForHumans() }}</span>
                    </flux:text>

                    @if ($message->is_internal)
                        <flux:badge size="sm" color="amber" icon="lock-closed">{{ __('Internal note') }}</flux:badge>
                    @endif
                </div>

                <flux:text class="whitespace-pre-line">{{ $message->body }}</flux:text>

                <x-ticket-attachment-list :attachments="$message->attachments" />
            </flux:card>
        @endif
    @endforeach
</div>
