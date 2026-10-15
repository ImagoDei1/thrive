<div @if($hasPending && ! $showPreview) wire:poll.10s @endif>
    <x-header title="Videos" :subtitle="$channel->name" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Upload Videos" icon="o-arrow-up-tray" class="btn-primary" responsive
                link="{{ route('provider.content.upload', $channel) }}" wire:navigate />
            <x-button label="Playlists" icon="o-queue-list"
                link="{{ route('provider.playlists.index', $channel) }}" wire:navigate class="btn-ghost" responsive />
            <x-button label="Channel Settings" icon="o-cog-6-tooth"
                link="{{ route('provider.channels.show', $channel) }}" wire:navigate class="btn-ghost" responsive />
            <x-button label="Dashboard" icon="o-home"
                link="{{ route('provider.dashboard') }}" wire:navigate class="btn-ghost" responsive />
        </x-slot:actions>
    </x-header>

    <x-alert icon="o-information-circle" class="alert-info mb-6">
        Uploaded videos are transcoded automatically and published once processing finishes.
        Unpublish a video to hide it from subscribers without deleting it.
    </x-alert>

    <x-card>
        @if($items->isEmpty())
            <div class="text-center py-12 text-base-content/40">
                <x-icon name="o-film" class="w-12 h-12 mx-auto mb-3" />
                <p class="font-medium">No videos yet</p>
                <p class="text-sm mt-1">Upload your first video to get started.</p>
            </div>
        @else
            <x-table :headers="$headers" :rows="$items" with-pagination>
                @scope('cell_title', $item)
                    <div class="max-w-xs xl:max-w-md" title="{{ $item->title }}">
                        <p class="font-semibold line-clamp-2 break-words">{{ $item->title }}</p>
                        @if($item->description)
                            <p class="text-xs text-base-content/40 truncate">{{ $item->description }}</p>
                        @endif
                    </div>
                @endscope

                @scope('cell_type', $item)
                    <x-badge :value="strtoupper(str_replace('_', ' ', $item->type))" class="badge-ghost" />
                @endscope

                @scope('cell_duration_seconds', $item)
                    @php $h = intdiv($item->duration_seconds, 3600); $m = intdiv($item->duration_seconds % 3600, 60); $s = $item->duration_seconds % 60; @endphp
                    <span class="font-mono text-sm whitespace-nowrap">{{ $h > 0 ? "{$h}h " : '' }}{{ $m }}m {{ $s }}s</span>
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

                @scope('cell_published_at', $item)
                    @if($item->isUnpublished())
                        <x-badge value="Unpublished" class="badge-ghost" />
                    @else
                        <span class="whitespace-nowrap">{{ $item->published_at?->format('d M Y') ?? '—' }}</span>
                    @endif
                @endscope

                @scope('cell_actions', $item)
                    <div class="flex gap-1 justify-end whitespace-nowrap">
                        @if($item->status === 'ready' && $item->bunny_video_id)
                            <x-button icon="o-play" class="btn-ghost btn-xs" tooltip="Preview"
                                wire:click="preview('{{ $item->id }}')" spinner />
                        @endif
                        @if($item->isUnpublished())
                            <x-button icon="o-eye" class="btn-ghost btn-xs" tooltip="Republish"
                                wire:click="republish('{{ $item->id }}')" spinner />
                        @else
                            <x-button icon="o-eye-slash" class="btn-ghost btn-xs" tooltip="Unpublish"
                                wire:click="confirmUnpublish('{{ $item->id }}')" />
                        @endif
                        <x-button icon="o-trash" class="btn-ghost btn-xs text-error"
                            wire:click="confirmDelete('{{ $item->id }}')" tooltip="Delete" />
                    </div>
                @endscope
            </x-table>
        @endif
    </x-card>

    <x-video-preview-modal />
    <x-confirm-modal />
</div>
