<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SubscriberProfile;
use App\Models\Subscription;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature   = 'subscriptions:expire';
    protected $description = 'Suspend subscribers whose grace period (expires_at + 3 days) has passed';

    public function handle(): int
    {
        // Grace period: 3 days after expires_at
        $cutoff = now()->subDays(3)->startOfDay();

        $expired = SubscriberProfile::where('subscription_status', 'active')
            ->where('expires_at', '<', $cutoff)
            ->get();

        foreach ($expired as $profile) {
            $profile->update(['subscription_status' => 'expired']);

            // Mark their active subscription as expired too
            Subscription::where('subscriber_id', $profile->id)
                ->where('status', 'active')
                ->update(['status' => 'expired']);
        }

        $count = $expired->count();

        if ($count > 0) {
            $this->info("Expired {$count} subscription(s) past grace period.");
        } else {
            $this->info('No subscriptions to expire.');
        }

        return self::SUCCESS;
    }
}
