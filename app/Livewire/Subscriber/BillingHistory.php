<?php

declare(strict_types=1);

namespace App\Livewire\Subscriber;

use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Billing History')]
class BillingHistory extends Component
{
    use WithPagination;

    public string $statusFilter = '';

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $payments = auth()->user()->payments()
            ->with('subscription.plan')
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(15);

        return view('livewire.subscriber.billing-history', [
            'payments' => $payments,
        ])->layout('components.subscriber-layout');
    }
}
