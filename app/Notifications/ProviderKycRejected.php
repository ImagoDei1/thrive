<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProviderKycRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Action needed on your Thrive provider application')
            ->greeting("Hi {$notifiable->name},")
            ->line('We could not verify your provider application. Our reviewer left this note:')
            ->line("> {$this->reason}")
            ->line('Please update your details or documents and resubmit.')
            ->action('Update Application', route('provider.onboarding'));
    }
}
