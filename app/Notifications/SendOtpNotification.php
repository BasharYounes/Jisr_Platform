<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOtpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
   public function __construct(
    public string $code,
    public string $type = 'login'
) {}


public function via(object $notifiable): array
{
    return ['mail'];
}

public function toMail(object $notifiable): MailMessage
{
    $title = $this->type === 'password_reset'
        ? 'Reset Your Password'
        : 'Login OTP';

    return (new MailMessage)
        ->subject($title)
        ->greeting('Hello ' . $notifiable->name)
        ->line('Your OTP Code:')
        ->line('👉 ' . $this->code)
        ->line('This code expires in a few minutes.');
    }
}