<div>
    <x-header title="Payouts" subtitle="Provider revenue payouts" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Filters" icon="o-funnel" @click="$wire.showFilters = true" class="bg-base-300" responsive />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$payouts" with-pagination>
            @scope('cell_provider', $payout)
                <div>
                    <p class="font-semibold text-sm">{{ $payout->provider->company_name }}</p>
                    <p class="text-xs text-base-content/40">{{ $payout->provider->user->email }}</p>
                </div>
            @endscope

            @scope('cell_period_label', $payout)
                <span class="font-mono text-sm">{{ $payout->billingPeriod->period_label }}</span>
            @endscope

            @scope('cell_watch_hours', $payout)
                {{ number_format($payout->watch_hours, 2) }} hrs
            @endscope

            @scope('cell_share_percent', $payout)
                {{ number_format($payout->share_percent * 100, 2) }}%
            @endscope

            @scope('cell_gross_amount_mwk', $payout)
                {{ number_format($payout->gross_amount_mwk, 2) }}
            @endscope

            @scope('cell_setup_fee_mwk', $payout)
                @if($payout->setup_fee_mwk > 0)
                    <span class="text-warning">−{{ number_format($payout->setup_fee_mwk, 2) }}</span>
                    <span class="text-xs text-base-content/40">({{ rtrim(rtrim(number_format((float) $payout->setup_fee_percentage, 2), '0'), '.') }} pts)</span>
                @else
                    <span class="text-base-content/30">—</span>
                @endif
            @endscope

            @scope('cell_amount_mwk', $payout)
                <span class="font-bold">MWK {{ number_format($payout->amount_mwk, 2) }}</span>
            @endscope

            @scope('cell_status', $payout)
                <x-badge :value="ucfirst($payout->status)" :class="
                    $payout->status === 'pending'  ? 'badge-warning' :
                   ($payout->status === 'approved' ? 'badge-info'    :
                   ($payout->status === 'paid'     ? 'badge-success' : 'badge-error'))" />
            @endscope

            @scope('cell_actions', $payout)
                <div class="flex gap-1 justify-end">
                    @if($payout->status === 'pending')
                        <x-button label="Approve" class="btn-info btn-xs"
                            wire:click="confirmApprove('{{ $payout->id }}')" />
                    @endif
                    @if($payout->status === 'approved')
                        <x-button label="Mark Paid" class="btn-success btn-xs"
                            wire:click="confirmMarkPaid('{{ $payout->id }}')" />
                    @endif
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-drawer wire:model="showFilters" title="Filters" class="lg:w-1/3" right separator with-close-button>
        <div class="grid gap-5">
            <x-select label="Period" wire:model.live="period" :options="$periods"
                option-value="id" option-label="name" placeholder="All Periods" placeholder-value="" inline />
            <x-select label="Status" wire:model.live="statusFilter" :options="[
                ['id' => '',         'name' => 'All'],
                ['id' => 'pending',  'name' => 'Pending'],
                ['id' => 'approved', 'name' => 'Approved'],
                ['id' => 'paid',     'name' => 'Paid'],
                ['id' => 'failed',   'name' => 'Failed'],
            ]" option-value="id" option-label="name" placeholder="All" placeholder-value="" inline />
        </div>
        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.showFilters = false" />
        </x-slot:actions>
    </x-drawer>

    <x-confirm-modal />
</div>
