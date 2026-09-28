<?php

namespace App\Actions\Tickets;

use App\Enums\TicketMessageType;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketStatus;
use App\Models\User;
use App\Notifications\TicketNoteAddedNotification;
use App\Notifications\TicketRepliedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Adds a public reply or an internal note to a ticket.
 */
class AddTicketMessage
{
    public function __construct(
        private StoreTicketAttachments $storeAttachments,
        private NotifyTicketUsers $notify,
        private LogTicketChanges $logChanges,
    ) {}

    /**
     * Add the message with its attachments and record the activity. A public reply from the
     * requester reopens a closed ticket in the default status.
     *
     * @param  array<int, UploadedFile>  $attachments
     */
    public function __invoke(Ticket $ticket, User $author, string $body, bool $isInternal = false, array $attachments = []): TicketMessage
    {
        $message = DB::transaction(function () use ($ticket, $author, $body, $isInternal, $attachments) {
            $message = $ticket->messages()->create([
                'user_id' => $author->id,
                'type' => TicketMessageType::Message,
                'body' => $body,
                'is_internal' => $isInternal,
            ]);

            ($this->storeAttachments)($ticket, $message, $attachments);

            $ticket->last_activity_at = now();

            $originalStatus = $ticket->status;

            if (! $isInternal && $ticket->isRequestedBy($author) && $ticket->isClosed()) {
                $this->reopen($ticket);
            }

            $ticket->save();

            if ($ticket->wasChanged('ticket_status_id')) {
                ($this->logChanges)($ticket, $author, [__('Status') => $originalStatus->name]);
            }

            return $message;
        });

        $this->sendNotifications($ticket, $message, $author);

        return $message;
    }

    /**
     * Move the ticket back to the default status.
     */
    private function reopen(Ticket $ticket): void
    {
        $status = TicketStatus::findDefault();

        if ($status === null || $status->is_closed) {
            return;
        }

        $ticket->status()->associate($status);
        $ticket->closed_at = null;
    }

    /**
     * Notify the requester and the assignee of a public reply (or the department's workers when
     * nobody is assigned), and the assignee of an internal note.
     */
    private function sendNotifications(Ticket $ticket, TicketMessage $message, User $author): void
    {
        if ($message->is_internal) {
            ($this->notify)([$ticket->assignee], $author, new TicketNoteAddedNotification($ticket, $message));

            return;
        }

        $workers = $ticket->assignee ? [$ticket->assignee] : $ticket->department->ticketWorkers()->all();

        ($this->notify)([$ticket->requester, ...$workers], $author, new TicketRepliedNotification($ticket, $message));
    }
}
