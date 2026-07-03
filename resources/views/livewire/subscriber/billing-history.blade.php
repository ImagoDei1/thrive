<div>
    <x-header title="Billing History" subtitle="All your subscription payments" separator progress-indicator>
        <x-slot:actions>
            <x-select wire:model.live="statusFilter" :options="[
                ['id' => '',          'name' => 'All'],
                ['id' => 'completed', 'name' => 'Completed'],
                ['id' => 'pending',   'name' => 'Pending'],
                ['id' => 'failed',    'name' => 'Failed'],
                ['id' => 'refunded',  'name' => 'Refunded'],
            ]" option-value="id" option-label="name" placeholder="All statuses" placeholder-value="" class="select-sm" />
        </x-slot:actions>
    </x-header>

    <x-card>
        @if($payments->isEmpty())
            <div class="text-center py-16 text-base-content/40">
                <x-icon name="o-receipt-percent" class="w-12 h-12 mx-auto mb-3" />
                <p class="font-medium">No payments found</p>
                <p class="text-sm mt-1">Your payment history will appear here once you subscribe.</p>
            </div>
        @else
            <div class="divide-y divide-base-200">
                @foreach($payments as $payment)
                    <div class="flex items-center justify-between py-4">
                        <div class="flex items-center gap-4">
                            <div class="bg-base-200 rounded-xl p-2.5">
                                <x-icon name="o-credit-card" class="w-5 h-5 text-base-content/50" />
                            </div>
                            <div>
                                <p class="font-semibold text-sm">
                                    {{ $payment->subscription?->plan?->name ?? 'Subscription Payment' }}
                                </p>
                                <p class="text-xs text-base-content/40 mt-0.5">
                                    {{ $payment->created_at->format('d M Y, H:i') }}
                                    @if($payment->payment_method)
                                        · {{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}
                                    @endif
                                    @if($payment->phone_number)
                                        · {{ $payment->phone_number }}
                                    @endif
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="font-bold">MWK {{ number_format($payment->amount, 2) }}</p>
                            @php
                                $pClass = match($payment->status) {
                                    'completed' => 'badge-success',
                                    'pending'   => 'badge-warning',
                                    'failed'    => 'badge-error',
                                    'refunded'  => 'badge-info',
                                    default     => 'badge-ghost',
                                };
                            @endphp
                            <x-badge :value="ucfirst($payment->status)" :class="$pClass . ' badge-sm mt-1'" />
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $payments->links() }}
            </div>
        @endif
    </x-card>
</div>
