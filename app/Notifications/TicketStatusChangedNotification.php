<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells the requester that their ticket's status changed.
 */
class TicketStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance, sent once the surrounding transaction commits.
     */
    public function __construct(public Ticket $ticket, public TicketStatus $previousStatus)
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
            ->subject(__('[:number] Status changed to :status: :subject', ['number' => $this->ticket->number, 'status' => $this->ticket->status->name, 'subject' => $this->ticket->subject]))
            ->line(__('The status of your ticket changed from :from to :to.', ['from' => $this->previousStatus->name, 'to' => $this->ticket->status->name]))
            ->action(__('View ticket'), route('tickets.show', $this->ticket));
    }
}
