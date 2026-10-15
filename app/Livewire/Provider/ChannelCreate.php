<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Str;
use Mary\Traits\Toast;

#[Title('New Channel')]
class ChannelCreate extends Component
{
    use Toast;

    #[Rule('required|min:3|max:100')]
    public string $name = '';

    #[Rule('nullable|max:500')]
    public string $description = '';

    #[Rule('nullable|max:50')]
    public string $category = '';

    #[Rule('required|size:2')]
    public string $language = 'en';

    public function mount(): void
    {
        $provider = auth()->user()->providerProfile;

        if ($provider && $provider->channels()->exists()) {
            $channel = $provider->channels()->first();
            $this->redirect(route('provider.channels.show', $channel), navigate: true);
        }
    }

    public function save(): void
    {
        $this->validate();

        $provider = auth()->user()->providerProfile;

        // Double-check — race condition guard
        if ($provider->channels()->exists()) {
            $this->error('You already have a channel.');
            return;
        }

        $channel = $provider->channels()->create([
            'name'            => $this->name,
            'slug'            => Str::slug($this->name) . '-' . Str::random(4),
            'description'     => $this->description,
            'category'        => $this->category,
            'language'        => $this->language,
            'rtmp_key'        => Str::random(32),
            'rtmp_ingest_url' => config('streaming.rtmp_ingest_url'),
        ]);

        $channel->update([
            'hls_playback_url' => route('hls.playback', ['slug' => $channel->slug, 'file' => 'index.m3u8']),
        ]);

        $this->success('Channel created. Stream setup unlocks once our team verifies it.');

        $this->redirect(route('provider.channels.show', $channel), navigate: true);
    }

    public function render()
    {
        return view('livewire.provider.channel-create')
            ->layout('components.provider-layout');
    }
}
