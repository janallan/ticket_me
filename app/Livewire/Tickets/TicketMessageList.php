<?php

namespace App\Livewire\Tickets;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The ticket's thread: replies, internal notes and change logs, newest last, with older entries
 * loaded on request. Refreshes itself when the ticket page announces a change.
 *
 * @property-read Ticket $ticket
 * @property-read Collection<int, TicketMessage> $messages
 * @property-read int $olderMessageCount
 * @property-read bool $canWork
 */
class TicketMessageList extends Component
{
    /**
     * How many thread entries are shown at first, and how many more each "Show older" click adds.
     */
    public const MESSAGES_PER_PAGE = 5;

    #[Locked]
    public int $ticketId;

    /**
     * How many of the newest thread entries are shown.
     */
    public int $shownMessages = self::MESSAGES_PER_PAGE;

    /**
     * Mount the component for the given ticket.
     */
    public function mount(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);

        $this->ticketId = $ticket->id;
    }

    #[Computed]
    public function ticket(): Ticket
    {
        return Ticket::with('department')->findOrFail($this->ticketId);
    }

    /**
     * Determine whether the current user works this ticket, and so may see internal notes.
     */
    #[Computed]
    public function canWork(): bool
    {
        return user()->can('work', $this->ticket);
    }

    /**
     * Get the newest thread entries the user may see, displayed oldest first. Internal notes are only
     * included for people who work the ticket. Each row also carries `thread_total`, the number of
     * entries the user may see in the whole thread, counted by a window function in the same query.
     *
     * @return Collection<int, TicketMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->visibleMessagesQuery()
            ->select('ticket_messages.*')
            ->selectRaw('count(*) over () as thread_total')
            ->with(['author', 'attachments'])
            ->reorder()
            ->latest()
            ->latest('id')
            ->take($this->shownMessages)
            ->get()
            ->reverse()
            ->values();
    }

    /**
     * Count the entries not shown yet because they are older than the ones on the page.
     */
    #[Computed]
    public function olderMessageCount(): int
    {
        $first = $this->messages->first();

        if ($first === null) {
            return 0;
        }

        return max(0, (int) $first->getAttribute('thread_total') - $this->messages->count());
    }

    /**
     * Show the next batch of older entries.
     */
    public function showOlderMessages(): void
    {
        $this->shownMessages += self::MESSAGES_PER_PAGE;

        unset($this->messages, $this->olderMessageCount);
    }

    /**
     * Show a new entry, keeping every entry that was already on the page.
     */
    #[On('ticket-message-added')]
    public function messageAdded(): void
    {
        $this->shownMessages++;

        $this->refresh();
    }

    /**
     * Reload after the ticket changed, which may have added a log entry or changed who can see notes.
     */
    #[On('ticket-updated')]
    public function ticketUpdated(): void
    {
        $this->shownMessages++;

        $this->refresh();
    }

    /**
     * Query the thread entries the user may see.
     *
     * @return Builder<TicketMessage>
     */
    private function visibleMessagesQuery(): Builder
    {
        return $this->ticket->messages()->getQuery()->unless($this->canWork, fn (Builder $query) => $query->public());
    }

    private function refresh(): void
    {
        unset($this->ticket, $this->canWork, $this->messages, $this->olderMessageCount);
    }

    public function render(): View
    {
        return view('livewire.tickets.ticket-message-list');
    }
}
