<?php

declare(strict_types=1);

namespace App\Livewire\Subscriber;

use App\Models\SubscriberProfile;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('My Dashboard')]
class Dashboard extends Component
{
    public function render()
    {
        $user    = auth()->user();
        $profile = $user->subscriberProfile;

        $activeSubscription = null;
        $daysRemaining      = null;
        $expiryWarning      = false;

        if ($profile) {
            $activeSubscription = $profile->subscriptions()
                ->with('plan')
                ->where('status', 'active')
                ->where('starts_at', '<=', now()) // ignore stacked passes that haven't started yet
                ->latest('starts_at')
                ->first();

            if ($profile->expires_at) {
                $daysRemaining = (int) now()->diffInDays($profile->expires_at, false);
                // Monthly+: warn in the last 7 days. Weekly: last day. 24h pass: never.
                $warnWithinDays = match (true) {
                    ($activeSubscription?->plan->duration_days ?? 30) >= 30 => 8,
                    $activeSubscription->plan->duration_days >= 7           => 1,
                    default                                                 => 0,
                };
                $expiryWarning = $daysRemaining < $warnWithinDays && $profile->expires_at->isFuture();
            }
        }

        $recentPayments = $user->payments()
            ->with('subscription.plan')
            ->latest()
            ->limit(5)
            ->get();

        return view('livewire.subscriber.dashboard', [
            'profile'            => $profile,
            'activeSubscription' => $activeSubscription,
            'daysRemaining'      => $daysRemaining,
            'expiryWarning'      => $expiryWarning,
            'recentPayments'     => $recentPayments,
        ])->layout('components.subscriber-layout');
    }
}
