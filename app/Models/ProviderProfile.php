<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\ProvisionBunnyLibraryJob;
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
        'bunny_library_id',
        'bunny_library_api_key',
        'bunny_token_key',
        'bunny_cdn_hostname',
    ];

    protected $hidden = [
        'bunny_library_api_key',
        'bunny_token_key',
    ];

    protected static function booted(): void
    {
        // Every approved provider gets their own Bunny Stream library.
        static::saved(function (ProviderProfile $provider): void {
            if ($provider->is_approved && $provider->bunny_library_id === null && $provider->wasChanged('is_approved')) {
                ProvisionBunnyLibraryJob::dispatch($provider->id);
            }
        });
    }

    public function hasBunnyLibrary(): bool
    {
        return $this->bunny_library_id !== null && $this->bunny_library_api_key !== null;
    }

    protected function casts(): array
    {
        return [
            'is_approved'  => 'boolean',
            'onboarded_at' => 'datetime',
            'bunny_library_api_key' => 'encrypted',
            'bunny_token_key'       => 'encrypted',
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
