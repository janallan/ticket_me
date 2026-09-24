<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $token) {}

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
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $expires = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject(__('Set your password for :app', ['app' => config('app.name')]))
            ->greeting(__('Welcome, :name!', ['name' => $notifiable->name]))
            ->line(__('An account has been created for you. Choose a password to start signing in.'))
            ->action(__('Set password'), $url)
            ->line(__('This link will expire in :count minutes. Ask an administrator to send a new one if it expires.', ['count' => $expires]));
    }
}
