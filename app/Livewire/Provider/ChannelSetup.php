<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use App\Livewire\Concerns\HasConfirmModal;
use App\Models\Channel;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

#[Title('Stream Setup')]
class ChannelSetup extends Component
{
    use HasConfirmModal, Toast;

    public Channel $channel;
    public bool $showKey = false;

    public function mount(Channel $channel): void
    {
        abort_unless(
            auth()->user()->providerProfile?->id === $channel->provider_id,
            403
        );
    }

    public function confirmRegenerate(): void
    {
        $this->askConfirm(
            'regenerateKey',
            'Regenerate stream key?',
            'Any currently active live stream will be disconnected immediately.',
            '', 'Regenerate', 'btn-warning'
        );
    }

    public function regenerateKey(): void
    {
        $this->channel->update(['rtmp_key' => Str::random(32)]);
        $this->showKey = true;
        $this->warning('Stream key regenerated. Update your encoder now — any active stream was disconnected.');
    }

    public function render()
    {
        $this->channel->refresh();

        return view('livewire.provider.channel-setup')
            ->layout('components.provider-layout');
    }
}
