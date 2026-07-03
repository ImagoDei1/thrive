<div>
    <x-header title="Videos" :subtitle="$channel->name" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Channel Settings" icon="o-cog-6-tooth"
                link="{{ route('provider.channels.show', $channel) }}" wire:navigate class="btn-ghost" responsive />
            <x-button label="Dashboard" icon="o-home"
                link="{{ route('provider.dashboard') }}" wire:navigate class="btn-ghost" responsive />
        </x-slot:actions>
    </x-header>

    <x-alert icon="o-information-circle" class="alert-info mb-6">
        Video upload is handled via your encoder. Files processed by FFmpeg appear here automatically once ready.
    </x-alert>

    <x-card>
        @if($items->isEmpty())
            <div class="text-center py-12 text-base-content/40">
                <x-icon name="o-film" class="w-12 h-12 mx-auto mb-3" />
                <p class="font-medium">No videos yet</p>
                <p class="text-sm mt-1">Videos will appear here once processed.</p>
            </div>
        @else
            <x-table :headers="$headers" :rows="$items" with-pagination>
                @scope('cell_title', $item)
                    <div>
                        <p class="font-semibold">{{ $item->title }}</p>
                        @if($item->description)
                            <p class="text-xs text-base-content/40 truncate max-w-xs">{{ $item->description }}</p>
                        @endif
                    </div>
                @endscope

                @scope('cell_type', $item)
                    <x-badge :value="strtoupper(str_replace('_', ' ', $item->type))" class="badge-ghost" />
                @endscope

                @scope('cell_duration_seconds', $item)
                    @php $h = intdiv($item->duration_seconds, 3600); $m = intdiv($item->duration_seconds % 3600, 60); $s = $item->duration_seconds % 60; @endphp
                    <span class="font-mono text-sm">{{ $h > 0 ? "{$h}h " : '' }}{{ $m }}m {{ $s }}s</span>
                @endscope

                @scope('cell_status', $item)
                    <x-badge :value="ucfirst($item->status)" :class="
                        $item->status === 'ready'      ? 'badge-success' :
                       ($item->status === 'processing' ? 'badge-warning' :
                       ($item->status === 'uploading'  ? 'badge-info'    : 'badge-error'))" />
                @endscope

                @scope('cell_published_at', $item)
                    {{ $item->published_at?->format('d M Y') ?? '—' }}
                @endscope

                @scope('cell_actions', $item)
                    <div class="flex gap-1 justify-end">
                        @if($item->hls_url)
                            <x-button icon="o-play" class="btn-ghost btn-xs" tooltip="Preview"
                                tag="a" href="{{ $item->hls_url }}" target="_blank" />
                        @endif
                        <x-button icon="o-trash" class="btn-ghost btn-xs text-error"
                            wire:click="confirmDelete('{{ $item->id }}')" tooltip="Delete" />
                    </div>
                @endscope
            </x-table>
        @endif
    </x-card>

    <x-confirm-modal />
</div>
