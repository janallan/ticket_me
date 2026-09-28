<?php

namespace App\Notifications;

use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells the assignee about an internal note. Internal notes are never sent to the requester.
 */
class TicketNoteAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance, sent once the surrounding transaction commits.
     */
    public function __construct(public Ticket $ticket, public TicketMessage $message)
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
            ->subject(__('[:number] Internal note: :subject', ['number' => $this->ticket->number, 'subject' => $this->ticket->subject]))
            ->line(__(':name added an internal note to ticket :number.', ['name' => $this->message->author->name, 'number' => $this->ticket->number]))
            ->line(Str::limit($this->message->body, 300))
            ->action(__('View ticket'), route('tickets.show', $this->ticket));
    }
}
