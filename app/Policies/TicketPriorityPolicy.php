<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\TicketPriority;
use App\Models\User;

class TicketPriorityPolicy
{
    /**
     * Determine whether the user can view the list of priorities.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TicketPriorities->value);
    }

    /**
     * Determine whether the user can create priorities.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::TicketPriorities->value);
    }

    /**
     * Determine whether the user can edit the priority.
     */
    public function update(User $user, TicketPriority $ticketPriority): bool
    {
        return $user->can(Permission::TicketPriorities->value);
    }

    /**
     * Determine whether the user can delete the priority. The default priority and priorities in use cannot be deleted.
     */
    public function delete(User $user, TicketPriority $ticketPriority): bool
    {
        return $user->can(Permission::TicketPriorities->value)
            && ! $ticketPriority->is_default
            && $ticketPriority->tickets()->doesntExist();
    }
}
