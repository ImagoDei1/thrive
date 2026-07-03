<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SubscriberProfile;
use App\Notifications\SubscriptionExpiringSoon;
use Illuminate\Console\Command;

class NotifyExpiringSubscriptions extends Command
{
    protected $signature   = 'subscriptions:notify-expiring';
    protected $description = 'Email subscribers whose subscription expires in 7 or 3 days';

    public function handle(): int
    {
        $notifyAt = [7, 3];

        $count = 0;

        foreach ($notifyAt as $days) {
            $targetDate = now()->addDays($days)->toDateString();

            $profiles = SubscriberProfile::with('user')
                ->where('subscription_status', 'active')
                ->whereDate('expires_at', $targetDate)
                ->get();

            foreach ($profiles as $profile) {
                $profile->user->notify(new SubscriptionExpiringSoon($days));
                $count++;
            }
        }

        $this->info("Sent {$count} expiry notification(s).");

        return self::SUCCESS;
    }
}
