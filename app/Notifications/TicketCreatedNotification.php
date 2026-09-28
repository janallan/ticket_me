<?php

namespace App\Notifications;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells department workers that a ticket was opened in their department.
 */
class TicketCreatedNotification extends Notification implements ShouldQueue
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
            ->subject(__('[:number] New ticket: :subject', ['number' => $this->ticket->number, 'subject' => $this->ticket->subject]))
            ->line(__(':name opened a ticket in :department.', ['name' => $this->ticket->requester->name, 'department' => $this->ticket->department->name]))
            ->lineIf(filled($this->ticket->description), Str::limit((string) $this->ticket->description, 300))
            ->action(__('View ticket'), route('tickets.show', $this->ticket));
    }
}
