<div>
    <x-header title="Dashboard" separator progress-indicator>
        <x-slot:actions>
            <div class="flex items-center gap-2 text-sm text-base-content/60 bg-base-200 rounded-lg px-3 py-2">
                <x-icon name="o-calendar" class="w-4 h-4" />
                {{ now()->format('F Y') }}
            </div>
        </x-slot:actions>
    </x-header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 lg:gap-8">
        <x-stat title="Active Subscribers" :value="number_format($totalSubscribers)" icon="o-users" class="shadow" />
        <x-stat title="Active Channels"    :value="number_format($activeChannels)"   icon="o-tv"    class="shadow" />
        <x-stat title="Revenue {{ now()->format('M Y') }}" :value="'MWK '.number_format($currentRevenue, 0)" icon="o-banknotes" class="shadow" />
        <x-stat title="Pending Payouts"    :value="number_format($pendingPayouts)"   icon="o-clock" class="shadow" color="!text-error" />
    </div>

    <div class="grid lg:grid-cols-2 gap-8 mt-8">

        {{-- Pending approvals --}}
        <x-card title="Pending Provider Approvals" separator>
            @forelse($recentProviders as $provider)
                <x-list-item :item="$provider" value="company_name" :sub-value="$provider->user->email" avatar-placeholder="{{ substr($provider->company_name, 0, 2) }}">
                    <x-slot:actions>
                        <x-button icon="o-eye" link="{{ route('admin.providers.show', $provider) }}" class="btn-ghost btn-xs" wire:navigate tooltip="Review" />
                    </x-slot:actions>
                </x-list-item>
            @empty
                <div class="text-center py-8 text-base-content/40">
                    <x-icon name="o-check-circle" class="w-10 h-10 mx-auto mb-2" />
                    <p class="text-sm">All providers reviewed</p>
                </div>
            @endforelse
            @if($recentProviders->isNotEmpty())
                <x-slot:footer>
                    <x-button label="All Providers" icon="o-building-office-2" link="{{ route('admin.providers.index') }}" class="btn-ghost btn-sm" wire:navigate />
                </x-slot:footer>
            @endif
        </x-card>

        {{-- Quick actions --}}
        <x-card title="Quick Actions" separator>
            <div class="grid grid-cols-2 gap-3">
                <x-button label="Providers"       icon="o-building-office-2" link="{{ route('admin.providers.index') }}"       class="btn-outline justify-start" wire:navigate />
                <x-button label="Channels"        icon="o-tv"                link="{{ route('admin.channels.index') }}"        class="btn-outline justify-start" wire:navigate />
                <x-button label="Change Requests" icon="o-pencil-square"     link="{{ route('admin.change-requests.index') }}" class="btn-outline justify-start" wire:navigate />
                <x-button label="Billing"         icon="o-calendar-days"     link="{{ route('admin.billing.index') }}"         class="btn-outline justify-start" wire:navigate />
                <x-button label="Payouts"         icon="o-banknotes"         link="{{ route('admin.payouts.index') }}"         class="btn-outline justify-start" wire:navigate />
            </div>
        </x-card>
    </div>
</div>
