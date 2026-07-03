<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Concerns\HasConfirmModal;
use App\Models\Channel;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

#[Title('Channels')]
class Channels extends Component
{
    use HasConfirmModal, Toast, WithPagination;

    public string $search = '';
    public string $categoryFilter = '';
    public bool $showFilters = false;

    public array $headers = [
        ['key' => 'name',      'label' => 'Channel'],
        ['key' => 'provider',  'label' => 'Provider'],
        ['key' => 'category',  'label' => 'Category'],
        ['key' => 'is_live',   'label' => 'Live'],
        ['key' => 'is_active', 'label' => 'Status'],
        ['key' => 'actions',   'label' => ''],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function confirmToggle(string $id): void
    {
        $channel = Channel::findOrFail($id);
        $action  = $channel->is_active ? 'Deactivate' : 'Activate';
        $body    = $channel->is_active
            ? 'This channel will be hidden from subscribers.'
            : 'This channel will become visible to subscribers.';
        $class   = $channel->is_active ? 'btn-error' : 'btn-success';

        $this->askConfirm('toggleActive', "{$action} {$channel->name}?", $body, $id, $action, $class);
    }

    public function toggleActive(string $id): void
    {
        $channel = Channel::findOrFail($id);
        $channel->update(['is_active' => ! $channel->is_active]);
        $status = $channel->is_active ? 'activated' : 'deactivated';
        $this->success("{$channel->name} {$status}.");
    }

    public function render()
    {
        $channels = Channel::with('provider.user')
            ->when($this->search, fn ($q) => $q->where('name', 'ilike', "%{$this->search}%"))
            ->when($this->categoryFilter, fn ($q) => $q->where('category', $this->categoryFilter))
            ->withCount('contentItems')
            ->latest()
            ->paginate(15);

        $categories = Channel::select('category')->distinct()->whereNotNull('category')->pluck('category')
            ->map(fn ($c) => ['id' => $c, 'name' => ucfirst($c)])->prepend(['id' => '', 'name' => 'All Categories']);

        return view('livewire.admin.channels', [
            'channels'   => $channels,
            'categories' => $categories,
        ])->layout('components.admin-layout');
    }
}
