<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SubscriberProfile;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;

/**
 * Turns a paid (pending) subscription into an active one.
 *
 * Call this from the PayChangu webhook once the payment is confirmed. The access
 * window is derived from the plan's duration_days, so 24h, 1-week and 1-month
 * packages all go through the same path.
 *
 * Purchases stack: buying a pass while another is still running starts the new
 * one when the current access ends, so no paid time is lost.
 */
class SubscriptionActivationService
{
    public function activate(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            // Lock the profile so two concurrent webhooks for the same subscriber
            // cannot both compute the same start time.
            $profile = SubscriberProfile::whereKey($subscription->subscriber_id)
                ->lockForUpdate()
                ->firstOrFail();

            $subscription = Subscription::whereKey($subscription->id)
                ->lockForUpdate()
                ->with('plan')
                ->firstOrFail();

            if ($subscription->status === 'active') {
                return $subscription;
            }

            $currentAccessEndsAt = $profile->subscriptions()
                ->where('status', 'active')
                ->where('ends_at', '>', now())
                ->max('ends_at');

            $startsAt = $currentAccessEndsAt ? now()->parse($currentAccessEndsAt) : now();
            $endsAt   = $startsAt->copy()->addDays($subscription->plan->duration_days);

            $subscription->update([
                'status'    => 'active',
                'starts_at' => $startsAt,
                'ends_at'   => $endsAt,
            ]);

            $profile->update([
                'subscription_status' => 'active',
                'subscribed_at'       => $profile->subscribed_at ?? now(),
                'expires_at'          => $endsAt,
            ]);

            return $subscription;
        });
    }
}
