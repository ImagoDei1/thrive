<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasUuids;
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_mwk',
        'duration_days',
        'grace_days',
        'is_active',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'grace_days'    => 'integer',
            'is_active'     => 'boolean',
            'features'      => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }

    /**
     * Billing interval exposed to the Flutter app: day | week | month | year | custom.
     */
    public function interval(): string
    {
        return match ($this->duration_days) {
            1       => 'day',
            7       => 'week',
            30, 31  => 'month',
            365     => 'year',
            default => 'custom',
        };
    }

    /**
     * Human label for the plan length, e.g. "24 hours", "1 week", "30 days".
     */
    public function durationLabel(): string
    {
        return match ($this->interval()) {
            'day'   => '24 hours',
            'week'  => '1 week',
            'month' => '1 month',
            'year'  => '1 year',
            default => $this->duration_days . ' days',
        };
    }
}
