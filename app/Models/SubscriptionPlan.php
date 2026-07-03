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
        'is_active',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'features'  => 'array',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'plan_id');
    }
}
