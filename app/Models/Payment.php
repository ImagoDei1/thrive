<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;
    protected $fillable = [
        'user_id',
        'subscription_id',
        'billing_period_id',
        'amount',
        'currency',
        'paychangu_tx_id',
        'status',
        'payment_method',
        'phone_number',
        'gateway_response',
    ];

    protected function casts(): array
    {
        return [
            'gateway_response' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function billingPeriod(): BelongsTo
    {
        return $this->belongsTo(BillingPeriod::class);
    }

    /**
     * Assign this payment to the correct billing period based on payment date.
     * Called after a payment is marked completed.
     */
    public function assignToBillingPeriod(): void
    {
        $service = app(\App\Services\BillingPeriodService::class);
        $period  = $service->periodForPaymentDate(Carbon::parse($this->created_at));

        if ($period) {
            $this->update(['billing_period_id' => $period->id]);

            // Update period revenue snapshot
            $period->increment('total_revenue', $this->amount);
            $platform = round($this->amount * ($period->platform_percentage / 100), 2);
            $period->increment('platform_share', $platform);
            $period->increment('provider_pool', $this->amount - $platform);
        }
    }
}
