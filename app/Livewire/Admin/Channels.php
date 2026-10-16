<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HasConfirmModal;
use App\Models\Channel;
use App\Models\ReferenceValue;
use App\Services\ProviderVerificationService;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Channels')]
class Channels extends Component
{
    use HasConfirmModal, Toast, WithPagination;

    public string $search = '';
    public string $categoryFilter = '';
    public bool $showFilters = false;

    public array $headers = [
        ['key' => 'name',      'label' => 'Channel'],
        ['key' => 'provider',  'label' => 'Provider'],
        ['key' => 'category',  'label' => 'Category'],
        ['key' => 'verified',  'label' => 'Verification'],
        ['key' => 'is_live',   'label' => 'Live'],
        ['key' => 'is_active', 'label' => 'Status'],
        ['key' => 'actions',   'label' => ''],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function confirmToggle(string $id): void
    {
        $channel = Channel::findOrFail($id);
        $action  = $channel->is_active ? 'Deactivate' : 'Activate';
        $body    = $channel->is_active
            ? 'This channel will be hidden from subscribers.'
            : 'This channel will become visible to subscribers.';
        $class   = $channel->is_active ? 'btn-error' : 'btn-success';

        $this->askConfirm('toggleActive', "{$action} {$channel->name}?", $body, $id, $action, $class);
    }

    public function toggleActive(string $id): void
    {
        $channel = Channel::findOrFail($id);
        $channel->update(['is_active' => ! $channel->is_active]);
        $status = $channel->is_active ? 'activated' : 'deactivated';
        $this->success("{$channel->name} {$status}.");
    }

    public function confirmVerification(string $id): void
    {
        $channel = Channel::with('provider')->findOrFail($id);

        if ($channel->isVerified()) {
            $this->askConfirm('toggleVerification', "Remove verification from {$channel->name}?",
                'The provider will lose access to stream setup and live publishing will be refused.',
                $id, 'Unverify', 'btn-error');
            return;
        }

        if ($channel->provider->kyc_status !== 'approved') {
            $this->error("{$channel->provider->company_name} must pass KYC before this channel can be verified.");
            return;
        }

        $this->askConfirm('toggleVerification', "Verify {$channel->name}?",
            'The provider will be able to view stream credentials and go live.',
            $id, 'Verify', 'btn-success');
    }

    public function toggleVerification(string $id): void
    {
        $channel = Channel::with('provider')->findOrFail($id);
        $verification = app(ProviderVerificationService::class);

        if ($channel->isVerified()) {
            $verification->unverifyChannel($channel);
            $this->warning("{$channel->name} is no longer verified.");
        } elseif ($channel->provider->kyc_status === 'approved') {
            $verification->verifyChannel($channel, auth()->user());
            $this->success("{$channel->name} verified.");
        }
    }

    public function render()
    {
        $channels = Channel::with('provider.user')
            ->when($this->search, fn ($q) => $q->where('name', 'ilike', "%{$this->search}%"))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->withCount('contentItems')
            ->latest()
            ->paginate(15);

        $categories = collect(ReferenceValue::selectOptions(ReferenceValue::CHANNEL_CATEGORY))
            ->prepend(['id' => '', 'name' => 'All Categories']);

        return view('livewire.admin.channels', [
            'channels'   => $channels,
            'categories' => $categories,
        ])->layout('components.admin-layout');
    }
}
