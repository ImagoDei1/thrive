<div>
    <x-header title="Providers" subtitle="Manage content providers" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Filters" icon="o-funnel" @click="$wire.showFilters = true" class="bg-base-300" responsive />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$providers" with-pagination>
            @scope('cell_company_name', $provider)
                @php $logo = $provider->channels->first()?->logo_url; @endphp
                <div class="flex items-center gap-3">
                    @if($logo)
                        <img src="{{ $logo }}" alt="{{ $provider->company_name }}"
                            class="w-9 h-9 rounded-lg object-cover shrink-0" />
                    @else
                        <div class="w-9 h-9 rounded-lg bg-base-200 flex items-center justify-center shrink-0 text-xs font-bold text-base-content/40">
                            {{ strtoupper(substr($provider->company_name, 0, 2)) }}
                        </div>
                    @endif
                    <span class="font-semibold">{{ $provider->company_name }}</span>
                </div>
            @endscope

            @scope('cell_email', $provider)
                {{ $provider->user->email }}
            @endscope

            @scope('cell_is_approved', $provider)
                <x-badge :value="$provider->is_approved ? 'Approved' : 'Pending'"
                    :class="$provider->is_approved ? 'badge-success' : 'badge-warning'" />
            @endscope

            @scope('cell_kyc_status', $provider)
                @php [$label, $class] = match($provider->kyc_status) {
                    'approved'  => ['Approved', 'badge-success'],
                    'submitted' => ['Awaiting review', 'badge-warning'],
                    'rejected'  => ['Rejected', 'badge-error'],
                    default     => ['Not started', 'badge-ghost'],
                }; @endphp
                <x-badge :value="$label" :class="$class" />
            @endscope

            @scope('cell_setup_fee', $provider)
                @php $feeStatus = $provider->setupFeeStatus(); @endphp
                @if($feeStatus === 'none')
                    <span class="text-base-content/30">—</span>
                @else
                    <x-badge :value="rtrim(rtrim(number_format((float) $provider->setup_fee_percentage, 2), '0'), '.').'% until '.$provider->setup_fee_ends_period"
                        :class="match($feeStatus) { 'active' => 'badge-warning', 'upcoming' => 'badge-info', default => 'badge-success' }" />
                @endif
            @endscope

            @scope('cell_actions', $provider)
                <div class="flex gap-1 justify-end">
                    <x-button icon="o-eye" link="{{ route('admin.providers.show', $provider) }}" class="btn-ghost btn-xs" tooltip="View" wire:navigate />
                    @if($provider->kycAwaitingReview())
                        <x-button icon="o-shield-check" link="{{ route('admin.providers.show', $provider) }}" class="btn-warning btn-xs" tooltip="Review KYC" wire:navigate />
                    @elseif($provider->is_approved)
                        <x-button icon="o-x-mark" wire:click="confirmReject('{{ $provider->id }}')" class="btn-error btn-outline btn-xs" tooltip="Revoke" />
                    @endif
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-drawer wire:model="showFilters" title="Filters" class="lg:w-1/3" right separator with-close-button>
        <div class="grid gap-5">
            <x-select label="Status" wire:model.live="statusFilter" :options="[
                ['id' => '',         'name' => 'All'],
                ['id' => 'approved', 'name' => 'Approved'],
                ['id' => 'pending',  'name' => 'Pending'],
                ['id' => 'kyc_review', 'name' => 'KYC awaiting review'],
            ]" option-value="id" option-label="name" placeholder="All" placeholder-value="" inline />
        </div>
        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="$set('statusFilter', '')" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.showFilters = false" />
        </x-slot:actions>
    </x-drawer>

    <x-confirm-modal />
</div>
