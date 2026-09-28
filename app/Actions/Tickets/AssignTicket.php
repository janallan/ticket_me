<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketAssignedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Assigns a ticket to someone who can work it, or unassigns it.
 */
class AssignTicket
{
    public function __construct(
        private NotifyTicketUsers $notify,
        private LogTicketChanges $logChanges,
    ) {}

    /**
     * Assign the ticket, or pass null to unassign it. The new assignee is notified unless they
     * assigned themselves. The original assignee is kept in a log entry.
     *
     * @throws ValidationException
     */
    public function __invoke(Ticket $ticket, User $actor, ?User $assignee, string $errorKey = 'assigneeId'): void
    {
        if ($assignee !== null && ! ($assignee->isActive() && $assignee->worksTicketsIn($ticket->department))) {
            throw ValidationException::withMessages([
                $errorKey => __(':name cannot work tickets in the :department department.', [
                    'name' => $assignee->name,
                    'department' => $ticket->department->name,
                ]),
            ]);
        }

        if ($ticket->assignee_id === $assignee?->id) {
            return;
        }

        $originalValues = [__('Assignee') => $ticket->assignee_id !== null ? $ticket->assignee->name : __('Unassigned')];

        $ticket->assignee()->associate($assignee);
        $ticket->last_activity_at = now();

        DB::transaction(function () use ($ticket, $actor, $originalValues) {
            $ticket->save();

            ($this->logChanges)($ticket, $actor, $originalValues);
        });

        if ($assignee !== null) {
            ($this->notify)([$assignee], $actor, new TicketAssignedNotification($ticket));
        }
    }
}
