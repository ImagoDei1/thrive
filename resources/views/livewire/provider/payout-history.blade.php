<div>
    <x-header title="Payout History" subtitle="Revenue earned per billing period" separator progress-indicator />

    {{-- Current month watch breakdown --}}
    @if($watchSummary->isNotEmpty())
        <x-card title="{{ now()->format('F Y') }} — Watch Time" separator class="mb-6">
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                @foreach($watchSummary as $row)
                    <x-stat :title="$row->channel->name" :value="number_format($row->total_hours, 2).' hrs'" icon="o-clock" class="shadow" />
                @endforeach
            </div>
        </x-card>
    @endif

    <x-card title="All Payouts" separator>
        @if($payouts->isEmpty())
            <div class="text-center py-12 text-base-content/40">
                <x-icon name="o-banknotes" class="w-12 h-12 mx-auto mb-3" />
                <p class="font-medium">No payouts yet</p>
                <p class="text-sm mt-1">Payouts are calculated at the end of each billing period.</p>
            </div>
        @else
            <x-table :headers="$headers" :rows="$payouts" with-pagination>
                @scope('cell_period', $payout)
                    <span class="font-mono font-semibold">{{ $payout->billingPeriod->period_label }}</span>
                @endscope

                @scope('cell_watch_hours', $payout)
                    {{ number_format($payout->watch_hours, 2) }} hrs
                @endscope

                @scope('cell_share_percent', $payout)
                    {{ number_format($payout->share_percent * 100, 4) }}%
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

                @scope('cell_paid_at', $payout)
                    {{ $payout->paid_at?->format('d M Y') ?? '—' }}
                @endscope
            </x-table>
        @endif
    </x-card>
</div>
