<div data-bunny-uploader>
    <x-header title="Upload Videos" :subtitle="$channel->name" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Back to Videos" icon="o-arrow-left" class="btn-ghost" responsive
                link="{{ route('provider.content.index', $channel) }}" wire:navigate />
        </x-slot:actions>
    </x-header>

    <x-alert icon="o-information-circle" class="alert-info mb-6">
        Keep this page open while files upload. If your connection drops, uploads retry automatically,
        and failed files can be resumed with Retry. Videos are published once processing finishes.
    </x-alert>

    {{-- File picker: wire:ignore so Livewire never clears the browser's file selection. --}}
    <div wire:ignore class="mb-6">
        <label data-bunny-dropzone
            class="flex flex-col items-center justify-center gap-2 w-full border-2 border-dashed border-base-300 rounded-box p-10 cursor-pointer hover:border-primary hover:bg-base-200/50 transition">
            <x-icon name="o-cloud-arrow-up" class="w-10 h-10 text-base-content/40" />
            <span class="font-medium">Drop video files here or click to choose</span>
            <span class="text-xs text-base-content/50">
                Up to {{ \App\Livewire\Provider\ContentUpload::MAX_FILES }} files ·
                {{ round(config('services.bunny.max_upload_bytes') / 1024 ** 3, 1) }} GB max each · MP4, MOV, MKV and more
            </span>
            <input type="file" accept="video/*" multiple class="hidden" data-bunny-files />
        </label>
    </div>

    @if($uploads)
        <x-card>
            <x-slot:title>
                Queue
                <span class="text-sm font-normal text-base-content/50 ml-2">
                    {{ count($uploads) }} file(s)
                    @if($counts['uploading'] ?? 0) · {{ $counts['uploading'] }} uploading @endif
                    @if($counts['done'] ?? 0) · {{ $counts['done'] }} done @endif
                    @if($counts['failed'] ?? 0) · <span class="text-error">{{ $counts['failed'] }} failed</span> @endif
                </span>
            </x-slot:title>

            <x-form wire:submit="startAll" no-separator>
                @if($playlists->isNotEmpty())
                    <x-choices-offline label="Add these uploads to playlists" wire:model="playlistIds" :options="$playlists"
                        option-label="title" placeholder="None" searchable clearable
                        hint="Optional. Videos are added to the end; for a series, to its latest season." class="mb-2" />
                @endif

                <div class="divide-y divide-base-200">
                    @foreach($uploads as $key => $upload)
                        <div wire:key="upload-{{ $key }}" class="py-4 grid gap-3 md:grid-cols-12 items-start">
                            <div class="md:col-span-4 min-w-0">
                                <p class="font-medium truncate" title="{{ $upload['name'] }}">
                                    <x-icon name="o-film" class="w-4 h-4 inline mr-1 text-base-content/50" />{{ $upload['name'] }}
                                </p>
                                <p class="text-xs text-base-content/50">{{ \App\Livewire\Provider\ContentUpload::humanSize($upload['size']) }}</p>
                                <div class="mt-2">
                                    @switch($upload['status'])
                                        @case('pending')   <x-badge value="Ready to upload" class="badge-ghost badge-sm" /> @break
                                        @case('uploading') <x-badge value="Uploading" class="badge-info badge-sm" /> @break
                                        @case('done')      <x-badge value="Uploaded · processing" class="badge-success badge-sm" /> @break
                                        @case('failed')    <x-badge value="Failed" class="badge-error badge-sm" /> @break
                                    @endswitch
                                </div>
                                @if($upload['error'])
                                    <p class="text-xs text-error mt-1">{{ $upload['error'] }}</p>
                                @endif
                            </div>

                            <div class="md:col-span-6 space-y-2">
                                <x-input placeholder="Title" wire:model.blur="uploads.{{ $key }}.title"
                                    :disabled="in_array($upload['status'], ['uploading', 'done'])" />
                                <x-textarea placeholder="Description (optional)" rows="2" wire:model.blur="uploads.{{ $key }}.description"
                                    :disabled="in_array($upload['status'], ['uploading', 'done'])" />

                                <div wire:ignore class="{{ $upload['status'] === 'pending' ? 'hidden' : '' }}" data-bunny-row="{{ $key }}">
                                    <progress class="progress progress-primary w-full" value="{{ $upload['status'] === 'done' ? 100 : 0 }}" max="100" data-bunny-progress></progress>
                                    <p class="text-xs text-base-content/60" data-bunny-progress-text></p>
                                </div>
                            </div>

                            <div class="md:col-span-2 flex md:justify-end gap-1">
                                @if($upload['status'] === 'failed')
                                    <x-button icon="o-arrow-path" class="btn-sm btn-ghost" tooltip="Retry"
                                        wire:click="retry('{{ $key }}')" spinner="retry('{{ $key }}')" />
                                @endif
                                @if($upload['status'] !== 'uploading')
                                    <x-button icon="o-x-mark" class="btn-sm btn-ghost text-error" tooltip="Remove from queue"
                                        wire:click="remove('{{ $key }}')" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <x-slot:actions>
                    @if($counts['done'] ?? 0)
                        <x-button label="Clear finished" wire:click="clearFinished" class="btn-ghost" />
                    @endif
                    @if(($counts['pending'] ?? 0) + ($counts['failed'] ?? 0))
                        <x-button label="Upload {{ ($counts['pending'] ?? 0) + ($counts['failed'] ?? 0) }} file(s)"
                            type="submit" icon="o-arrow-up-tray" class="btn-primary" spinner="startAll" />
                    @endif
                </x-slot:actions>
            </x-form>
        </x-card>
    @endif
</div>
