<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use App\Livewire\Concerns\HasConfirmModal;
use App\Livewire\Concerns\HasVideoPreview;
use App\Models\Channel;
use App\Models\ContentItem;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Videos')]
class ContentItems extends Component
{
    use HasConfirmModal, HasVideoPreview, Toast, WithPagination;

    public Channel $channel;

    public array $headers = [
        ['key' => 'title',            'label' => 'Title'],
        ['key' => 'type',             'label' => 'Type'],
        ['key' => 'duration_seconds', 'label' => 'Duration'],
        ['key' => 'likes_count',      'label' => 'Likes'],
        ['key' => 'status',           'label' => 'Status'],
        ['key' => 'published_at',     'label' => 'Published'],
        ['key' => 'actions',          'label' => ''],
    ];

    public function mount(Channel $channel): void
    {
        abort_unless(
            auth()->user()->providerProfile?->id === $channel->provider_id,
            403
        );
    }

    public function preview(string $id): void
    {
        $item = ContentItem::where('channel_id', $this->channel->id)->findOrFail($id);
        $item->setRelation('channel', $this->channel->loadMissing('provider'));

        $this->openPreview($item, $this->channel->name);
    }

    public function confirmUnpublish(string $id): void
    {
        $item = $this->ownedItem($id);
        $this->askConfirm('unpublish',
            "Unpublish \"{$item->title}\"?",
            'Subscribers will no longer see it in the app or in playlists. The video is kept and you can republish it any time.',
            $id, 'Unpublish', 'btn-warning');
    }

    public function unpublish(string $id): void
    {
        $item = $this->ownedItem($id);
        $item->update(['unpublished_at' => now()]);
        $this->success("\"{$item->title}\" unpublished.");
    }

    public function republish(string $id): void
    {
        $item = $this->ownedItem($id);
        $item->update(['unpublished_at' => null]);
        $this->success($item->status === 'ready'
            ? "\"{$item->title}\" is visible to subscribers again."
            : "\"{$item->title}\" will be published once processing finishes.");
    }

    private function ownedItem(string $id): ContentItem
    {
        return ContentItem::where('channel_id', $this->channel->id)->findOrFail($id);
    }

    public function confirmDelete(string $id): void
    {
        $item = $this->ownedItem($id);
        $this->askConfirm('delete',
            "Delete \"{$item->title}\"?",
            'The video is permanently removed from storage and cannot be recovered. To hide it from subscribers but keep it, unpublish it instead.',
            $id, 'Delete', 'btn-error');
    }

    public function delete(string $id): void
    {
        $item = ContentItem::findOrFail($id);
        abort_unless($item->channel_id === $this->channel->id, 403);

        $item->delete();
        $this->success('Video deleted.');
    }

    public function render()
    {
        $this->channel->loadMissing('provider');

        $items = ContentItem::where('channel_id', $this->channel->id)
            ->withCount('likes')
            ->latest()
            ->paginate(15);

        $items->getCollection()->each(fn (ContentItem $item) => $item->setRelation('channel', $this->channel));

        return view('livewire.provider.content-items', [
            'items'      => $items,
            'hasPending' => $items->getCollection()->contains(fn ($i) => $i->status === 'processing'),
        ])->layout('components.provider-layout');
    }
}
