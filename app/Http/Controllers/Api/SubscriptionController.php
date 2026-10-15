<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SubscriptionController extends Controller
{
    // ---------------------------------------------------------------------------
    // GET /api/plans
    // ---------------------------------------------------------------------------
    public function plans(): JsonResponse
    {
        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('duration_days')
            ->orderBy('price_mwk')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => [
                'id'             => $plan->id,
                'name'           => $plan->name,
                'slug'           => $plan->slug,
                'description'    => $plan->description,
                'price'          => $plan->price_mwk,
                'currency'       => 'MWK',
                'interval'       => $plan->interval(),
                'duration_days'  => $plan->duration_days,
                'duration_label' => $plan->durationLabel(),
                'features'       => $plan->features ?? [],
            ]);

        return response()->json(['data' => $plans]);
    }

    // ---------------------------------------------------------------------------
    // GET /api/subscription
    // ---------------------------------------------------------------------------
    public function status(Request $request): JsonResponse
    {
        $profile = $request->user()->subscriberProfile()->first();

        if (! $profile) {
            return response()->json([
                'status'    => 'none',
                'plan'      => null,
                'starts_at' => null,
                'ends_at'   => null,
            ]);
        }

        $subscription = $profile->subscriptions()
            ->with('plan')
            ->orderByDesc('created_at')
            ->first();

        if (! $subscription) {
            return response()->json([
                'status'    => 'none',
                'plan'      => null,
                'starts_at' => null,
                'ends_at'   => null,
            ]);
        }

        return response()->json([
            'status'  => $subscription->status,
            'plan'    => $subscription->plan ? [
                'id'            => $subscription->plan->id,
                'name'          => $subscription->plan->name,
                'interval'      => $subscription->plan->interval(),
                'duration_days' => $subscription->plan->duration_days,
            ] : null,
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'ends_at'   => $subscription->ends_at?->toIso8601String(),
        ]);
    }

    // ---------------------------------------------------------------------------
    // POST /api/subscription/checkout
    // ---------------------------------------------------------------------------
    public function checkout(Request $request): JsonResponse
    {
        $request->validate([
            'plan_id' => 'required|uuid|exists:subscription_plans,id',
        ]);

        $plan = SubscriptionPlan::where('id', $request->plan_id)
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return response()->json(['message' => 'Plan not found or inactive'], 404);
        }

        // Ensure subscriber profile exists (defensive — should always exist post-register)
        $profile = $request->user()
            ->subscriberProfile()
            ->firstOrCreate(['user_id' => $request->user()->id]);

        // Generate a unique PayChangu reference
        $reference = 'THR-' . strtoupper(Str::random(12));

        // Create a pending subscription; PayChangu webhook will activate it
        Subscription::create([
            'subscriber_id' => $profile->id,
            'plan_id'       => $plan->id,
            'status'        => 'pending',
            'starts_at'     => null,
            'ends_at'       => null,
            'paychangu_ref' => $reference,
        ]);

        // TODO: Replace with real PayChangu checkout URL once integration is wired
        $checkoutUrl = 'https://paychangu.com/pay/' . $reference;

        return response()->json([
            'checkout_url' => $checkoutUrl,
            'reference'    => $reference,
        ]);
    }
}
