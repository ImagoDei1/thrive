<div>
    <x-header :title="$playlist->title" :subtitle="$channel->name" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Add Videos" icon="o-plus" class="btn-primary" responsive wire:click="openAdd" />
            <x-button label="All Playlists" icon="o-arrow-left"
                link="{{ route('provider.playlists.index', $channel) }}" wire:navigate class="btn-ghost" responsive />
        </x-slot:actions>
    </x-header>

    <div class="flex flex-wrap items-center gap-2 mb-6">
        <x-badge :value="ucfirst($playlist->kind)" :class="$playlist->isSeries() ? 'badge-secondary' : 'badge-ghost'" />
        <x-badge :value="$playlist->is_published ? 'Published' : 'Draft'"
            :class="$playlist->is_published ? 'badge-success' : 'badge-ghost'" />
        <span class="text-sm text-base-content/50">{{ $itemCount }} {{ \Illuminate\Support\Str::plural('video', $itemCount) }}</span>
        <span class="text-sm text-base-content/50">· Viewers only see videos that have finished processing.</span>
    </div>

    @if($itemCount === 0)
        <x-card>
            <div class="text-center py-12 text-base-content/40">
                <x-icon name="o-queue-list" class="w-12 h-12 mx-auto mb-3" />
                <p class="font-medium">This playlist is empty</p>
                <p class="text-sm mt-1">Add videos from your channel to get started.</p>
            </div>
        </x-card>
    @else
        <div class="space-y-6">
            @foreach($groups as $season => $items)
                <x-card wire:key="group-{{ $season }}" :title="$playlist->isSeries() ? 'Season ' . $season : null" shadow>
                    <div class="divide-y divide-base-200">
                        @foreach($items as $item)
                            <div wire:key="row-{{ $item->pivot->id }}" class="py-3 flex items-center gap-3">
                                <span class="font-mono text-sm text-base-content/50 w-12 shrink-0">
                                    {{ $playlist->isSeries() ? 'E' . $loop->iteration : $loop->iteration }}
                                </span>

                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold truncate">{{ $item->title }}</p>
                                    <p class="text-xs text-base-content/50">
                                        @php $h = intdiv($item->duration_seconds, 3600); $m = intdiv($item->duration_seconds % 3600, 60); $s = $item->duration_seconds % 60; @endphp
                                        {{ $h > 0 ? "{$h}h " : '' }}{{ $m }}m {{ $s }}s
                                        @if($item->status !== 'ready')
                                            · <span class="text-warning">{{ ucfirst($item->status) }}</span>
                                        @endif
                                        @if($item->isUnpublished())
                                            · <span class="text-base-content/50">Unpublished</span>
                                        @endif
                                    </p>
                                </div>

                                @if($playlist->isSeries())
                                    <div class="w-24 shrink-0">
                                        <x-input type="number" min="1" max="{{ \App\Livewire\Provider\PlaylistShow::MAX_SEASON }}"
                                            value="{{ $item->pivot->season_number }}" class="input-sm" prefix="S"
                                            wire:change="setSeason('{{ $item->pivot->id }}', $event.target.value)" />
                                    </div>
                                @endif

                                <div class="flex gap-1 shrink-0">
                                    <x-button icon="o-chevron-up" class="btn-ghost btn-xs" tooltip="Move up"
                                        wire:click="moveUp('{{ $item->pivot->id }}')" :disabled="$loop->first" />
                                    <x-button icon="o-chevron-down" class="btn-ghost btn-xs" tooltip="Move down"
                                        wire:click="moveDown('{{ $item->pivot->id }}')" :disabled="$loop->last" />
                                    <x-button icon="o-x-mark" class="btn-ghost btn-xs text-error" tooltip="Remove from playlist"
                                        wire:click="confirmRemove('{{ $item->pivot->id }}')" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif

    <x-modal wire:model="showAdd" title="Add Videos" class="backdrop-blur" box-class="max-w-2xl">
        <x-form wire:submit="addSelected">
            <x-input placeholder="Search your videos…" icon="o-magnifying-glass" wire:model.live.debounce.300ms="search" clearable />

            @if($playlist->isSeries())
                <x-input label="Add to season" type="number" min="1" max="{{ \App\Livewire\Provider\PlaylistShow::MAX_SEASON }}"
                    wire:model="addSeason" hint="Enter a new number to start a new season." />
            @endif

            <div class="max-h-80 overflow-y-auto divide-y divide-base-200 border border-base-200 rounded-box">
                @forelse($candidates as $candidate)
                    <label wire:key="candidate-{{ $candidate->id }}" class="flex items-center gap-3 p-3 cursor-pointer hover:bg-base-200/50">
                        <input type="checkbox" class="checkbox checkbox-sm checkbox-primary"
                            value="{{ $candidate->id }}" wire:model="selected" />
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium truncate">{{ $candidate->title }}</span>
                            <span class="block text-xs text-base-content/50">
                                {{ $candidate->created_at->format('d M Y') }}
                                @if($candidate->status !== 'ready') · {{ ucfirst($candidate->status) }} @endif
                            </span>
                        </span>
                    </label>
                @empty
                    <p class="p-6 text-center text-sm text-base-content/50">
                        {{ $search !== '' ? 'No videos match your search.' : 'All your videos are already in this playlist.' }}
                    </p>
                @endforelse
            </div>
            @error('selected') <p class="text-error text-sm">{{ $message }}</p> @enderror

            <x-slot:actions>
                <x-button label="Cancel" wire:click="$set('showAdd', false)" />
                <x-button label="Add selected" type="submit" icon="o-plus" class="btn-primary" spinner="addSelected" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-confirm-modal />
</div>
