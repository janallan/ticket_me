<?php

namespace App\Actions\Tickets;

use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * Sends a ticket notification to active users only, and never to the person who caused the event.
 */
class NotifyTicketUsers
{
    /**
     * Send the notification to the eligible recipients.
     *
     * @param  iterable<User|null>  $recipients
     */
    public function __invoke(iterable $recipients, User $actor, Notification $notification): void
    {
        $eligible = collect($recipients)
            ->filter(fn (?User $user) => $user !== null && $user->isActive() && ! $user->is($actor))
            ->unique('id')
            ->values();

        if ($eligible->isNotEmpty()) {
            NotificationFacade::send($eligible, $notification);
        }
    }
}
