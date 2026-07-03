<div>
    <x-header title="{{ $provider->company_name }}" subtitle="Provider Profile" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Back" icon="o-arrow-left" link="{{ route('admin.providers.index') }}" class="btn-ghost" wire:navigate />
            @if(! $provider->is_approved)
                <x-button label="Approve" icon="o-check" wire:click="confirmApprove" class="btn-success" />
            @else
                <x-button label="Revoke" icon="o-x-mark" wire:click="confirmReject" class="btn-error btn-outline" />
            @endif
        </x-slot:actions>
    </x-header>

    <div class="grid lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Company Details" separator>
                @foreach([
                    ['Company Name',   $provider->company_name],
                    ['Contact Person', $provider->contact_person ?? '—'],
                    ['Phone',          $provider->contact_phone  ?? '—'],
                    ['Email',          $provider->user->email],
                ] as [$label, $value])
                    <x-list-item :item="['label' => $label, 'value' => $value]" value="value" sub-value="label" no-separator />
                @endforeach
                @if($provider->description)
                    <div class="pt-2 px-2">
                        <p class="text-xs font-medium text-base-content/50 uppercase tracking-wide mb-1">Description</p>
                        <p class="text-sm">{{ $provider->description }}</p>
                    </div>
                @endif
            </x-card>

            <x-card title="Channels ({{ $provider->channels->count() }})" separator>
                @forelse($provider->channels as $channel)
                    <x-list-item :item="$channel" value="name"
                        :sub-value="ucfirst($channel->category ?? '—').' · '.strtoupper($channel->language)"
                        no-separator>
                        <x-slot:actions>
                            <x-badge :value="$channel->is_active ? 'Active' : 'Inactive'"
                                :class="$channel->is_active ? 'badge-success' : 'badge-ghost'" />
                            @if($channel->is_live)
                                <x-badge value="Live" class="badge-error animate-pulse" />
                            @endif
                        </x-slot:actions>
                    </x-list-item>
                @empty
                    <p class="text-sm text-base-content/40 px-2 py-4">No channels yet.</p>
                @endforelse
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Status" separator>
                <x-list-item :item="['k' => 'Approval']" value="k" no-separator no-hover>
                    <x-slot:actions>
                        <x-badge :value="$provider->is_approved ? 'Approved' : 'Pending'"
                            :class="$provider->is_approved ? 'badge-success' : 'badge-warning'" />
                    </x-slot:actions>
                </x-list-item>
                <x-list-item :item="['k' => 'Setup Fee']" value="k" no-separator no-hover>
                    <x-slot:actions>
                        <x-badge :value="ucfirst($provider->setup_fee_status)"
                            :class="$provider->setup_fee_status === 'paid' ? 'badge-success' : 'badge-ghost'" />
                    </x-slot:actions>
                </x-list-item>
                @if($provider->onboarded_at)
                    <x-list-item
                        :item="['k' => 'Onboarded', 'v' => $provider->onboarded_at->format('d M Y')]"
                        value="k" sub-value="v" no-separator no-hover />
                @endif
            </x-card>

            <x-card title="Payout History" separator>
                @forelse($provider->payouts->take(5) as $payout)
                    <x-list-item
                        :item="['period' => $payout->billingPeriod->period_label, 'amount' => 'MWK '.number_format($payout->amount_mwk, 2)]"
                        value="period" sub-value="amount" no-separator />
                @empty
                    <p class="text-sm text-base-content/40 px-2 py-4">No payouts yet.</p>
                @endforelse
            </x-card>
        </div>
    </div>

    <x-confirm-modal />
</div>
