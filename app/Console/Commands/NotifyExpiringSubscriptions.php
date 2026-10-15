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

            // profile.expires_at is the end of the subscriber's latest (stacked) access window.
            $profiles = SubscriberProfile::with('user')
                ->where('subscription_status', 'active')
                ->whereDate('expires_at', $targetDate)
                ->get();

            foreach ($profiles as $profile) {
                $plan = $profile->subscriptions()
                    ->with('plan')
                    ->where('status', 'active')
                    ->latest('ends_at')
                    ->first()
                    ?->plan;

                // A reminder only makes sense when it falls inside the plan:
                // no "7 days left" for a 1-week pass, none at all for a 24h pass.
                if (! $plan || $plan->duration_days <= $days) {
                    continue;
                }

                $profile->user->notify(new SubscriptionExpiringSoon($days));
                $count++;
            }
        }

        $this->info("Sent {$count} expiry notification(s).");

        return self::SUCCESS;
    }
}
