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

    <div class="grid gap-8 lg:grid-cols-3">
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <flux:card class="space-y-2" data-test="ticket-description">
                <flux:heading>{{ __('Description') }}</flux:heading>

                @if (filled($this->ticket->description))
                    <flux:text class="whitespace-pre-line">{{ $this->ticket->description }}</flux:text>
                @else
                    <flux:text variant="subtle" class="italic">{{ __('No description.') }}</flux:text>
                @endif

                <x-ticket-attachment-list :attachments="$this->ticket->attachments" />
            </flux:card>

            <livewire:tickets.ticket-message-list :ticket="$this->ticket" :key="'ticket-messages-'.$ticketId" />

            @can('reply', $this->ticket)
                <flux:separator variant="subtle" />

                <livewire:tickets.ticket-message-form :ticket="$this->ticket" :key="'ticket-message-form-'.$ticketId" />
            @endcan
        </div>

        <aside class="space-y-6 lg:col-span-1">
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
