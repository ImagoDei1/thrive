<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\ProvisionBunnyLibraryJob;
use Carbon\Carbon;
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
        'setup_fee_percentage',
        'setup_fee_starts_period',
        'setup_fee_ends_period',
        'bank_account',
        'mobile_money_number',
        'is_approved',
        'onboarded_at',
        'bunny_library_id',
        'bunny_library_api_key',
        'bunny_token_key',
        'bunny_cdn_hostname',
        'kyc_status',
        'organisation_type',
        'registration_number',
        'physical_address',
        'district',
        'id_type',
        'id_number',
        'kyc_submitted_at',
        'kyc_reviewed_at',
        'kyc_reviewed_by',
        'kyc_rejection_reason',
    ];

    public const ORGANISATION_TYPES = [
        'church'      => 'Church / Ministry',
        'broadcaster' => 'Broadcaster / Media house',
        'ngo'         => 'NGO / Non-profit',
        'school'      => 'School / Education',
        'business'    => 'Registered business',
        'individual'  => 'Individual creator',
    ];

    public const ID_TYPES = [
        'national_id'     => 'National ID',
        'passport'        => 'Passport',
        'drivers_licence' => "Driver's licence",
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

    public function isIndividual(): bool
    {
        return $this->organisation_type === 'individual';
    }

    /** @return list<string> */
    public function requiredKycDocuments(): array
    {
        return $this->isIndividual()
            ? ['id_front', 'proof_of_address']
            : ['id_front', 'registration_certificate', 'proof_of_address'];
    }

    /**
     * Setup-fee percentage points withheld for a "YYYY-MM" billing period (0 outside the term).
     */
    public function setupFeePercentageFor(string $periodLabel): float
    {
        if (! $this->setup_fee_starts_period || (float) $this->setup_fee_percentage <= 0) {
            return 0.0;
        }

        // "YYYY-MM" labels compare correctly as strings
        return $periodLabel >= $this->setup_fee_starts_period && $periodLabel <= $this->setup_fee_ends_period
            ? (float) $this->setup_fee_percentage
            : 0.0;
    }

    public function hasSetupFeeTerms(): bool
    {
        return $this->setup_fee_starts_period !== null && (float) $this->setup_fee_percentage > 0;
    }

    public function setupFeeMonths(): int
    {
        if (! $this->hasSetupFeeTerms()) {
            return 0;
        }

        return (int) Carbon::createFromFormat('!Y-m', $this->setup_fee_starts_period)
            ->diffInMonths(Carbon::createFromFormat('!Y-m', $this->setup_fee_ends_period)) + 1;
    }

    /** active | upcoming | completed | none */
    public function setupFeeStatus(?string $periodLabel = null): string
    {
        $periodLabel ??= now()->format('Y-m');

        return match (true) {
            ! $this->hasSetupFeeTerms()                       => 'none',
            $periodLabel < $this->setup_fee_starts_period     => 'upcoming',
            $periodLabel > $this->setup_fee_ends_period       => 'completed',
            default                                           => 'active',
        };
    }

    public function kycAwaitingReview(): bool
    {
        return $this->kyc_status === 'submitted';
    }

    /** Providers may edit KYC details until they are submitted for review. */
    public function kycEditable(): bool
    {
        return in_array($this->kyc_status, ['not_started', 'rejected'], true);
    }

    protected function casts(): array
    {
        return [
            'is_approved'  => 'boolean',
            'setup_fee_percentage' => 'decimal:2',
            'onboarded_at' => 'datetime',
            'kyc_submitted_at' => 'datetime',
            'kyc_reviewed_at'  => 'datetime',
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

    public function kycDocuments(): HasMany
    {
        return $this->hasMany(ProviderKycDocument::class, 'provider_id');
    }

    public function kycReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kyc_reviewed_by');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(ProviderPayout::class, 'provider_id');
    }
}
