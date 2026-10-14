<x-modal wire:model="showPreview" :title="$this->previewTitle" :subtitle="$this->previewSubtitle" box-class="max-w-5xl" separator>
    @if($this->showPreview && $this->previewUrl)
        <div class="relative w-full aspect-video rounded-lg overflow-hidden bg-black">
            <iframe wire:key="{{ $this->previewUrl }}" src="{{ $this->previewUrl }}" loading="lazy"
                class="absolute inset-0 w-full h-full border-0"
                allow="accelerometer; gyroscope; autoplay; encrypted-media; picture-in-picture; fullscreen"
                allowfullscreen></iframe>
        </div>
    @endif
</x-modal>
