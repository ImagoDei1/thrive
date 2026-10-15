<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Channel;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Notifications\ProviderKycApproved;
use App\Notifications\ProviderKycRejected;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProviderVerificationService
{
    /**
     * Approving KYC approves the provider and verifies the channel(s) they
     * applied with, which unlocks stream credentials.
     */
    public function approve(ProviderProfile $provider, User $admin): void
    {
        if ($provider->kyc_status !== 'submitted') {
            throw new InvalidArgumentException('Only submitted KYC applications can be approved.');
        }

        DB::transaction(function () use ($provider, $admin): void {
            $provider->update([
                'kyc_status'           => 'approved',
                'kyc_reviewed_at'      => now(),
                'kyc_reviewed_by'      => $admin->id,
                'kyc_rejection_reason' => null,
                'is_approved'          => true,
                'onboarded_at'         => $provider->onboarded_at ?? now(),
            ]);

            $provider->channels()->whereNull('verified_at')->get()
                ->each(fn (Channel $channel) => $this->verifyChannel($channel, $admin));

            // No upfront setup fee — a reduced revenue share for the first months instead
            app(ProviderSetupFeeService::class)->assignDefaults($provider);
        });

        $provider->user->notify(new ProviderKycApproved());
    }

    public function reject(ProviderProfile $provider, User $admin, string $reason): void
    {
        if ($provider->kyc_status !== 'submitted') {
            throw new InvalidArgumentException('Only submitted KYC applications can be rejected.');
        }

        $provider->update([
            'kyc_status'           => 'rejected',
            'kyc_reviewed_at'      => now(),
            'kyc_reviewed_by'      => $admin->id,
            'kyc_rejection_reason' => $reason,
            'is_approved'          => false,
        ]);

        $provider->user->notify(new ProviderKycRejected($reason));
    }

    public function verifyChannel(Channel $channel, User $admin): void
    {
        $channel->update(['verified_at' => now(), 'verified_by' => $admin->id]);
    }

    public function unverifyChannel(Channel $channel): void
    {
        $channel->update(['verified_at' => null, 'verified_by' => null]);
    }
}
