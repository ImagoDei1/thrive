<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HasConfirmModal;
use App\Models\ProviderProfile;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Providers')]
class Providers extends Component
{
    use HasConfirmModal, Toast, WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public bool $showFilters = false;

    public array $headers = [
        ['key' => 'company_name',     'label' => 'Company'],
        ['key' => 'email',            'label' => 'Email'],
        ['key' => 'kyc_status',       'label' => 'KYC'],
        ['key' => 'setup_fee',        'label' => 'Setup Fee'],
        ['key' => 'is_approved',      'label' => 'Status'],
        ['key' => 'actions',          'label' => ''],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function confirmReject(string $id): void
    {
        $provider = ProviderProfile::findOrFail($id);
        $this->askConfirm('reject', "Revoke approval for {$provider->company_name}?",
            'Their channels will be hidden from subscribers.',
            $id, 'Revoke', 'btn-error');
    }

    public function reject(string $id): void
    {
        $provider = ProviderProfile::findOrFail($id);
        $provider->update(['is_approved' => false]);
        $this->warning("{$provider->company_name} approval revoked.");
    }

    public function render()
    {
        $providers = ProviderProfile::with(['user', 'channels'])
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('company_name', 'ilike', "%{$this->search}%")
                ->orWhereHas('user', fn ($q) => $q->where('email', 'ilike', "%{$this->search}%"))))
            ->when($this->statusFilter !== '', fn ($q) => match ($this->statusFilter) {
                'kyc_review' => $q->where('kyc_status', 'submitted'),
                default      => $q->where('is_approved', $this->statusFilter === 'approved'),
            })
            ->withCount('channels')
            ->latest()
            ->paginate(15);

        return view('livewire.admin.providers', [
            'providers' => $providers,
        ])->layout('components.admin-layout');
    }
}
