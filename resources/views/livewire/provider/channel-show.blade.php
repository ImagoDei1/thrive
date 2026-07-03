<div>
    <x-header title="{{ $channel->name }}" subtitle="Channel Settings" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Back" icon="o-arrow-left" link="{{ route('provider.dashboard') }}" class="btn-ghost" wire:navigate />
            <x-button
                :label="$channel->is_active ? 'Deactivate' : 'Activate'"
                :icon="$channel->is_active ? 'o-eye-slash' : 'o-eye'"
                wire:click="confirmToggle"
                :class="$channel->is_active ? 'btn-ghost' : 'btn-success'" />
        </x-slot:actions>
    </x-header>

    <div class="grid lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">

            <x-card title="Stream Credentials" separator>
                <div class="space-y-4">
                    <x-input label="RTMP Ingest URL" value="{{ $channel->rtmp_ingest_url }}" readonly icon="o-signal" />

                    <div>
                        <p class="text-sm font-medium mb-1">Stream Key</p>
                        @if($showRtmpKey)
                            <x-input value="{{ $channel->rtmp_key }}" readonly icon="o-key"
                                hint="Copy this now — it will be hidden when you leave." />
                        @else
                            <div class="flex gap-2">
                                <x-input value="••••••••••••••••••••••••••••••••" readonly class="flex-1" />
                                <x-button label="Show" icon="o-eye" wire:click="$set('showRtmpKey', true)" />
                            </div>
                        @endif
                        <div class="mt-2">
                            <x-button label="Regenerate Key" icon="o-arrow-path" wire:click="confirmRegenerate" class="btn-warning btn-sm" />
                        </div>
                    </div>

                    <x-input label="HLS Playback URL" value="{{ $channel->hls_playback_url }}" readonly icon="o-play-circle" />
                </div>
            </x-card>

            <x-card title="Videos" separator>
                <x-list-item :item="['title' => 'On-demand content for this channel']" value="title" no-separator no-hover>
                    <x-slot:actions>
                        <x-button label="Manage Videos" icon="o-film"
                            link="{{ route('provider.content.index', $channel) }}" wire:navigate class="btn-ghost btn-sm" />
                    </x-slot:actions>
                </x-list-item>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Status" separator>
                <x-list-item :item="['label' => 'Visibility']" value="label" no-separator no-hover>
                    <x-slot:actions>
                        <x-badge :value="$channel->is_active ? 'Active' : 'Inactive'"
                            :class="$channel->is_active ? 'badge-success' : 'badge-ghost'" />
                    </x-slot:actions>
                </x-list-item>
                <x-list-item :item="['label' => 'Live Now']" value="label" no-separator no-hover>
                    <x-slot:actions>
                        @if($channel->is_live)
                            <x-badge value="LIVE" class="badge-error animate-pulse" />
                        @else
                            <span class="text-sm text-base-content/30">Offline</span>
                        @endif
                    </x-slot:actions>
                </x-list-item>
                <x-list-item
                    :item="['label' => 'Category', 'value' => ucfirst($channel->category ?? '—')]"
                    value="label" sub-value="value" no-separator no-hover />
                <x-list-item
                    :item="['label' => 'Language', 'value' => strtoupper($channel->language)]"
                    value="label" sub-value="value" no-separator no-hover />
            </x-card>

            <x-card title="Streaming Guide" separator>
                <ol class="text-sm text-base-content/70 space-y-2 list-decimal list-inside">
                    <li>Open OBS or your encoder</li>
                    <li>Set server to the <strong class="text-base-content">RTMP Ingest URL</strong></li>
                    <li>Set stream key to your <strong class="text-base-content">Stream Key</strong></li>
                    <li>Start streaming — channel goes live automatically</li>
                </ol>
            </x-card>
        </div>
    </div>

    <x-confirm-modal />
</div>
