<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\PayChangu\PayChanguClient;
use App\Services\PayChangu\PayChanguException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Subscriber plan purchases via PayChangu direct mobile-money charges.
 *
 * Flow: startCheckout() sends a PIN prompt to the subscriber's phone and leaves
 * a pending Subscription + Payment keyed by our charge_id. confirm() is called
 * from the webhook job and from the app's status poll; it never trusts the
 * webhook body and always re-verifies the charge with PayChangu before
 * activating access.
 */
class SubscriptionPaymentService
{
    private const SUCCESS_STATUSES = ['success', 'successful', 'completed'];

    private const FAILED_STATUSES = ['failed', 'cancelled', 'canceled', 'declined', 'expired', 'reversed'];

    public function __construct(
        private readonly PayChanguClient $client,
        private readonly SubscriptionActivationService $activation,
    ) {}

    /** @throws PayChanguException when PayChangu rejects the charge (the payment is marked failed). */
    public function startCheckout(User $user, SubscriptionPlan $plan, string $phone, string $operatorRefId): Payment
    {
        $profile  = $user->subscriberProfile()->firstOrCreate(['user_id' => $user->id]);
        $chargeId = 'THR-' . strtoupper(Str::random(16));
        $mobile   = self::normalizePhone($phone);

        $payment = DB::transaction(function () use ($profile, $plan, $user, $chargeId, $mobile) {
            $subscription = Subscription::create([
                'subscriber_id' => $profile->id,
                'plan_id'       => $plan->id,
                'status'        => 'pending',
                'paychangu_ref' => $chargeId,
            ]);

            return Payment::create([
                'user_id'         => $user->id,
                'subscription_id' => $subscription->id,
                'amount'          => $plan->price_mwk,
                'currency'        => 'MWK',
                'paychangu_tx_id' => $chargeId,
                'status'          => 'pending',
                'payment_method'  => 'mobile_money',
                'phone_number'    => $mobile,
            ]);
        });

        [$firstName, $lastName] = array_pad(explode(' ', trim((string) $user->name), 2), 2, null);

        try {
            $charge = $this->client->chargeMobileMoney($mobile, $operatorRefId, (float) $plan->price_mwk, $chargeId, [
                'email'      => $user->email,
                'first_name' => $firstName,
                'last_name'  => $lastName,
            ]);
        } catch (PayChanguException $e) {
            $this->markFailed($payment, ['initialize_error' => $e->body]);

            throw $e;
        }

        $payment->update(['gateway_response' => ['initialize' => $charge]]);

        return $payment;
    }

    /**
     * Re-verify a charge with PayChangu and settle the payment. Idempotent: a
     * payment that is already completed or failed is returned unchanged, so
     * duplicate webhooks and concurrent polls cannot activate access twice.
     */
    public function confirm(Payment $payment): Payment
    {
        if ($payment->status !== 'pending') {
            return $payment;
        }

        $charge = $this->client->verifyCharge($payment->paychangu_tx_id);
        $status = strtolower((string) ($charge['status'] ?? ''));

        if (in_array($status, self::FAILED_STATUSES, true)) {
            return $this->markFailed($payment, ['verify' => $charge]);
        }

        if (! in_array($status, self::SUCCESS_STATUSES, true)) {
            return $payment; // still waiting for the subscriber to enter their PIN
        }

        $currency = strtoupper((string) ($charge['currency'] ?? $payment->currency));
        $amount   = (float) ($charge['amount'] ?? 0);

        if ($currency !== $payment->currency || $amount < (float) $payment->amount) {
            Log::warning('PayChangu charge amount/currency mismatch', [
                'charge_id' => $payment->paychangu_tx_id,
                'expected'  => [$payment->amount, $payment->currency],
                'received'  => [$amount, $currency],
            ]);

            return $this->markFailed($payment, ['verify' => $charge, 'mismatch' => true]);
        }

        return DB::transaction(function () use ($payment, $charge) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'pending') {
                return $payment;
            }

            $payment->update([
                'status'           => 'completed',
                'gateway_response' => array_merge($payment->gateway_response ?? [], ['verify' => $charge]),
            ]);

            $this->activation->activate($payment->subscription);
            $payment->assignToBillingPeriod();

            return $payment->refresh();
        });
    }

    /**
     * PayChangu takes the local 10-digit form, e.g. 0991234567.
     * Accepts +265 991 234 567, 265991234567, 0991234567 or 991234567.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '265') && strlen($digits) === 12) {
            $digits = substr($digits, 3);
        }

        return strlen($digits) === 9 ? '0' . $digits : $digits;
    }

    /** @param  array<string, mixed>  $gateway */
    private function markFailed(Payment $payment, array $gateway): Payment
    {
        return DB::transaction(function () use ($payment, $gateway) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'pending') {
                return $payment;
            }

            $payment->update([
                'status'           => 'failed',
                'gateway_response' => array_merge($payment->gateway_response ?? [], $gateway),
            ]);
            $payment->subscription()->where('status', 'pending')->update(['status' => 'failed']);

            return $payment;
        });
    }
}
