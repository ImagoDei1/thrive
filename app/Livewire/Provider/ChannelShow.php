<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use App\Livewire\Concerns\HasConfirmModal;
use App\Models\Channel;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

#[Title('Channel Settings')]
class ChannelShow extends Component
{
    use HasConfirmModal, Toast;

    public Channel $channel;
    public bool $showRtmpKey = false;

    public function mount(Channel $channel): void
    {
        abort_unless(
            auth()->user()->providerProfile?->id === $channel->provider_id,
            403
        );
    }

    public function confirmToggle(): void
    {
        $action = $this->channel->is_active ? 'Deactivate' : 'Activate';
        $body   = $this->channel->is_active
            ? 'This channel will be hidden from subscribers.'
            : 'This channel will become visible to subscribers.';
        $class  = $this->channel->is_active ? 'btn-error' : 'btn-success';

        $this->askConfirm('toggleActive', "{$action} {$this->channel->name}?", $body, '', $action, $class);
    }

    public function confirmRegenerate(): void
    {
        $this->askConfirm('regenerateKey',
            'Regenerate stream key?',
            'Any currently active live stream will be disconnected immediately.',
            '', 'Regenerate', 'btn-warning');
    }

    public function toggleActive(): void
    {
        $this->channel->update(['is_active' => ! $this->channel->is_active]);
        $status = $this->channel->is_active ? 'activated' : 'deactivated';
        $this->success("Channel {$status}.");
    }

    public function regenerateKey(): void
    {
        $this->channel->update(['rtmp_key' => Str::random(32)]);
        $this->showRtmpKey = true;
        $this->warning('RTMP key regenerated. Update your encoder — any active stream has been disconnected.');
    }

    public function render()
    {
        $this->channel->refresh();

        return view('livewire.provider.channel-show')
            ->layout('components.provider-layout');
    }
}
