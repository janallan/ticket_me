<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a user that a ticket was assigned to them.
 */
class TicketAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance, sent once the surrounding transaction commits.
     */
    public function __construct(public Ticket $ticket)
    {
        $this->afterCommit();
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('[:number] Assigned to you: :subject', ['number' => $this->ticket->number, 'subject' => $this->ticket->subject]))
            ->line(__('Ticket :number in :department is now assigned to you.', ['number' => $this->ticket->number, 'department' => $this->ticket->department->name]))
            ->action(__('View ticket'), route('tickets.show', $this->ticket));
    }
}
