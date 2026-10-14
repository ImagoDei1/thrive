<div wire:poll.10s>
    <x-header title="Channels" subtitle="All provider channels" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search channels..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Filters" icon="o-funnel" @click="$wire.showFilters = true" class="bg-base-300" responsive />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$channels" with-pagination>
            @scope('cell_name', $channel)
                <div class="flex items-center gap-3">
                    @if($channel->logo_url)
                        <img src="{{ $channel->logo_url }}" alt="{{ $channel->name }}"
                            class="w-9 h-9 rounded-lg object-cover shrink-0" />
                    @else
                        <div class="w-9 h-9 rounded-lg bg-base-200 flex items-center justify-center shrink-0 text-xs font-bold text-base-content/40">
                            {{ strtoupper(substr($channel->name, 0, 2)) }}
                        </div>
                    @endif
                    <div>
                        <p class="font-semibold">{{ $channel->name }}</p>
                        <p class="text-xs text-base-content/40">{{ $channel->slug }}</p>
                    </div>
                </div>
            @endscope

            @scope('cell_provider', $channel)
                <a href="{{ route('admin.providers.show', $channel->provider) }}" class="link link-primary text-sm" wire:navigate>
                    {{ $channel->provider->company_name }}
                </a>
            @endscope

            @scope('cell_category', $channel)
                <x-badge :value="ucfirst($channel->category ?? '—')" class="badge-ghost" />
            @endscope

            @scope('cell_is_live', $channel)
                @if($channel->is_live)
                    <x-badge value="Live" class="badge-error animate-pulse" />
                @else
                    <span class="text-base-content/30 text-xs">Offline</span>
                @endif
            @endscope

            @scope('cell_is_active', $channel)
                <x-badge :value="$channel->is_active ? 'Active' : 'Inactive'"
                    :class="$channel->is_active ? 'badge-success' : 'badge-ghost'" />
            @endscope

            @scope('cell_actions', $channel)
                <div class="flex gap-1 justify-end">
                    <x-button icon="o-film" class="btn-ghost btn-xs"
                        :tooltip="$channel->content_items_count . ' videos'"
                        link="{{ route('admin.content.index', ['channel' => $channel->id]) }}" wire:navigate />
                    <x-button
                        :icon="$channel->is_active ? 'o-eye-slash' : 'o-eye'"
                        wire:click="confirmToggle('{{ $channel->id }}')"
                        class="btn-ghost btn-xs"
                        :tooltip="$channel->is_active ? 'Deactivate' : 'Activate'" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-drawer wire:model="showFilters" title="Filters" class="lg:w-1/3" right separator with-close-button>
        <div class="grid gap-5">
            <x-select label="Category" wire:model.live="categoryFilter" :options="$categories"
                option-value="id" option-label="name" placeholder="All Categories" placeholder-value="" inline />
        </div>
        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="$set('categoryFilter', '')" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.showFilters = false" />
        </x-slot:actions>
    </x-drawer>

    <x-confirm-modal />
</div>
