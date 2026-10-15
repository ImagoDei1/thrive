<div>
    <x-header title="Subscription Plans" subtitle="Packages subscribers can buy in the Thrive app" separator progress-indicator>
        <x-slot:actions>
            <x-button label="New Plan" icon="o-plus" class="btn-primary" wire:click="create" responsive />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$plans">

            @scope('cell_name', $row)
                <div>
                    <span class="font-semibold text-sm">{{ $row->name }}</span>
                    <span class="block text-xs text-base-content/40">{{ $row->slug }}</span>
                </div>
            @endscope

            @scope('cell_duration_days', $row)
                <span class="text-sm">{{ $row->durationLabel() }}</span>
            @endscope

            @scope('cell_price_mwk', $row)
                <span class="text-sm font-semibold">{{ number_format($row->price_mwk, 2) }}</span>
            @endscope

            @scope('cell_grace_days', $row)
                <span class="text-sm">{{ $row->grace_days ? $row->grace_days . ' ' . Str::plural('day', $row->grace_days) : '—' }}</span>
            @endscope

            @scope('cell_subscriptions_count', $row)
                <span class="text-sm">{{ $row->subscriptions_count }}</span>
            @endscope

            @scope('cell_is_active', $row)
                <x-badge :value="$row->is_active ? 'Offered' : 'Hidden'" :class="$row->is_active ? 'badge-success' : 'badge-ghost'" />
            @endscope

            @scope('cell_actions', $row)
                <div class="flex gap-1 justify-end">
                    <x-button label="Edit" class="btn-ghost btn-xs" wire:click="edit('{{ $row->id }}')" />
                    <x-button :label="$row->is_active ? 'Hide' : 'Offer'"
                        :class="($row->is_active ? 'btn-warning' : 'btn-success') . ' btn-xs'"
                        wire:click="toggleActive('{{ $row->id }}')" spinner />
                </div>
            @endscope

        </x-table>
    </x-card>

    <x-modal wire:model="showModal" :title="$editingId ? 'Edit Plan' : 'New Plan'">
        <x-form wire:submit="save">
            <div class="grid grid-cols-2 gap-4">
                <x-input label="Name" wire:model.live.debounce="name" placeholder="24-Hour Pass" />
                <x-input label="Slug" wire:model="slug" placeholder="daily" />
            </div>

            <x-textarea label="Description" wire:model="description" rows="2" />

            <div class="grid grid-cols-3 gap-4">
                <x-input label="Price" wire:model="price_mwk" prefix="MWK" type="number" step="0.01" min="0" />
                <x-input label="Duration (days)" wire:model="duration_days" type="number" min="1" max="366"
                    hint="1 = 24h, 7 = week, 30 = month" />
                <x-input label="Grace (days)" wire:model="grace_days" type="number" min="0" max="30"
                    hint="Status only — playback stops at expiry" />
            </div>

            <x-textarea label="Features" wire:model="features" rows="3" hint="One per line — shown in the app" />

            <x-toggle label="Offered to subscribers" wire:model="is_active" />

            @if($editingId)
                <p class="text-xs text-base-content/50">
                    Changes apply to new purchases only. Existing subscriptions keep the dates they were activated with.
                </p>
            @endif

            <x-slot:actions>
                <x-button label="Cancel" @click="$wire.showModal = false" />
                <x-button label="Save" type="submit" icon="o-check" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
