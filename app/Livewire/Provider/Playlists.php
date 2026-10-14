<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use App\Livewire\Concerns\HasConfirmModal;
use App\Models\Channel;
use App\Models\Playlist;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Playlists')]
class Playlists extends Component
{
    use HasConfirmModal, Toast, WithPagination;

    public Channel $channel;

    public bool $showForm = false;

    public ?string $editingId = null;

    #[Rule('required|string|max:255')]
    public string $title = '';

    #[Rule('nullable|string|max:5000')]
    public ?string $description = null;

    #[Rule('required|in:playlist,series')]
    public string $kind = 'playlist';

    #[Rule('boolean')]
    public bool $isPublished = true;

    public array $headers = [
        ['key' => 'title',       'label' => 'Title'],
        ['key' => 'kind',        'label' => 'Type'],
        ['key' => 'items_count', 'label' => 'Videos'],
        ['key' => 'status',      'label' => 'Status'],
        ['key' => 'actions',     'label' => ''],
    ];

    public array $kinds = [
        ['id' => 'playlist', 'name' => 'Playlist — an ordered list of videos'],
        ['id' => 'series',   'name' => 'Series — videos grouped into seasons and episodes'],
    ];

    public function mount(Channel $channel): void
    {
        abort_unless(
            auth()->user()->providerProfile?->id === $channel->provider_id,
            403
        );
    }

    public function create(): void
    {
        $this->reset(['editingId', 'title', 'description', 'kind', 'isPublished']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(string $id): void
    {
        $playlist = $this->ownedPlaylist($id);

        $this->editingId   = $playlist->id;
        $this->title       = $playlist->title;
        $this->description = $playlist->description;
        $this->kind        = $playlist->kind;
        $this->isPublished = $playlist->is_published;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        $data = $this->validate();

        $attributes = [
            'title'        => $data['title'],
            'description'  => $data['description'] ?: null,
            'kind'         => $data['kind'],
            'is_published' => $data['isPublished'],
        ];

        if ($this->editingId) {
            $playlist = $this->ownedPlaylist($this->editingId);
            $playlist->fill($attributes);
        } else {
            $playlist = new Playlist(['channel_id' => $this->channel->id, ...$attributes]);
        }

        if ($playlist->is_published && ! $playlist->published_at) {
            $playlist->published_at = now();
        }

        $converting = $playlist->exists && $playlist->isDirty('kind');
        $playlist->save();

        // Switching to a series puts every video in season 1; switching back clears seasons.
        if ($converting) {
            $playlist->contentItems()->newPivotQuery()->update([
                'season_number' => $playlist->isSeries() ? 1 : null,
            ]);
        }

        $this->showForm = false;

        if ($this->editingId) {
            $this->success('Playlist updated.');

            return;
        }

        $this->success('Playlist created. Now add some videos.');
        $this->redirectRoute('provider.playlists.show', [$this->channel, $playlist], navigate: true);
    }

    public function confirmDelete(string $id): void
    {
        $playlist = $this->ownedPlaylist($id);
        $this->askConfirm('delete',
            "Delete \"{$playlist->title}\"?",
            'The videos themselves are kept — only the playlist is removed.',
            $id, 'Delete', 'btn-error');
    }

    public function delete(string $id): void
    {
        $this->ownedPlaylist($id)->delete();
        $this->success('Playlist deleted.');
    }

    public function render()
    {
        $playlists = Playlist::where('channel_id', $this->channel->id)
            ->withCount('contentItems as items_count')
            ->latest()
            ->paginate(15);

        return view('livewire.provider.playlists', [
            'playlists' => $playlists,
        ])->layout('components.provider-layout');
    }

    private function ownedPlaylist(string $id): Playlist
    {
        return Playlist::where('channel_id', $this->channel->id)->findOrFail($id);
    }
}
