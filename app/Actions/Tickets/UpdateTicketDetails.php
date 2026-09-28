<?php

namespace App\Actions\Tickets;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use App\Notifications\TicketStatusChangedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Changes a ticket's status, priority and department.
 */
class UpdateTicketDetails
{
    public function __construct(
        private NotifyTicketUsers $notify,
        private LogTicketChanges $logChanges,
    ) {}

    /**
     * Save the details. Moving between open and closed statuses sets or clears `closed_at`,
     * moving to another department drops an assignee who cannot work there, and the requester
     * is told about status changes. The original values are kept in a log entry, after any
     * `$otherOriginalValues` from changes the caller already made to the ticket.
     *
     * @param  array<string, string|null>  $otherOriginalValues
     */
    public function __invoke(
        Ticket $ticket,
        User $actor,
        TicketStatus $status,
        TicketPriority $priority,
        Department $department,
        array $otherOriginalValues = [],
    ): void {
        $previousStatus = $ticket->status;
        $statusChanged = $previousStatus->isNot($status);
        $originalValues = [...$otherOriginalValues, ...$this->originalValues($ticket, $status, $priority, $department)];

        $ticket->status()->associate($status);
        $ticket->priority()->associate($priority);
        $ticket->department()->associate($department);

        if ($statusChanged && $status->is_closed !== $previousStatus->is_closed) {
            $ticket->closed_at = $status->is_closed ? now() : null;
        }

        if ($ticket->assignee && ! $ticket->assignee->worksTicketsIn($department)) {
            $originalValues[__('Assignee')] = $ticket->assignee->name;
            $ticket->assignee()->dissociate();
        }

        if (! $ticket->isDirty()) {
            return;
        }

        $ticket->last_activity_at = now();

        DB::transaction(function () use ($ticket, $actor, $originalValues) {
            $ticket->save();

            ($this->logChanges)($ticket, $actor, $originalValues);
        });

        if ($statusChanged) {
            ($this->notify)([$ticket->requester], $actor, new TicketStatusChangedNotification($ticket, $previousStatus));
        }
    }

    /**
     * Get the original status, priority and department, for the ones that are changing.
     *
     * @return array<string, string>
     */
    private function originalValues(Ticket $ticket, TicketStatus $status, TicketPriority $priority, Department $department): array
    {
        return array_filter([
            __('Status') => $ticket->status->isNot($status) ? $ticket->status->name : null,
            __('Priority') => $ticket->priority->isNot($priority) ? $ticket->priority->name : null,
            __('Department') => $ticket->department->isNot($department) ? $ticket->department->name : null,
        ], fn (?string $value) => $value !== null);
    }
}
