<?php

namespace App\Livewire\Tickets;

use App\Actions\Tickets\AddTicketMessage;
use App\Actions\Tickets\StoreTicketAttachments;
use App\Models\Ticket;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The reply box under a ticket's thread. Posts a public reply, or an internal note for people who work
 * the ticket, then tells the rest of the page with a `ticket-message-added` event.
 *
 * @property-read Ticket $ticket
 * @property-read bool $canWork
 */
class TicketMessageForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $ticketId;

    /**
     * The reply box, bound in the view as `replyForm.<field>`. `isInternal` turns the reply into an
     * internal note, which only people who work the ticket may post.
     *
     * @var array{body: string, isInternal: bool, attachments: array<int, UploadedFile>}
     */
    public array $replyForm = [
        'body' => '',
        'isInternal' => false,
        'attachments' => [],
    ];

    /**
     * Mount the component for the given ticket.
     */
    public function mount(Ticket $ticket): void
    {
        $this->authorize('reply', $ticket);

        $this->ticketId = $ticket->id;
    }

    #[Computed]
    public function ticket(): Ticket
    {
        return Ticket::with(['department', 'status'])->findOrFail($this->ticketId);
    }

    /**
     * Determine whether the current user may post internal notes on this ticket.
     */
    #[Computed]
    public function canWork(): bool
    {
        return user()->can('work', $this->ticket);
    }

    /**
     * Remove a file from the pending attachments.
     */
    public function removeAttachment(int $index): void
    {
        unset($this->replyForm['attachments'][$index]);

        $this->replyForm['attachments'] = array_values($this->replyForm['attachments']);
    }

    /**
     * Post a public reply, or an internal note for people who work the ticket.
     */
    public function reply(AddTicketMessage $addTicketMessage): void
    {
        $ticket = $this->ticket;

        $this->authorize('reply', $ticket);

        if ($this->replyForm['isInternal']) {
            $this->authorize('work', $ticket);
        }

        $validated = $this->validate([
            'replyForm.body' => ['required', 'string', 'max:20000'],
            'replyForm.isInternal' => ['boolean'],
            ...StoreTicketAttachments::rules('replyForm.attachments'),
        ], attributes: [
            'replyForm.body' => __('message'),
            'replyForm.isInternal' => __('internal note'),
            'replyForm.attachments' => __('attachments'),
            'replyForm.attachments.*' => __('attachment'),
        ])['replyForm'];

        $addTicketMessage($ticket, user(), $validated['body'], $validated['isInternal'], $this->replyForm['attachments']);

        $this->reset('replyForm');
        unset($this->ticket, $this->canWork);

        $this->dispatch('ticket-message-added');

        Flux::toast(variant: 'success', text: $validated['isInternal'] ? __('Internal note added.') : __('Reply sent.'));
    }

    public function render(): View
    {
        return view('livewire.tickets.ticket-message-form');
    }
}
