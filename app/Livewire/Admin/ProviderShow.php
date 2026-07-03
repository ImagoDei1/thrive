<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HasConfirmModal;
use App\Models\ProviderProfile;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

#[Title('Provider Profile')]
class ProviderShow extends Component
{
    use HasConfirmModal, Toast;

    public ProviderProfile $provider;

    public function confirmApprove(): void
    {
        $this->askConfirm('approve',
            "Approve {$this->provider->company_name}?",
            'Their channels will become visible to subscribers.',
            '', 'Approve', 'btn-success');
    }

    public function confirmReject(): void
    {
        $this->askConfirm('reject',
            "Revoke approval for {$this->provider->company_name}?",
            'Their channels will be hidden from subscribers.',
            '', 'Revoke', 'btn-error');
    }

    public function approve(): void
    {
        $this->provider->update(['is_approved' => true, 'onboarded_at' => now()]);
        $this->success("{$this->provider->company_name} approved.");
    }

    public function reject(): void
    {
        $this->provider->update(['is_approved' => false]);
        $this->warning("Approval revoked for {$this->provider->company_name}.");
    }

    public function render()
    {
        $this->provider->load(['user', 'channels', 'payouts.billingPeriod']);

        return view('livewire.admin.provider-show')
            ->layout('components.admin-layout');
    }
}
