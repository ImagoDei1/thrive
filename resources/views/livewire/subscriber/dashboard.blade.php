<div>
    <x-header title="My Dashboard" separator progress-indicator>
        <x-slot:actions>
            <div class="flex items-center gap-2 text-sm text-base-content/60 bg-base-200 rounded-lg px-3 py-2">
                <x-icon name="o-calendar" class="w-4 h-4" />
                {{ now()->format('F Y') }}
            </div>
        </x-slot:actions>
    </x-header>

    {{-- Expiry warning --}}
    @if($expiryWarning)
        <x-alert icon="o-exclamation-triangle" class="alert-warning mb-6">
            Your subscription expires <strong>{{ $profile->expires_at->diffForHumans() }}</strong>.
            Please renew through the Thrive mobile app to continue watching.
        </x-alert>
    @endif

    {{-- Subscription status card --}}
    <div class="grid lg:grid-cols-3 gap-5 lg:gap-8 mb-8">

        {{-- Status --}}
        <x-card class="col-span-2">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-base-content/50 font-medium uppercase tracking-wide">Subscription</p>
                    @if($activeSubscription)
                        <p class="text-2xl font-bold mt-1">{{ $activeSubscription->plan->name }}</p>
                        <p class="text-sm text-base-content/50 mt-0.5">
                            MWK {{ number_format($activeSubscription->plan->price_mwk, 2) }} / {{ $activeSubscription->plan->durationLabel() }}
                        </p>
                    @else
                        <p class="text-2xl font-bold mt-1 text-base-content/40">No active plan</p>
                        <p class="text-sm text-base-content/50 mt-0.5">Subscribe via the Thrive mobile app.</p>
                    @endif
                </div>

                @if($profile)
                    @php
                        $statusClass = match($profile->subscription_status) {
                            'active'    => 'badge-success',
                            'trial'     => 'badge-info',
                            'expired'   => 'badge-error',
                            'cancelled' => 'badge-ghost',
                            default     => 'badge-ghost',
                        };
                    @endphp
                    <x-badge :value="ucfirst($profile->subscription_status)" :class="$statusClass" />
                @endif
            </div>

            @if($profile?->expires_at)
                <div class="mt-4 pt-4 border-t border-base-200">
                    <div class="flex justify-between text-sm">
                        <span class="text-base-content/50">
                            {{ $profile->subscription_status === 'expired' ? 'Expired on' : 'Renews / expires on' }}
                        </span>
                        <span class="font-semibold {{ $daysRemaining !== null && $daysRemaining <= 3 ? 'text-error' : '' }}">
                            {{ $profile->expires_at->format('d M Y, H:i') }}
                            @if($daysRemaining !== null && $daysRemaining >= 0)
                                <span class="text-base-content/40 font-normal">({{ $profile->expires_at->diffForHumans(['parts' => 1, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE]) }} left)</span>
                            @endif
                        </span>
                    </div>
                </div>
            @endif

            @if($activeSubscription?->plan->features)
                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach($activeSubscription->plan->features as $feature)
                        <span class="inline-flex items-center gap-1 text-xs bg-base-200 rounded-full px-3 py-1">
                            <x-icon name="o-check" class="w-3 h-3 text-success" />
                            {{ $feature }}
                        </span>
                    @endforeach
                </div>
            @endif
        </x-card>

        {{-- Quick stats --}}
        <div class="flex flex-col gap-4">
            <x-stat title="Member Since"
                :value="$profile?->subscribed_at ? $profile->subscribed_at->format('M Y') : '—'"
                icon="o-star" class="shadow flex-1" />
            <x-stat title="Payments Made"
                :value="(string) $recentPayments->count()"
                icon="o-credit-card" class="shadow flex-1" />
        </div>
    </div>

    {{-- Recent payments --}}
    <x-header title="Recent Payments" size="text-xl" separator />

    @if($recentPayments->isEmpty())
        <x-card>
            <div class="text-center py-10 text-base-content/40">
                <x-icon name="o-receipt-percent" class="w-12 h-12 mx-auto mb-3" />
                <p class="font-medium">No payments yet</p>
                <p class="text-sm mt-1">Your payment history will appear here.</p>
            </div>
        </x-card>
    @else
        <x-card>
            <div class="divide-y divide-base-200">
                @foreach($recentPayments as $payment)
                    <div class="flex items-center justify-between py-3">
                        <div>
                            <p class="font-semibold text-sm">
                                {{ $payment->subscription?->plan?->name ?? 'Subscription' }}
                            </p>
                            <p class="text-xs text-base-content/40">
                                {{ $payment->created_at->format('d M Y, H:i') }}
                                @if($payment->payment_method)
                                    · {{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}
                                @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-bold text-sm">MWK {{ number_format($payment->amount, 2) }}</p>
                            @php
                                $pClass = match($payment->status) {
                                    'completed' => 'badge-success',
                                    'pending'   => 'badge-warning',
                                    'failed'    => 'badge-error',
                                    'refunded'  => 'badge-info',
                                    default     => 'badge-ghost',
                                };
                            @endphp
                            <x-badge :value="ucfirst($payment->status)" :class="$pClass . ' badge-xs mt-1'" />
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>

        <div class="mt-3 text-right">
            <x-button label="Full billing history" icon="o-arrow-right" class="btn-ghost btn-sm"
                link="{{ route('subscriber.billing') }}" wire:navigate />
        </div>
    @endif
</div>
