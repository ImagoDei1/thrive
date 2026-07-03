<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HasConfirmModal;
use App\Models\SubscriberProfile;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Subscribers')]
class Subscribers extends Component
{
    use HasConfirmModal, Toast, WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public bool $showFilters = false;

    public array $headers = [
        ['key' => 'name',                'label' => 'Name'],
        ['key' => 'email',               'label' => 'Email'],
        ['key' => 'subscription_status', 'label' => 'Status'],
        ['key' => 'subscribed_at',       'label' => 'Subscribed'],
        ['key' => 'expires_at',          'label' => 'Expires'],
        ['key' => 'payments_count',      'label' => 'Payments'],
        ['key' => 'actions',             'label' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->search = '';
        $this->statusFilter = '';
    }

    public function confirmCancel(string $id): void
    {
        $profile = SubscriberProfile::with('user')->findOrFail($id);
        $this->askConfirm(
            'cancelSubscription',
            "Cancel subscription for {$profile->user->name}?",
            'Their status will be set to cancelled immediately.',
            $id, 'Cancel Subscription', 'btn-error'
        );
    }

    public function confirmReactivate(string $id): void
    {
        $profile = SubscriberProfile::with('user')->findOrFail($id);
        $this->askConfirm(
            'reactivate',
            "Reactivate subscription for {$profile->user->name}?",
            'Their status will be set to active.',
            $id, 'Reactivate', 'btn-success'
        );
    }

    public function cancelSubscription(string $id): void
    {
        $profile = SubscriberProfile::findOrFail($id);
        $profile->update(['subscription_status' => 'cancelled']);
        $profile->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);
        $this->success('Subscription cancelled.');
    }

    public function reactivate(string $id): void
    {
        $profile = SubscriberProfile::findOrFail($id);
        $profile->update(['subscription_status' => 'active']);
        $this->success('Subscription reactivated.');
    }

    public function render()
    {
        $subscribers = SubscriberProfile::with('user')
            ->withCount('subscriptions as payments_count')
            ->when($this->search, fn ($q) => $q->whereHas('user', fn ($q) => $q
                ->where('name', 'ilike', "%{$this->search}%")
                ->orWhere('email', 'ilike', "%{$this->search}%")))
            ->when($this->statusFilter, fn ($q) => $q->where('subscription_status', $this->statusFilter))
            ->latest()
            ->paginate(20);

        // Flatten user fields onto the profile rows for the table
        $subscribers->getCollection()->transform(function ($profile) {
            $profile->name  = $profile->user->name;
            $profile->email = $profile->user->email;
            return $profile;
        });

        $total   = SubscriberProfile::count();
        $active  = SubscriberProfile::where('subscription_status', 'active')->count();
        $expired = SubscriberProfile::where('subscription_status', 'expired')->count();

        return view('livewire.admin.subscribers', [
            'subscribers' => $subscribers,
            'total'       => $total,
            'active'      => $active,
            'expired'     => $expired,
        ])->layout('components.admin-layout');
    }
}
