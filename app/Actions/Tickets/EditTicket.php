<?php

namespace App\Actions\Tickets;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;

/**
 * Changes a ticket's subject, description, status, priority and department in one go, keeping the original
 * values in a single log entry.
 */
class EditTicket
{
    public function __construct(private UpdateTicketDetails $updateDetails) {}

    /**
     * Save the edit. Status and department changes follow the same rules as the details panel:
     * `closed_at` is kept in step, an assignee who cannot work in the new department is dropped,
     * and the requester is told about a status change. Nothing is saved when nothing changed.
     */
    public function __invoke(
        Ticket $ticket,
        User $editor,
        string $subject,
        ?string $description,
        TicketStatus $status,
        TicketPriority $priority,
        Department $department,
    ): void {
        $originalValues = [];

        if ($ticket->subject !== $subject) {
            $originalValues[__('Subject')] = $ticket->subject;
        }

        if ($ticket->description !== $description) {
            $originalValues[__('Description')] = $ticket->description;
        }

        $ticket->subject = $subject;
        $ticket->description = $description;

        ($this->updateDetails)($ticket, $editor, $status, $priority, $department, $originalValues);
    }
}
