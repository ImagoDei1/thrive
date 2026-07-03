<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HasConfirmModal;
use App\Models\BillingPeriod;
use App\Services\BillingPeriodService;
use App\Services\RevenueCalculationService;
use Illuminate\Validation\Rule as ValidationRule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

#[Title('Billing Periods')]
class BillingPeriods extends Component
{
    use HasConfirmModal, Toast;

    public bool $showCreateModal = false;

    public string $periodLabel = '';
    public int $cutoffDay = BillingPeriodService::DEFAULT_CUTOFF_DAY;

    public function rules(): array
    {
        return [
            'periodLabel' => [
                'required',
                ValidationRule::regex('/^\d{4}-(0[1-9]|1[0-2])$/'),
                ValidationRule::unique('billing_periods', 'period_label'),
            ],
            'cutoffDay' => ['required', 'integer', 'min:1', 'max:28'],
        ];
    }

    public array $headers = [
        ['key' => 'period_label',      'label' => 'Period'],
        ['key' => 'cutoff_date',       'label' => 'Cut-off'],
        ['key' => 'payout_date',       'label' => 'Payout Date'],
        ['key' => 'total_subscribers', 'label' => 'Subscribers'],
        ['key' => 'total_revenue',     'label' => 'Revenue (MWK)'],
        ['key' => 'provider_pool',     'label' => 'Provider Pool'],
        ['key' => 'status',            'label' => 'Status'],
        ['key' => 'actions',           'label' => ''],
    ];

    public function openCreate(): void
    {
        $this->periodLabel = now()->format('Y-m');
        $this->cutoffDay   = BillingPeriodService::DEFAULT_CUTOFF_DAY;
        $this->showCreateModal = true;
    }

    public function createPeriod(): void
    {
        $this->validate();

        app(BillingPeriodService::class)->createPeriod($this->periodLabel, $this->cutoffDay);

        $this->showCreateModal = false;
        $this->reset('periodLabel', 'cutoffDay');
        $this->success("Billing period {$this->periodLabel} created.");
    }

    public function confirmCalculate(string $id): void
    {
        $period = BillingPeriod::findOrFail($id);
        $this->askConfirm('calculate',
            "Calculate revenue for {$period->period_label}?",
            'This will overwrite any existing payout drafts for this period.',
            $id, 'Calculate', 'btn-warning');
    }

    public function confirmClose(string $id): void
    {
        $period = BillingPeriod::findOrFail($id);
        $this->askConfirm('closePeriod',
            "Close period {$period->period_label}?",
            'Once closed no further payments will be assigned to this period. The next period will be created automatically.',
            $id, 'Close Period', 'btn-error');
    }

    public function calculate(string $id): void
    {
        $period = BillingPeriod::findOrFail($id);
        app(RevenueCalculationService::class)->calculate($period);
        $this->success("Revenue calculated for {$period->period_label}.");
    }

    public function closePeriod(string $id): void
    {
        $period = BillingPeriod::findOrFail($id);
        $period->update(['status' => 'closed', 'closed_at' => now()]);

        // Auto-create the next period
        $next = app(BillingPeriodService::class)->ensureNextPeriodExists($period);

        $this->success("Period {$period->period_label} closed. Next period {$next->period_label} created (payout: {$next->payout_date->format('d M Y')}).");
    }

    public function render()
    {
        return view('livewire.admin.billing-periods', [
            'periods' => BillingPeriod::latest('period_label')->paginate(12),
        ])->layout('components.admin-layout');
    }
}
