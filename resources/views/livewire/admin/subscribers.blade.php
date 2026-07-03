<div>
    <x-header title="Subscribers" subtitle="Manage subscriber accounts" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Filters" icon="o-funnel" @click="$wire.showFilters = true" class="bg-base-300" responsive />
        </x-slot:actions>
    </x-header>

    {{-- Summary stats --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <x-stat title="Total" :value="(string) $total" icon="o-users" class="shadow" />
        <x-stat title="Active" :value="(string) $active" icon="o-check-circle" class="shadow" color="text-success" />
        <x-stat title="Expired" :value="(string) $expired" icon="o-x-circle" class="shadow" color="text-error" />
    </div>

    <x-card>
        <x-table :headers="$headers" :rows="$subscribers" with-pagination>

            @scope('cell_name', $row)
                <span class="font-semibold text-sm">{{ $row->name }}</span>
            @endscope

            @scope('cell_email', $row)
                <span class="text-sm text-base-content/60">{{ $row->email }}</span>
            @endscope

            @scope('cell_subscription_status', $row)
                @php
                    $cls = match($row->subscription_status) {
                        'active'    => 'badge-success',
                        'trial'     => 'badge-info',
                        'expired'   => 'badge-error',
                        'cancelled' => 'badge-ghost',
                        default     => 'badge-ghost',
                    };
                @endphp
                <x-badge :value="ucfirst($row->subscription_status)" :class="$cls" />
            @endscope

            @scope('cell_subscribed_at', $row)
                <span class="text-sm">
                    {{ $row->subscribed_at ? $row->subscribed_at->format('d M Y') : '—' }}
                </span>
            @endscope

            @scope('cell_expires_at', $row)
                @if($row->expires_at)
                    <span class="text-sm {{ $row->expires_at->isPast() ? 'text-error' : ($row->expires_at->diffInDays(now()) <= 7 ? 'text-warning' : '') }}">
                        {{ $row->expires_at->format('d M Y') }}
                    </span>
                @else
                    <span class="text-base-content/30">—</span>
                @endif
            @endscope

            @scope('cell_payments_count', $row)
                <span class="text-sm">{{ $row->payments_count }}</span>
            @endscope

            @scope('cell_actions', $row)
                <div class="flex gap-1 justify-end">
                    @if(in_array($row->subscription_status, ['active', 'trial']))
                        <x-button label="Cancel" class="btn-error btn-xs"
                            wire:click="confirmCancel('{{ $row->id }}')" />
                    @elseif(in_array($row->subscription_status, ['expired', 'cancelled']))
                        <x-button label="Reactivate" class="btn-success btn-xs"
                            wire:click="confirmReactivate('{{ $row->id }}')" />
                    @endif
                </div>
            @endscope

        </x-table>
    </x-card>

    {{-- Filters drawer --}}
    <x-drawer wire:model="showFilters" title="Filters" class="lg:w-1/3" right separator with-close-button>
        <div class="grid gap-5">
            <x-input label="Search" wire:model.live.debounce="search" icon="o-magnifying-glass"
                placeholder="Name or email…" clearable />
            <x-select label="Status" wire:model.live="statusFilter" :options="[
                ['id' => '',          'name' => 'All'],
                ['id' => 'active',    'name' => 'Active'],
                ['id' => 'trial',     'name' => 'Trial'],
                ['id' => 'expired',   'name' => 'Expired'],
                ['id' => 'cancelled', 'name' => 'Cancelled'],
            ]" option-value="id" option-label="name" placeholder="All" placeholder-value="" inline />
        </div>
        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="clear" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.showFilters = false" />
        </x-slot:actions>
    </x-drawer>

    <x-confirm-modal />
</div>
