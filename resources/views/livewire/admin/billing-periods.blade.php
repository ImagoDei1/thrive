<div>
    <x-header title="Billing Periods" subtitle="Monthly revenue periods" separator progress-indicator>
        <x-slot:actions>
            <x-button label="New Period" icon="o-plus" wire:click="openCreate" class="btn-primary" responsive />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$periods" with-pagination>
            @scope('cell_cutoff_date', $period)
                <span class="text-sm">
                    {{ $period->cutoff_date?->format('d M Y') ?? '—' }}
                </span>
            @endscope

            @scope('cell_payout_date', $period)
                @if($period->payout_date)
                    <span class="font-medium {{ $period->payout_date->isToday() ? 'text-success' : '' }}">
                        {{ $period->payout_date->format('d M Y') }}
                    </span>
                @else
                    <span class="text-base-content/30">—</span>
                @endif
            @endscope

            @scope('cell_total_revenue', $period)
                <span class="font-semibold">MWK {{ number_format($period->total_revenue, 2) }}</span>
            @endscope

            @scope('cell_provider_pool', $period)
                MWK {{ number_format($period->provider_pool, 2) }}
            @endscope

            @scope('cell_status', $period)
                <x-badge :value="ucfirst($period->status)" :class="
                    $period->status === 'open'        ? 'badge-info'    :
                   ($period->status === 'calculating' ? 'badge-warning' :
                   ($period->status === 'closed'      ? 'badge-success' : 'badge-primary'))" />
            @endscope

            @scope('cell_actions', $period)
                <div class="flex gap-1 justify-end">
                    @if(in_array($period->status, ['open', 'calculating']))
                        <x-button label="Calculate" icon="o-calculator" class="btn-warning btn-xs"
                            wire:click="confirmCalculate('{{ $period->id }}')" />
                    @endif
                    @if($period->status === 'calculating')
                        <x-button label="Close" icon="o-lock-closed" class="btn-error btn-xs"
                            wire:click="confirmClose('{{ $period->id }}')" />
                    @endif
                    <x-button icon="o-banknotes"
                        link="{{ route('admin.payouts.index', ['period' => $period->period_label]) }}"
                        class="btn-ghost btn-xs" tooltip="View Payouts" wire:navigate />
                </div>
            @endscope
        </x-table>
    </x-card>

    {{-- Create modal --}}
    <x-modal wire:model="showCreateModal" title="New Billing Period">
        <x-form wire:submit="createPeriod">
            <x-input label="Period (YYYY-MM)" wire:model="periodLabel"
                placeholder="{{ now()->format('Y-m') }}"
                hint="The month this period covers, e.g. {{ now()->format('Y-m') }}" />

            <x-input label="Cut-off day" wire:model="cutoffDay" type="number" min="1" max="28"
                hint="Payments received on or before this day are assigned to this period. Default: 20." />

            @if($cutoffDay)
                @php
                    try {
                        $previewCutoff  = \Carbon\Carbon::createFromFormat('Y-m', $periodLabel ?: now()->format('Y-m'))
                            ->setDay((int) $cutoffDay);
                        $previewPayout  = app(\App\Services\BillingPeriodService::class)->payoutDateFromCutoff($previewCutoff);
                        $preview = true;
                    } catch (\Exception $e) {
                        $preview = false;
                    }
                @endphp
                @if($preview)
                    <div class="bg-base-200 rounded-xl p-4 text-sm space-y-1">
                        <div class="flex justify-between">
                            <span class="text-base-content/50">Cut-off date</span>
                            <span class="font-medium">{{ $previewCutoff->format('d M Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/50">Payout date</span>
                            <span class="font-semibold text-success">{{ $previewPayout->format('d M Y') }}</span>
                        </div>
                    </div>
                @endif
            @endif

            <x-slot:actions>
                <x-button label="Cancel" wire:click="$set('showCreateModal', false)" />
                <x-button label="Create" type="submit" class="btn-primary" spinner="createPeriod" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-confirm-modal />
</div>
