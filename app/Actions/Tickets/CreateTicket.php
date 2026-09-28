<?php

namespace App\Actions\Tickets;

use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketPriority;
use App\Models\TicketStatus;
use App\Models\User;
use App\Notifications\TicketCreatedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Opens a ticket with its description and attachments.
 */
class CreateTicket
{
    public function __construct(
        private StoreTicketAttachments $storeAttachments,
        private NotifyTicketUsers $notify,
    ) {}

    /**
     * Create the ticket in the chosen or default status, with the chosen or default priority, and
     * notify the department's workers.
     *
     * @param  array<int, UploadedFile>  $attachments
     *
     * @throws ValidationException
     */
    public function __invoke(
        User $requester,
        Department $department,
        string $subject,
        ?string $description,
        ?TicketPriority $priority = null,
        array $attachments = [],
        ?TicketStatus $status = null,
    ): Ticket {
        $status ??= TicketStatus::findDefault();
        $priority ??= TicketPriority::findDefault();

        if ($status === null || $priority === null) {
            throw ValidationException::withMessages([
                'subject' => __('Tickets cannot be opened until a default status and priority are set.'),
            ]);
        }

        $ticket = DB::transaction(function () use ($requester, $department, $subject, $description, $status, $priority, $attachments) {
            $ticket = Ticket::create([
                'subject' => $subject,
                'description' => $description,
                'department_id' => $department->id,
                'ticket_status_id' => $status->id,
                'ticket_priority_id' => $priority->id,
                'requester_id' => $requester->id,
                'closed_at' => $status->is_closed ? now() : null,
                'last_activity_at' => now(),
            ]);

            ($this->storeAttachments)($ticket, null, $attachments);

            return $ticket;
        });

        ($this->notify)($department->ticketWorkers(), $requester, new TicketCreatedNotification($ticket));

        return $ticket;
    }
}
