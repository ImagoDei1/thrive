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
    <div class="grid lg:grid-cols-4 gap-5 lg:gap-8">
        <x-stat title="Watch Time {{ now()->format('M') }}" :value="number_format($watchThisMonth / 3600, 1).' hrs'" icon="o-clock" class="shadow" />
        <x-stat title="Est. Payout" :value="'MWK '.number_format($estimatedPayout, 0)" icon="o-banknotes" class="shadow" />
        <x-stat title="Channel Status" :value="$channel ? ($channel->is_active ? 'Active' : 'Inactive') : 'No Channel'" icon="o-tv" class="shadow" />
        <x-stat title="Last Paid" :value="$lastPayout ? 'MWK '.number_format($lastPayout->amount_mwk, 0) : '—'" icon="o-check-badge" class="shadow" />
    </div>

    {{-- Channel section --}}
    <div class="mt-8">
        <x-header title="My Channel" size="text-xl" separator />

        @if(! $channel)
            <x-card>
                <div class="text-center py-10 text-base-content/40">
                    <x-icon name="o-tv" class="w-12 h-12 mx-auto mb-3" />
                    <p class="font-medium">No channel yet</p>
                    <p class="text-sm mt-1">Create your channel to start streaming.</p>
                    <x-button label="Create Channel" icon="o-plus" link="{{ route('provider.channels.create') }}"
                        class="btn-primary mt-4" wire:navigate />
                </div>
            </x-card>
        @else
            <x-card>
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <p class="font-bold text-xl">{{ $channel->name }}</p>
                        <p class="text-sm text-base-content/50 mt-0.5">
                            {{ ucfirst($channel->category ?? 'General') }} · {{ strtoupper($channel->language) }}
                        </p>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        <x-badge :value="$channel->is_active ? 'Active' : 'Inactive'"
                            :class="$channel->is_active ? 'badge-success' : 'badge-ghost'" />
                        @if($channel->is_live)
                            <x-badge value="● LIVE" class="badge-error animate-pulse" />
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    <div class="bg-base-200/50 rounded-xl p-3 text-center">
                        <p class="text-xs text-base-content/50">Videos</p>
                        <p class="font-bold text-lg">{{ $channel->content_items_count }}</p>
                    </div>
                    <div class="bg-base-200/50 rounded-xl p-3 text-center">
                        <p class="text-xs text-base-content/50">Watch Time</p>
                        <p class="font-bold text-lg">{{ number_format($watchThisMonth / 3600, 1) }}<span class="text-xs">h</span></p>
                    </div>
                </div>

                <x-slot:actions>
                    <x-button label="Channel Info" icon="o-information-circle"
                        link="{{ route('provider.channels.show', $channel) }}" wire:navigate class="btn-ghost btn-sm" />
                    <x-button label="Stream Setup" icon="o-signal"
                        link="{{ route('provider.channels.setup', $channel) }}" wire:navigate class="btn-primary btn-sm" />
                    <x-button label="Videos" icon="o-film"
                        link="{{ route('provider.content.index', $channel) }}" wire:navigate class="btn-ghost btn-sm" />
                </x-slot:actions>
            </x-card>
        @endif
    </div>
</div>
