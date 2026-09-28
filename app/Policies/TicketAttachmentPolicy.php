<?php

namespace App\Policies;

use App\Models\TicketAttachment;
use App\Models\User;

class TicketAttachmentPolicy
{
    /**
     * Determine whether the user can download the attachment. Files on internal notes are only
     * available to users who can work the ticket.
     */
    public function view(User $user, TicketAttachment $attachment): bool
    {
        return $attachment->isInternal()
            ? $user->can('work', $attachment->ticket)
            : $user->can('view', $attachment->ticket);
    }
}
