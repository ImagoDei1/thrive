<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Payment;
use App\Services\SubscriptionPaymentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Dispatched by the PayChangu webhook. Re-verifies the charge with PayChangu,
 * and keeps re-checking for a while if the charge is still pending.
 */
class ConfirmPayChanguPaymentJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    /** @var list<int> */
    public array $backoff = [10, 30, 60, 120, 300];

    public function __construct(public readonly string $paymentId) {}

    public function handle(SubscriptionPaymentService $payments): void
    {
        $payment = Payment::find($this->paymentId);

        if (! $payment) {
            return;
        }

        $payment = $payments->confirm($payment);

        if ($payment->status === 'pending' && $this->attempts() < $this->tries) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);
        }
    }
}
