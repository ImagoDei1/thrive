<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HasConfirmModal;
use App\Models\ProviderProfile;
use App\Services\ProviderSetupFeeService;
use App\Services\ProviderVerificationService;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

#[Title('Provider Profile')]
class ProviderShow extends Component
{
    use HasConfirmModal, Toast;

    public ProviderProfile $provider;

    public bool $showRejectForm = false;
    public string $rejectionReason = '';

    public bool $showSetupFeeForm = false;
    public string $setupFeePercentage = '';
    public string $setupFeeStartsPeriod = '';
    public int $setupFeeMonths = 0;

    public function confirmApprove(): void
    {
        $this->askConfirm('approve',
            "Approve KYC for {$this->provider->company_name}?",
            'The provider will be approved and their channel verified, which issues stream credentials. '
                . 'Default setup-fee terms apply ('.config('revenue.setup_fee.percentage').'% for '
                . config('revenue.setup_fee.months').' months) — you can adjust them afterwards.',
            '', 'Approve', 'btn-success');
    }

    public function confirmRevoke(): void
    {
        $this->askConfirm('revoke',
            "Revoke approval for {$this->provider->company_name}?",
            'Their channels will be hidden from subscribers and they will lose portal access.',
            '', 'Revoke', 'btn-error');
    }

    public function approve(): void
    {
        if (! $this->provider->kycAwaitingReview()) {
            $this->error('This provider has not submitted KYC for review.');
            return;
        }

        $this->verification()->approve($this->provider, auth()->user());
        $this->success("{$this->provider->company_name} approved and channel verified.");
    }

    public function openReject(): void
    {
        $this->reset('rejectionReason');
        $this->resetValidation();
        $this->showRejectForm = true;
    }

    public function reject(): void
    {
        $this->validate(['rejectionReason' => ['required', 'string', 'min:10', 'max:1000']]);

        if (! $this->provider->kycAwaitingReview()) {
            $this->error('This provider has not submitted KYC for review.');
            return;
        }

        $this->verification()->reject($this->provider, auth()->user(), $this->rejectionReason);
        $this->showRejectForm = false;
        $this->warning("KYC rejected for {$this->provider->company_name}. They have been asked to resubmit.");
    }

    public function revoke(): void
    {
        $this->provider->update(['is_approved' => false]);
        $this->warning("Approval revoked for {$this->provider->company_name}.");
    }

    public function confirmReinstate(): void
    {
        $this->askConfirm('reinstate',
            "Reinstate {$this->provider->company_name}?",
            'Their KYC was approved previously. Portal access and channel visibility will be restored.',
            '', 'Reinstate', 'btn-success');
    }

    public function reinstate(): void
    {
        if ($this->provider->kyc_status !== 'approved') {
            $this->error('Only providers with approved KYC can be reinstated.');
            return;
        }

        $this->provider->update(['is_approved' => true]);
        $this->success("{$this->provider->company_name} reinstated.");
    }

    public function toggleChannelVerification(string $channelId): void
    {
        $channel = $this->provider->channels()->findOrFail($channelId);

        if ($channel->isVerified()) {
            $this->verification()->unverifyChannel($channel);
            $this->warning("{$channel->name} is no longer verified.");
        } else {
            $this->verification()->verifyChannel($channel, auth()->user());
            $this->success("{$channel->name} verified.");
        }
    }

    public function openSetupFee(): void
    {
        $this->setupFeePercentage   = $this->provider->hasSetupFeeTerms()
            ? (string) (float) $this->provider->setup_fee_percentage
            : (string) config('revenue.setup_fee.percentage');
        $this->setupFeeStartsPeriod = $this->provider->setup_fee_starts_period
            ?? ($this->provider->onboarded_at ?? now())->format('Y-m');
        $this->setupFeeMonths       = $this->provider->hasSetupFeeTerms()
            ? $this->provider->setupFeeMonths()
            : (int) config('revenue.setup_fee.months');
        $this->resetValidation();
        $this->showSetupFeeForm = true;
    }

    public function saveSetupFee(): void
    {
        $this->validate([
            'setupFeeMonths'       => ['required', 'integer', 'min:0', 'max:' . config('revenue.setup_fee.max_months')],
            'setupFeePercentage'   => ['exclude_if:setupFeeMonths,0', 'required', 'numeric', 'gt:0', 'max:' . config('revenue.setup_fee.max_percentage')],
            'setupFeeStartsPeriod' => ['exclude_if:setupFeeMonths,0', 'required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ], [
            'setupFeeStartsPeriod.regex' => 'Use a billing period like ' . now()->format('Y-m') . '.',
        ]);

        app(ProviderSetupFeeService::class)->assign(
            $this->provider,
            (float) $this->setupFeePercentage,
            $this->setupFeeStartsPeriod,
            $this->setupFeeMonths,
        );

        $this->showSetupFeeForm = false;
        $this->success('Setup-fee terms saved. Recalculate any billing periods that are already calculated.');
    }

    private function verification(): ProviderVerificationService
    {
        return app(ProviderVerificationService::class);
    }

    public function render()
    {
        $this->provider->load(['user', 'channels', 'payouts.billingPeriod', 'kycDocuments', 'kycReviewer']);

        return view('livewire.admin.provider-show', [
            'organisationTypes' => ProviderProfile::ORGANISATION_TYPES,
            'idTypes'           => ProviderProfile::ID_TYPES,
        ])->layout('components.admin-layout');
    }
}
