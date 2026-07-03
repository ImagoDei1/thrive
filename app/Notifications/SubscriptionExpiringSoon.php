<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $daysRemaining) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dayLabel = $this->daysRemaining === 1 ? '1 day' : "{$this->daysRemaining} days";

        return (new MailMessage)
            ->subject("Your Thrive subscription expires in {$dayLabel}")
            ->greeting("Hi {$notifiable->name},")
            ->line("Your Thrive subscription will expire in **{$dayLabel}**.")
            ->line('To continue watching all your favourite channels without interruption, please renew via the Thrive mobile app.')
            ->action('Open Thrive App', 'https://thrive.app')
            ->line('Thank you for being a Thrive subscriber!');
    }
}
