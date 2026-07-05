<div wire:poll.5s>
    <x-header title="Stream Setup" subtitle="{{ $channel->name }}" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Channel Info" icon="o-information-circle"
                link="{{ route('provider.channels.show', $channel) }}" wire:navigate class="btn-ghost" />
            <x-button label="Dashboard" icon="o-home"
                link="{{ route('provider.dashboard') }}" wire:navigate class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    <div class="grid lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2">

            <x-card separator>
                <x-slot:title>
                    <div class="flex items-center gap-2">
                        <x-icon name="o-signal" class="w-5 h-5 text-primary" />
                        Stream Credentials
                    </div>
                </x-slot:title>

                <div class="space-y-5">
                    {{-- RTMP URL --}}
                    <div>
                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-2">
                            RTMP Server / Ingest URL
                        </p>
                        <div class="flex items-center gap-2 bg-base-200 rounded-xl px-4 py-3">
                            <code class="flex-1 font-mono text-sm break-all select-all">{{ $channel->rtmp_ingest_url }}</code>
                            <x-button icon="o-clipboard-document"
                                x-on:click="navigator.clipboard.writeText('{{ $channel->rtmp_ingest_url }}'); $dispatch('mary-toast', {type:'success', title:'Copied!'})"
                                class="btn-ghost btn-sm shrink-0" tooltip="Copy" />
                        </div>
                        <p class="text-xs text-base-content/40 mt-1">Paste this as the "Server" or "RTMP URL" in your broadcasting software.</p>
                    </div>

                    {{-- Stream Key --}}
                    <div>
                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-2">
                            Stream Key
                        </p>
                        <div class="flex items-center gap-2 bg-base-200 rounded-xl px-4 py-3">
                            @if($showKey)
                                <code class="flex-1 font-mono text-sm break-all select-all">{{ $channel->rtmp_key }}</code>
                                <x-button icon="o-clipboard-document"
                                    x-on:click="navigator.clipboard.writeText('{{ $channel->rtmp_key }}'); $dispatch('mary-toast', {type:'success', title:'Copied!'})"
                                    class="btn-ghost btn-sm shrink-0" tooltip="Copy" />
                            @else
                                <code class="flex-1 font-mono text-sm tracking-widest text-base-content/40">••••••••••••••••••••••••••••••••</code>
                                <x-button label="Reveal" icon="o-eye" wire:click="$set('showKey', true)" class="btn-ghost btn-sm shrink-0" />
                            @endif
                        </div>
                        <p class="text-xs text-base-content/40 mt-1">Keep this secret. Paste it as the "Stream Key" in your encoder.</p>
                        <div class="mt-3">
                            <x-button label="Regenerate Key" icon="o-arrow-path"
                                wire:click="confirmRegenerate" class="btn-warning btn-sm" />
                        </div>
                    </div>

                    {{-- HLS Playback --}}
                    <div>
                        <p class="text-xs font-semibold text-base-content/50 uppercase tracking-wider mb-2">
                            HLS Playback URL
                        </p>
                        <div class="flex items-center gap-2 bg-base-200 rounded-xl px-4 py-3">
                            <code class="flex-1 font-mono text-sm break-all select-all">{{ $channel->hls_playback_url }}</code>
                            <x-button icon="o-clipboard-document"
                                x-on:click="navigator.clipboard.writeText('{{ $channel->hls_playback_url }}'); $dispatch('mary-toast', {type:'success', title:'Copied!'})"
                                class="btn-ghost btn-sm shrink-0" tooltip="Copy" />
                        </div>
                        <p class="text-xs text-base-content/40 mt-1">Share this URL with viewers or use it in the StreamTV app.</p>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Sidebar --}}
        <div class="space-y-6">
            <x-card title="Channel Status" separator>
                <x-list-item :item="['k' => 'Visibility']" value="k" no-separator no-hover>
                    <x-slot:actions>
                        <x-badge :value="$channel->is_active ? 'Active' : 'Inactive'"
                            :class="$channel->is_active ? 'badge-success' : 'badge-ghost'" />
                    </x-slot:actions>
                </x-list-item>
                <x-list-item :item="['k' => 'Stream Status']" value="k" no-separator no-hover>
                    <x-slot:actions>
                        @if($channel->is_live)
                            <x-badge value="● LIVE" class="badge-error animate-pulse" />
                        @else
                            <span class="text-sm text-base-content/40">Offline</span>
                        @endif
                    </x-slot:actions>
                </x-list-item>
            </x-card>

            <x-card title="Need help connecting?" separator>
                <p class="text-sm text-base-content/60 mb-4">
                    Not sure how to set up your broadcasting software? Our guide covers OBS, Streamlabs, vMix, and mobile apps.
                </p>
                <x-button label="How to Connect" icon="o-question-mark-circle"
                    link="/docs/streaming-setup" external
                    class="btn-primary btn-sm w-full" />
            </x-card>
        </div>
    </div>

    <x-confirm-modal />
</div>
