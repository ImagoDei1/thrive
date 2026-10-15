<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SubscriberProfile;
use App\Models\Subscription;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature   = 'subscriptions:expire';
    protected $description = 'Expire subscriptions whose ends_at + plan grace period has passed';

    public function handle(): int
    {
        // Grace is per plan: monthly keeps a few days, 24h / weekly passes get none.
        $expired = Subscription::with('plan')
            ->where('status', 'active')
            ->where('ends_at', '<', now())
            ->get()
            ->filter(fn (Subscription $sub) => $sub->ends_at->copy()->addDays($sub->plan->grace_days)->isPast());

        Subscription::whereIn('id', $expired->pluck('id'))->update(['status' => 'expired']);

        // Only flip the profile once nothing else (e.g. a stacked pass) is still running.
        SubscriberProfile::whereIn('id', $expired->pluck('subscriber_id')->unique())
            ->where('subscription_status', 'active')
            ->whereDoesntHave('subscriptions', fn ($q) => $q->where('status', 'active'))
            ->update(['subscription_status' => 'expired']);

        $count = $expired->count();

        if ($count > 0) {
            $this->info("Expired {$count} subscription(s) past grace period.");
        } else {
            $this->info('No subscriptions to expire.');
        }

        return self::SUCCESS;
    }
}
