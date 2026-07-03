<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HasConfirmModal;
use App\Models\BillingPeriod;
use App\Models\ProviderPayout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Payouts')]
class Payouts extends Component
{
    use HasConfirmModal, Toast, WithPagination;

    #[Url]
    public string $period = '';

    public string $statusFilter = '';
    public bool $showFilters = false;

    public function clear(): void
    {
        $this->period = '';
        $this->statusFilter = '';
    }

    public array $headers = [
        ['key' => 'provider',      'label' => 'Provider'],
        ['key' => 'period_label',  'label' => 'Period'],
        ['key' => 'watch_hours',   'label' => 'Watch Hours'],
        ['key' => 'share_percent', 'label' => 'Share %'],
        ['key' => 'amount_mwk',    'label' => 'Amount (MWK)'],
        ['key' => 'status',        'label' => 'Status'],
        ['key' => 'actions',       'label' => ''],
    ];

    public function confirmApprove(string $id): void
    {
        $payout = ProviderPayout::with('provider')->findOrFail($id);
        $this->askConfirm('approve',
            "Approve payout to {$payout->provider->company_name}?",
            'MWK ' . number_format($payout->amount_mwk, 2) . ' will be queued for payment.',
            $id, 'Approve', 'btn-info');
    }

    public function confirmMarkPaid(string $id): void
    {
        $payout = ProviderPayout::with('provider')->findOrFail($id);
        $this->askConfirm('markPaid',
            "Confirm payment to {$payout->provider->company_name}?",
            'MWK ' . number_format($payout->amount_mwk, 2) . ' will be marked as paid.',
            $id, 'Mark Paid', 'btn-success');
    }

    public function approve(string $id): void
    {
        $payout = ProviderPayout::findOrFail($id);
        $payout->update(['status' => 'approved']);
        $this->success('Payout approved.');
    }

    public function markPaid(string $id): void
    {
        $payout = ProviderPayout::findOrFail($id);
        $payout->update(['status' => 'paid', 'paid_at' => now()]);
        $this->success('Payout marked as paid.');

        $billingPeriod = $payout->billingPeriod;
        $unpaid = ProviderPayout::where('billing_period_id', $billingPeriod->id)
            ->whereNotIn('status', ['paid', 'failed'])
            ->count();

        if ($unpaid === 0) {
            $billingPeriod->update(['status' => 'paid']);
        }
    }

    public function render()
    {
        $payouts = ProviderPayout::with(['provider.user', 'billingPeriod'])
            ->when($this->period, fn ($q) => $q->whereHas('billingPeriod', fn ($q) => $q->where('period_label', $this->period)))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->latest()
            ->paginate(15);

        $periods = BillingPeriod::orderByDesc('period_label')->get()
            ->map(fn ($p) => ['id' => $p->period_label, 'name' => $p->period_label])
            ->prepend(['id' => '', 'name' => 'All Periods']);

        return view('livewire.admin.payouts', [
            'payouts' => $payouts,
            'periods' => $periods,
        ])->layout('components.admin-layout');
    }
}
