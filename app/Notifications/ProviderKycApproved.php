<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProviderKycApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Your Thrive provider account is approved')
            ->greeting("Hi {$notifiable->name},")
            ->line('Your verification documents have been reviewed and your provider account is now approved.')
            ->line('Your channel is verified — you can now view your stream credentials and start uploading content.');

        $provider = $notifiable->providerProfile;

        if ($provider?->hasSetupFeeTerms()) {
            $from = \Carbon\Carbon::createFromFormat('!Y-m', $provider->setup_fee_starts_period)->format('F Y');
            $to   = \Carbon\Carbon::createFromFormat('!Y-m', $provider->setup_fee_ends_period)->format('F Y');

            $message->line("There is no upfront setup fee. Instead, from {$from} to {$to} your revenue share is reduced by "
                . rtrim(rtrim(number_format((float) $provider->setup_fee_percentage, 2), '0'), '.')
                . ' percentage points, which covers your setup.');
        }

        return $message
            ->action('Open Provider Portal', route('provider.dashboard'))
            ->line('Welcome to Thrive!');
    }
}
