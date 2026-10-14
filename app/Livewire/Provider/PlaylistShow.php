<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use App\Livewire\Concerns\HasConfirmModal;
use App\Models\Channel;
use App\Models\ContentItem;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

#[Title('Playlist')]
class PlaylistShow extends Component
{
    use HasConfirmModal, Toast;

    public const MAX_SEASON = 99;

    public Channel $channel;

    public Playlist $playlist;

    public bool $showAdd = false;

    public string $search = '';

    /** @var list<string> */
    public array $selected = [];

    public int $addSeason = 1;

    public function mount(Channel $channel, Playlist $playlist): void
    {
        abort_unless(
            auth()->user()->providerProfile?->id === $channel->provider_id
                && $playlist->channel_id === $channel->id,
            403
        );
    }

    public function openAdd(): void
    {
        $this->reset(['search', 'selected']);
        $this->addSeason = $this->playlist->isSeries() ? $this->playlist->latestSeason() : 1;
        $this->showAdd = true;
    }

    public function addSelected(): void
    {
        $this->validate([
            'selected'   => 'required|array|min:1',
            'selected.*' => 'string',
            'addSeason'  => 'required|integer|min:1|max:' . self::MAX_SEASON,
        ], ['selected.required' => 'Select at least one video.']);

        $items = ContentItem::where('channel_id', $this->channel->id)
            ->whereIn('id', $this->selected)
            ->oldest()
            ->get();

        $added = $this->playlist->addVideos($items, $this->addSeason);

        $this->showAdd = false;
        $this->reset(['search', 'selected']);
        $this->success($added === 1 ? '1 video added.' : "{$added} videos added.");
    }

    public function moveUp(string $rowId): void
    {
        $this->swap($rowId, -1);
    }

    public function moveDown(string $rowId): void
    {
        $this->swap($rowId, 1);
    }

    /** Moves a video into another season, at the end of that season. */
    public function setSeason(string $rowId, mixed $season): void
    {
        $season = (int) $season;

        if (! $this->playlist->isSeries() || $season < 1 || $season > self::MAX_SEASON) {
            $this->error('Season must be between 1 and ' . self::MAX_SEASON . '.');

            return;
        }

        $row = $this->ownedRow($rowId);

        if ($row->season_number === $season) {
            return;
        }

        $row->update([
            'season_number' => $season,
            'position'      => (int) PlaylistItem::where('playlist_id', $this->playlist->id)->max('position') + 1,
        ]);
    }

    public function confirmRemove(string $rowId): void
    {
        $row = $this->ownedRow($rowId);
        $title = ContentItem::whereKey($row->content_item_id)->value('title');

        $this->askConfirm('remove',
            "Remove \"{$title}\" from this playlist?",
            'The video itself is not deleted.',
            $rowId, 'Remove', 'btn-error');
    }

    public function remove(string $rowId): void
    {
        $this->ownedRow($rowId)->delete();
        $this->success('Video removed from playlist.');
    }

    public function render()
    {
        $items = $this->playlist->contentItems()->get();

        $candidates = $this->showAdd
            ? ContentItem::where('channel_id', $this->channel->id)
                ->whereNotIn('id', $items->pluck('id'))
                ->when($this->search !== '', fn ($q) => $q->where('title', 'ilike', '%' . $this->search . '%'))
                ->latest()
                ->limit(50)
                ->get()
            : collect();

        $groups = $this->playlist->isSeries()
            ? $items->groupBy(fn (ContentItem $item) => $item->pivot->season_number ?? 1)
            : collect([null => $items]);

        return view('livewire.provider.playlist-show', [
            'groups'     => $groups,
            'itemCount'  => $items->count(),
            'candidates' => $candidates,
        ])->layout('components.provider-layout');
    }

    // ---------------------------------------------------------------------------

    private function ownedRow(string $rowId): PlaylistItem
    {
        return PlaylistItem::where('playlist_id', $this->playlist->id)->findOrFail($rowId);
    }

    /** Swaps a row with its neighbour; series rows only move within their season. */
    private function swap(string $rowId, int $direction): void
    {
        $row = $this->ownedRow($rowId);

        $rows = PlaylistItem::where('playlist_id', $this->playlist->id)
            ->when($this->playlist->isSeries(), fn ($q) => $q->where('season_number', $row->season_number))
            ->orderBy('position')
            ->get()
            ->values();

        $index = $rows->search(fn (PlaylistItem $r) => $r->id === $row->id);
        $neighbour = $rows->get($index + $direction);

        if (! $neighbour) {
            return;
        }

        DB::transaction(function () use ($row, $neighbour) {
            [$a, $b] = [$row->position, $neighbour->position];
            $row->update(['position' => $b]);
            $neighbour->update(['position' => $a]);
        });
    }
}
