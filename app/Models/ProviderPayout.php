<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderPayout extends Model
{
    use HasUuids;
    protected $fillable = [
        'provider_id',
        'billing_period_id',
        'watch_seconds',
        'watch_hours',
        'share_percent',
        'gross_amount_mwk',
        'setup_fee_percentage',
        'setup_fee_mwk',
        'amount_mwk',
        'status',
        'payment_reference',
        'paid_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_id');
    }

    public function billingPeriod(): BelongsTo
    {
        return $this->belongsTo(BillingPeriod::class);
    }
}
