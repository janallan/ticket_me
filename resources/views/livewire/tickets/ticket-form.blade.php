<section class="w-full">
    <x-slot:title>{{ $this->ticket ? __('Edit ticket :number', ['number' => $this->ticket->number]) : __('New ticket') }}</x-slot:title>

    <div class="mb-6">
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item :href="route('tickets.index')" wire:navigate>{{ __('Tickets') }}</flux:breadcrumbs.item>
            @if ($this->ticket)
                <flux:breadcrumbs.item :href="route('tickets.show', $this->ticket)" wire:navigate>{{ $this->ticket->number }}</flux:breadcrumbs.item>
                <flux:breadcrumbs.item>{{ __('Edit') }}</flux:breadcrumbs.item>
            @else
                <flux:breadcrumbs.item>{{ __('New ticket') }}</flux:breadcrumbs.item>
            @endif
        </flux:breadcrumbs>

        <flux:heading size="xl" level="1">{{ $this->ticket ? __('Edit ticket') : __('New ticket') }}</flux:heading>
        <flux:subheading size="lg">
            {{ $this->ticket
                ? __('The original values of anything you change are kept in the ticket\'s thread')
                : __('Describe what you need, and the department will pick it up') }}
        </flux:subheading>
    </div>

    <flux:separator variant="subtle" class="mb-6" />

    <form wire:submit="save" class="grid gap-8 lg:grid-cols-3">
        <aside class="lg:order-last lg:col-span-1">
            <flux:card class="space-y-4">
                <flux:heading>{{ __('Details') }}</flux:heading>

                @if ($this->canChooseStatus)
                    <flux:select wire:model="ticketForm.status" :label="__('Status')" size="sm" required>
                        @foreach ($this->statuses as $statusOption)
                            <flux:select.option :value="$statusOption->id" wire:key="status-option-{{ $statusOption->id }}">{{ $statusOption->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <flux:field>
                        <flux:label>{{ __('Status') }}</flux:label>

                        @if ($shownStatus = $this->ticket?->status ?? $this->statuses->firstWhere('is_default', true))
                            <div><flux:badge size="sm" :color="$shownStatus->color->value">{{ $shownStatus->name }}</flux:badge></div>
                        @endif

                        <flux:error name="ticketForm.status" />
                    </flux:field>
                @endif

                <flux:select wire:model="ticketForm.department" :label="__('Department')" :placeholder="__('Choose a department...')" size="sm" required>
                    @foreach ($this->departments as $departmentOption)
                        <flux:select.option :value="$departmentOption->id" wire:key="department-option-{{ $departmentOption->id }}">{{ $departmentOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="ticketForm.priority" :label="__('Priority')" size="sm" required>
                    @foreach ($this->priorities as $priorityOption)
                        <flux:select.option :value="$priorityOption->id" wire:key="priority-option-{{ $priorityOption->id }}">{{ $priorityOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </flux:card>
        </aside>

        <div class="min-w-0 space-y-6 lg:col-span-2">
            <flux:card class="space-y-6">
                <flux:input wire:model="ticketForm.subject" :label="__('Subject')" type="text" required autofocus />

                <flux:textarea wire:model="ticketForm.description" :label="__('Description')" rows="10"
                    :placeholder="__('What happened, what you expected, and anything that helps reproduce it.')" />
            </flux:card>

            <flux:card>
                @if ($this->ticket)
                    <flux:heading>{{ __('Attachments') }}</flux:heading>
                    <flux:text variant="subtle" class="text-sm">{{ __('To add files, reply to the ticket.') }}</flux:text>

                    <x-ticket-attachment-list :attachments="$this->ticket->attachments" />
                @else
                    <x-ticket-attachments-input model="ticketForm.attachments" :files="$ticketForm['attachments']" />
                @endif
            </flux:card>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit" data-test="save-ticket-button">
                    {{ $this->ticket ? __('Save') : __('Open ticket') }}
                </flux:button>

                <flux:button variant="ghost" :href="$this->ticket ? route('tickets.show', $this->ticket) : route('tickets.index')" wire:navigate>
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </div>
    </form>
</section>
