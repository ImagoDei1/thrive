<div>
    <x-header title="New Channel" subtitle="Set up your streaming channel" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Back" icon="o-arrow-left" link="{{ route('provider.dashboard') }}" class="btn-ghost" wire:navigate />
        </x-slot:actions>
    </x-header>

    <div class="max-w-2xl">
        <x-card>
            <x-form wire:submit="save">
                <x-input label="Channel Name" wire:model="name" placeholder="e.g. Grace Church Live" required />
                <x-textarea label="Description" wire:model="description" placeholder="Brief description of your channel..." rows="3" />
                <x-select label="Category" wire:model="category" :options="$categories"
                    option-value="id" option-label="name" placeholder="Select category" native required />
                <x-select label="Language" wire:model="language" :options="$languages"
                    option-value="id" option-label="name" native required />

                <x-slot:actions>
                    <x-button label="Cancel" link="{{ route('provider.dashboard') }}" wire:navigate />
                    <x-button label="Create Channel" type="submit" icon="o-check" class="btn-primary" />
                </x-slot:actions>
            </x-form>
        </x-card>
    </div>
</div>
