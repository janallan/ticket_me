<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine whether the user can list tickets. Everyone sees at least the tickets they opened.
     */
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Determine whether the user can open tickets. No permission is needed.
     */
    public function create(User $user): bool
    {
        return $user->isActive();
    }

    /**
     * Determine whether the user can see the ticket and its public replies.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isActive()
            && ($ticket->isRequestedBy($user) || $user->worksTicketsIn($ticket->department));
    }

    /**
     * Determine whether the user can edit the subject and description: only the requester, and only
     * while the ticket is open. People who work the ticket change its details from the ticket page.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->isActive() && $ticket->isRequestedBy($user) && ! $ticket->isClosed();
    }

    /**
     * Determine whether the user can post a public reply.
     */
    public function reply(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    /**
     * Determine whether the user can work the ticket: see and add internal notes, and change its
     * status, priority and department.
     */
    public function work(User $user, Ticket $ticket): bool
    {
        return $user->isActive() && $user->worksTicketsIn($ticket->department);
    }

    /**
     * Determine whether the user can assign the ticket to themselves.
     */
    public function claim(User $user, Ticket $ticket): bool
    {
        return $ticket->assignee_id === null && $this->work($user, $ticket);
    }

    /**
     * Determine whether the user can assign the ticket to anyone who can work it.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->isActive() && $user->can(Permission::TicketsAll->value);
    }
}
