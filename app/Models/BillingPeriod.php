<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPeriod extends Model
{
    use HasUuids;
    protected $fillable = [
        'period_label',
        'starts_at',
        'ends_at',
        'cutoff_date',
        'payout_date',
        'total_subscribers',
        'total_revenue',
        'platform_share',
        'provider_pool',
        'platform_percentage',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'   => 'date',
            'ends_at'     => 'date',
            'cutoff_date' => 'date',
            'payout_date' => 'date',
            'closed_at'   => 'datetime',
        ];
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(ProviderPayout::class);
    }
}
