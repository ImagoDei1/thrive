<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HasVideoPreview;
use App\Models\Channel;
use App\Models\ContentItem;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Content')]
class ContentItems extends Component
{
    use HasVideoPreview, Toast, WithPagination;

    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'channel')]
    public string $channelFilter = '';

    public bool $showFilters = false;

    public array $headers = [
        ['key' => 'title',            'label' => 'Title'],
        ['key' => 'channel',          'label' => 'Channel'],
        ['key' => 'duration_seconds', 'label' => 'Duration'],
        ['key' => 'status',           'label' => 'Status'],
        ['key' => 'created_at',       'label' => 'Uploaded'],
        ['key' => 'actions',          'label' => ''],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedChannelFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset('statusFilter', 'channelFilter');
        $this->resetPage();
    }

    public function preview(string $id): void
    {
        $item = ContentItem::with('channel.provider')->findOrFail($id);

        $this->openPreview($item, "{$item->channel->name} · {$item->channel->provider->company_name}");
    }

    public function render()
    {
        $items = ContentItem::with('channel.provider')
            ->when($this->search, fn ($q) => $q->where('title', 'ilike', "%{$this->search}%"))
            ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->channelFilter, fn ($q) => $q->where('channel_id', $this->channelFilter))
            ->latest()
            ->paginate(15);

        $statuses = collect(['uploading', 'processing', 'ready', 'failed'])
            ->map(fn ($s) => ['id' => $s, 'name' => ucfirst($s)])
            ->prepend(['id' => '', 'name' => 'All Statuses']);

        $channels = Channel::orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])
            ->prepend(['id' => '', 'name' => 'All Channels']);

        return view('livewire.admin.content-items', [
            'items'      => $items,
            'statuses'   => $statuses,
            'channels'   => $channels,
            'hasPending' => $items->contains(fn ($i) => in_array($i->status, ['uploading', 'processing'], true)),
        ])->layout('components.admin-layout');
    }
}
