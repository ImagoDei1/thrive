<div>
    <x-header title="Playlists" :subtitle="$channel->name" separator progress-indicator>
        <x-slot:actions>
            <x-button label="New Playlist" icon="o-plus" class="btn-primary" responsive wire:click="create" />
            <x-button label="Videos" icon="o-film"
                link="{{ route('provider.content.index', $channel) }}" wire:navigate class="btn-ghost" responsive />
        </x-slot:actions>
    </x-header>

    <x-alert icon="o-information-circle" class="alert-info mb-6">
        Group your videos into playlists, such as a sermon series or conference sessions. Choose
        <strong>Series</strong> when viewers should see seasons and episode numbers. A video can be in several playlists.
    </x-alert>

    <x-card>
        @if($playlists->isEmpty())
            <div class="text-center py-12 text-base-content/40">
                <x-icon name="o-queue-list" class="w-12 h-12 mx-auto mb-3" />
                <p class="font-medium">No playlists yet</p>
                <p class="text-sm mt-1">Create a playlist to group related videos.</p>
            </div>
        @else
            <x-table :headers="$headers" :rows="$playlists" with-pagination>
                @scope('cell_title', $playlist)
                    <div>
                        <a href="{{ route('provider.playlists.show', [$playlist->channel_id, $playlist]) }}" wire:navigate
                            class="font-semibold hover:underline">{{ $playlist->title }}</a>
                        @if($playlist->description)
                            <p class="text-xs text-base-content/40 truncate max-w-xs">{{ $playlist->description }}</p>
                        @endif
                    </div>
                @endscope

                @scope('cell_kind', $playlist)
                    <x-badge :value="ucfirst($playlist->kind)" :class="$playlist->isSeries() ? 'badge-secondary' : 'badge-ghost'" />
                @endscope

                @scope('cell_status', $playlist)
                    <x-badge :value="$playlist->is_published ? 'Published' : 'Draft'"
                        :class="$playlist->is_published ? 'badge-success' : 'badge-ghost'" />
                @endscope

                @scope('cell_actions', $playlist)
                    <div class="flex gap-1 justify-end">
                        <x-button icon="o-queue-list" class="btn-ghost btn-xs" tooltip="Manage videos"
                            link="{{ route('provider.playlists.show', [$playlist->channel_id, $playlist]) }}" wire:navigate />
                        <x-button icon="o-pencil" class="btn-ghost btn-xs" tooltip="Edit details"
                            wire:click="edit('{{ $playlist->id }}')" />
                        <x-button icon="o-trash" class="btn-ghost btn-xs text-error" tooltip="Delete"
                            wire:click="confirmDelete('{{ $playlist->id }}')" />
                    </div>
                @endscope
            </x-table>
        @endif
    </x-card>

    <x-modal wire:model="showForm" :title="$editingId ? 'Edit Playlist' : 'New Playlist'" class="backdrop-blur">
        <x-form wire:submit="save">
            <x-input label="Title" wire:model="title" placeholder="e.g. Book of Romans" />
            <x-textarea label="Description" wire:model="description" rows="3" placeholder="Optional" />
            <x-select label="Type" wire:model="kind" :options="$kinds" />
            <x-toggle label="Published" wire:model="isPublished"
                hint="Viewers only see published playlists that have at least one ready video." />

            <x-slot:actions>
                <x-button label="Cancel" wire:click="$set('showForm', false)" />
                <x-button label="Save" type="submit" icon="o-check" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-confirm-modal />
</div>
