<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriberProfile extends Model
{
    use HasUuids;
    protected $fillable = [
        'user_id',
        'subscription_status',
        'subscribed_at',
        'expires_at',
        'paychangu_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
            'expires_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'subscriber_id');
    }
}
