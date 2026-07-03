<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use App\Livewire\Concerns\HasConfirmModal;
use App\Models\Channel;
use App\Models\ContentItem;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Videos')]
class ContentItems extends Component
{
    use HasConfirmModal, Toast, WithPagination;

    public Channel $channel;

    public array $headers = [
        ['key' => 'title',            'label' => 'Title'],
        ['key' => 'type',             'label' => 'Type'],
        ['key' => 'duration_seconds', 'label' => 'Duration'],
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

    public function confirmDelete(string $id): void
    {
        $item = ContentItem::findOrFail($id);
        $this->askConfirm('delete',
            "Delete \"{$item->title}\"?",
            'This action cannot be undone.',
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
        return view('livewire.provider.content-items', [
            'items' => ContentItem::where('channel_id', $this->channel->id)
                ->latest()
                ->paginate(15),
        ])->layout('components.provider-layout');
    }
}
