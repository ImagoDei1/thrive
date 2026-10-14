<div @if($hasPending && ! $showPreview) wire:poll.10s @endif>
    <x-header title="Content" subtitle="All provider videos" separator progress-indicator>
        <x-slot:middle class="!justify-end">
            <x-input placeholder="Search titles..." wire:model.live.debounce="search" icon="o-magnifying-glass" clearable />
        </x-slot:middle>
        <x-slot:actions>
            <x-button label="Filters" icon="o-funnel" @click="$wire.showFilters = true" class="bg-base-300" responsive />
        </x-slot:actions>
    </x-header>

    <x-card>
        <x-table :headers="$headers" :rows="$items" with-pagination>
            @scope('cell_title', $item)
                <div class="flex items-center gap-3">
                    @if($thumb = app(\App\Services\VideoSourceService::class)->thumbnailUrl($item))
                        <img src="{{ $thumb }}" alt="" class="w-16 h-9 rounded object-cover shrink-0 bg-base-200" loading="lazy" />
                    @else
                        <div class="w-16 h-9 rounded bg-base-200 flex items-center justify-center shrink-0">
                            <x-icon name="o-film" class="w-4 h-4 text-base-content/30" />
                        </div>
                    @endif
                    <div>
                        <p class="font-semibold">{{ $item->title }}</p>
                        @if($item->description)
                            <p class="text-xs text-base-content/40 truncate max-w-xs">{{ $item->description }}</p>
                        @endif
                    </div>
                </div>
            @endscope

            @scope('cell_channel', $item)
                <div>
                    <p class="text-sm">{{ $item->channel?->name ?? '—' }}</p>
                    @if($item->channel?->provider)
                        <a href="{{ route('admin.providers.show', $item->channel->provider) }}" class="link link-primary text-xs" wire:navigate>
                            {{ $item->channel->provider->company_name }}
                        </a>
                    @endif
                </div>
            @endscope

            @scope('cell_duration_seconds', $item)
                @php $h = intdiv((int) $item->duration_seconds, 3600); $m = intdiv((int) $item->duration_seconds % 3600, 60); $s = (int) $item->duration_seconds % 60; @endphp
                <span class="font-mono text-sm">{{ $h > 0 ? "{$h}h " : '' }}{{ $m }}m {{ $s }}s</span>
            @endscope

            @scope('cell_status', $item)
                <x-badge :value="ucfirst($item->status)" :class="
                    $item->status === 'ready'      ? 'badge-success' :
                   ($item->status === 'processing' ? 'badge-warning' :
                   ($item->status === 'uploading'  ? 'badge-info'    : 'badge-error'))" />
                @if($item->status === 'processing')
                    <span class="loading loading-spinner loading-xs ml-1 align-middle"></span>
                @endif
            @endscope

            @scope('cell_created_at', $item)
                {{ $item->created_at->format('d M Y') }}
            @endscope

            @scope('cell_actions', $item)
                <div class="flex gap-1 justify-end">
                    @if($item->status === 'ready' && $item->bunny_video_id)
                        <x-button icon="o-play" class="btn-ghost btn-xs" tooltip="Preview"
                            wire:click="preview('{{ $item->id }}')" spinner />
                    @endif
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-video-preview-modal />

    <x-drawer wire:model="showFilters" title="Filters" class="lg:w-1/3" right separator with-close-button>
        <div class="grid gap-5">
            <x-select label="Status" wire:model.live="statusFilter" :options="$statuses"
                option-value="id" option-label="name" placeholder="All Statuses" placeholder-value="" inline />
            <x-select label="Channel" wire:model.live="channelFilter" :options="$channels"
                option-value="id" option-label="name" placeholder="All Channels" placeholder-value="" inline />
        </div>
        <x-slot:actions>
            <x-button label="Reset" icon="o-x-mark" wire:click="resetFilters" spinner />
            <x-button label="Done" icon="o-check" class="btn-primary" @click="$wire.showFilters = false" />
        </x-slot:actions>
    </x-drawer>
</div>
