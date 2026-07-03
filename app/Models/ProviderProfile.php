<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderProfile extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_name',
        'contact_person',
        'contact_phone',
        'description',
        'logo_url',
        'setup_fee_status',
        'setup_fee_amount',
        'bank_account',
        'mobile_money_number',
        'is_approved',
        'onboarded_at',
    ];

    protected function casts(): array
    {
        return [
            'is_approved'  => 'boolean',
            'onboarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class, 'provider_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(ProviderPayout::class, 'provider_id');
    }
}
