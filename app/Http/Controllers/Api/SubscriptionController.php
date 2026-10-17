<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\SubscriptionPlan;
use App\Services\PayChangu\PayChanguClient;
use App\Services\PayChangu\PayChanguException;
use App\Services\SubscriptionPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

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
                'price'          => (float) $plan->price_mwk,
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

        // Every checkout creates a 'pending' row first, so an abandoned checkout
        // (or a declined one) must not hide a purchase that already settled. Only
        // fall back to pending/failed rows when nothing has ever been activated.
        $subscription = $profile->subscriptions()
            ->with('plan')
            ->whereNotIn('status', ['pending', 'failed'])
            ->orderByDesc('created_at')
            ->first()
            ?? $profile->subscriptions()
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
    // GET /api/payment-operators
    // ---------------------------------------------------------------------------
    public function operators(PayChanguClient $client): JsonResponse
    {
        try {
            $operators = $client->operators();
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Mobile money is temporarily unavailable. Please try again.'], 503);
        }

        return response()->json(['data' => $operators]);
    }

    // ---------------------------------------------------------------------------
    // POST /api/subscription/checkout
    // Sends a PIN prompt to the subscriber's phone; poll checkoutStatus() after.
    // ---------------------------------------------------------------------------
    public function checkout(Request $request, PayChanguClient $client, SubscriptionPaymentService $payments): JsonResponse
    {
        $request->validate([
            'plan_id'         => 'required|uuid|exists:subscription_plans,id',
            'phone'           => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (! preg_match('/^0\d{9}$/', SubscriptionPaymentService::normalizePhone((string) $value))) {
                    $fail('Enter a valid Malawi mobile number, e.g. 0991234567.');
                }
            }],
            'operator_ref_id' => 'required|string',
        ]);

        $plan = SubscriptionPlan::where('id', $request->plan_id)
            ->where('is_active', true)
            ->first();

        if (! $plan) {
            return response()->json(['message' => 'Plan not found or inactive'], 404);
        }

        try {
            $known = collect($client->operators())->pluck('ref_id')->contains($request->operator_ref_id);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Mobile money is temporarily unavailable. Please try again.'], 503);
        }

        if (! $known) {
            return response()->json([
                'message' => 'Unknown mobile money operator.',
                'errors'  => ['operator_ref_id' => ['Unknown mobile money operator.']],
            ], 422);
        }

        try {
            $payment = $payments->startCheckout($request->user(), $plan, $request->phone, $request->operator_ref_id);
        } catch (PayChanguException $e) {
            report($e);

            // 4xx from PayChangu is usually a bad number/operator pairing the user can fix.
            return $e->httpStatus >= 400 && $e->httpStatus < 500
                ? response()->json(['message' => 'The payment could not be started. Check the number and network, then try again.'], 422)
                : response()->json(['message' => 'Mobile money is temporarily unavailable. Please try again.'], 502);
        }

        return response()->json($this->checkoutPayload($payment), 202);
    }

    // ---------------------------------------------------------------------------
    // GET /api/subscription/checkout/{reference}
    // App polls this every few seconds after checkout until status != pending.
    // ---------------------------------------------------------------------------
    public function checkoutStatus(Request $request, string $reference, SubscriptionPaymentService $payments): JsonResponse
    {
        $payment = Payment::with('subscription')
            ->where('paychangu_tx_id', $reference)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // Fallback for a late or lost webhook: verify with PayChangu at most once
        // every 10s per payment, however often the app polls.
        if ($payment->status === 'pending' && Cache::add("paychangu:verify:{$payment->id}", true, 10)) {
            try {
                $payment = $payments->confirm($payment)->load('subscription');
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->json($this->checkoutPayload($payment));
    }

    /** @return array<string, mixed> */
    private function checkoutPayload(Payment $payment): array
    {
        $subscription = $payment->subscription;

        return [
            'reference' => $payment->paychangu_tx_id,
            'status'    => $payment->status, // pending | completed | failed
            'message'   => match ($payment->status) {
                'pending'   => 'Approve the payment on your phone by entering your mobile money PIN.',
                'completed' => 'Payment received. Your subscription is active.',
                default     => 'The payment was not completed.',
            },
            'amount'    => (float) $payment->amount,
            'currency'  => $payment->currency,
            'subscription' => $payment->status === 'completed' && $subscription ? [
                'status'    => $subscription->status,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'ends_at'   => $subscription->ends_at?->toIso8601String(),
            ] : null,
        ];
    }
}
