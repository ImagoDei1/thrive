<x-modal wire:model="showConfirm" :title="$this->confirmTitle" class="backdrop-blur">
    @if($this->confirmBody)
        <p class="text-base-content/70">{{ $this->confirmBody }}</p>
    @endif

    <x-slot:actions>
        <x-button label="Cancel" wire:click="$set('showConfirm', false)" />
        <x-button :label="$this->confirmLabel" wire:click="runConfirmed" :class="$this->confirmClass" spinner="runConfirmed" />
    </x-slot:actions>
</x-modal>
