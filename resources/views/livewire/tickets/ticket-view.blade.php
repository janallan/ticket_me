<section class="w-full">
    <x-slot:title>{{ $this->ticket->number }} {{ $this->ticket->subject }}</x-slot:title>

    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <flux:breadcrumbs class="mb-2">
                <flux:breadcrumbs.item :href="route('tickets.index')" wire:navigate>{{ __('Tickets') }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ $this->ticket->number }}</flux:breadcrumbs.item>
            </flux:breadcrumbs>

            <div class="flex flex-wrap items-center gap-3">
                <flux:heading size="xl" level="1">{{ $this->ticket->subject }}</flux:heading>
                <flux:badge size="sm" :color="$this->ticket->status->color->value">{{ $this->ticket->status->name }}</flux:badge>
                <flux:badge size="sm" :color="$this->ticket->priority->color->value">{{ $this->ticket->priority->name }}</flux:badge>
            </div>

            <flux:subheading size="lg">
                {{ __(':number opened by :name in :department, :date', [
                    'number' => $this->ticket->number,
                    'name' => $this->ticket->requester->name,
                    'department' => $this->ticket->department->name,
                    'date' => $this->ticket->created_at?->diffForHumans(),
                ]) }}
            </flux:subheading>
        </div>

        @can('update', $this->ticket)
            <flux:button icon="pencil-square" :href="route('tickets.edit', $this->ticket)" wire:navigate data-test="edit-ticket-button">
                {{ __('Edit ticket') }}
            </flux:button>
        @endcan
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="space-y-6">
            <flux:card class="space-y-2" data-test="ticket-description">
                <flux:heading>{{ __('Description') }}</flux:heading>

                @if (filled($this->ticket->description))
                    <flux:text class="whitespace-pre-line">{{ $this->ticket->description }}</flux:text>
                @else
                    <flux:text variant="subtle" class="italic">{{ __('No description.') }}</flux:text>
                @endif

                <x-ticket-attachment-list :attachments="$this->ticket->attachments" />
            </flux:card>

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

            @can('reply', $this->ticket)
                <form wire:submit="reply" class="space-y-4">
                    <flux:textarea wire:model="replyForm.body" :label="$replyForm['isInternal'] ? __('Internal note') : __('Reply')" rows="5" required
                        :placeholder="$replyForm['isInternal'] ? __('Only people who work this ticket will see this note.') : __('Write a reply...')" />

                    <flux:card>
                        <x-ticket-attachments-input model="replyForm.attachments" :files="$replyForm['attachments']" />
                    </flux:card>

                    <div class="flex flex-wrap items-center justify-between gap-4">
                        @if ($this->canWork)
                            <flux:switch wire:model.live="replyForm.isInternal" :label="__('Internal note')" align="left" />
                        @else
                            <span></span>
                        @endif

                        <flux:button variant="primary" type="submit" data-test="send-reply-button">
                            {{ $replyForm['isInternal'] ? __('Add note') : __('Send reply') }}
                        </flux:button>
                    </div>
                </form>
            @endcan
        </div>

        <aside class="space-y-6">
            <flux:card class="space-y-4">
                <flux:heading>{{ __('Assignee') }}</flux:heading>

                @if ($this->ticket->assignee)
                    <flux:text>{{ $this->ticket->assignee->name }}</flux:text>
                @else
                    <flux:text variant="subtle">{{ __('Unassigned') }}</flux:text>
                @endif

                @can('claim', $this->ticket)
                    <flux:button size="sm" icon="hand-raised" wire:click="claim" data-test="claim-ticket-button">{{ __('Claim') }}</flux:button>
                @endcan

                @can('assign', $this->ticket)
                    <form wire:submit="assign" class="space-y-3">
                        <flux:select wire:model="assignForm.assignee" :label="__('Assign to')" size="sm">
                            <flux:select.option value="">{{ __('Nobody') }}</flux:select.option>
                            @foreach ($this->assignableUsers as $assignableUser)
                                <flux:select.option :value="$assignableUser->id" wire:key="assignee-option-{{ $assignableUser->id }}">{{ $assignableUser->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:button size="sm" type="submit" data-test="assign-ticket-button">{{ __('Assign') }}</flux:button>
                    </form>
                @endcan
            </flux:card>

            <flux:card class="space-y-4">
                <flux:heading>{{ __('Details') }}</flux:heading>

                @if ($this->canWork)
                    <form wire:submit="saveDetails" class="space-y-4">
                        <flux:select wire:model="detailsForm.status" :label="__('Status')" size="sm">
                            @foreach ($this->statuses as $statusOption)
                                <flux:select.option :value="$statusOption->id" wire:key="status-option-{{ $statusOption->id }}">{{ $statusOption->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="detailsForm.priority" :label="__('Priority')" size="sm">
                            @foreach ($this->priorities as $priorityOption)
                                <flux:select.option :value="$priorityOption->id" wire:key="priority-option-{{ $priorityOption->id }}">{{ $priorityOption->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="detailsForm.department" :label="__('Department')" size="sm">
                            @foreach ($this->departments as $departmentOption)
                                <flux:select.option :value="$departmentOption->id" wire:key="department-option-{{ $departmentOption->id }}">{{ $departmentOption->name }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:button size="sm" variant="primary" type="submit" data-test="save-details-button">{{ __('Save details') }}</flux:button>
                    </form>
                @else
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</dt>
                            <dd><flux:badge size="sm" :color="$this->ticket->status->color->value">{{ $this->ticket->status->name }}</flux:badge></dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Priority') }}</dt>
                            <dd><flux:badge size="sm" :color="$this->ticket->priority->color->value">{{ $this->ticket->priority->name }}</flux:badge></dd>
                        </div>
                        <div>
                            <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Department') }}</dt>
                            <dd>{{ $this->ticket->department->name }}</dd>
                        </div>
                    </dl>
                @endif

                @if ($this->ticket->closed_at)
                    <flux:text variant="subtle" class="text-xs">
                        {{ __('Closed :date', ['date' => $this->ticket->closed_at->diffForHumans()]) }}
                    </flux:text>
                @endif
            </flux:card>

            <flux:card class="space-y-4" data-test="ticket-info">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Created by') }}</dt>
                        <dd>{{ $this->ticket->requester->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Created At') }}</dt>
                        <dd title="{{ $this->ticket->created_at?->diffForHumans() }}">{{ $this->ticket->created_at?->toDayDateTimeString() }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Last updated') }}</dt>
                        <dd title="{{ $this->ticket->updated_at?->diffForHumans() }}">{{ $this->ticket->updated_at?->toDayDateTimeString() }}</dd>
                    </div>
                </dl>
            </flux:card>
        </aside>
    </div>
</section>
