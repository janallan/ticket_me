<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\TicketStatus;
use App\Models\User;

class TicketStatusPolicy
{
    /**
     * Determine whether the user can view the list of statuses.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TicketStatuses->value);
    }

    /**
     * Determine whether the user can create statuses.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::TicketStatuses->value);
    }

    /**
     * Determine whether the user can edit the status.
     */
    public function update(User $user, TicketStatus $ticketStatus): bool
    {
        return $user->can(Permission::TicketStatuses->value);
    }

    /**
     * Determine whether the user can delete the status. The default status and statuses in use cannot be deleted.
     */
    public function delete(User $user, TicketStatus $ticketStatus): bool
    {
        return $user->can(Permission::TicketStatuses->value)
            && ! $ticketStatus->is_default
            && $ticketStatus->tickets()->doesntExist();
    }
}
