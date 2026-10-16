<div>
    <x-header title="Reference Data" subtitle="Lookup values offered in provider forms and the Thrive app" separator progress-indicator>
        <x-slot:actions>
            <x-button label="New Value" icon="o-plus" class="btn-primary" wire:click="create" responsive />
        </x-slot:actions>
    </x-header>

    <div role="tablist" class="tabs tabs-box mb-4 w-fit max-w-full overflow-x-auto flex-nowrap">
        @foreach($types as $type => [$typeLabel])
            <button type="button" role="tab" wire:key="tab-{{ $type }}"
                wire:click="$set('selectedType', '{{ $type }}')"
                @class(['tab whitespace-nowrap', 'tab-active' => $selectedType === $type])>
                {{ $typeLabel }}
            </button>
        @endforeach
    </div>

    <x-card :title="$types[$selectedType][0]" :subtitle="$types[$selectedType][1]" separator>
        <x-table :headers="$headers" :rows="$values">

            @scope('cell_sort_order', $row)
                <span class="text-xs text-base-content/40">{{ $row->sort_order }}</span>
            @endscope

            @scope('cell_label', $row)
                <div>
                    <span class="font-semibold text-sm">{{ $row->label }}</span>
                    @if($row->is_system)
                        <x-badge value="System" class="badge-info badge-xs ml-1" />
                    @endif
                    <span class="block text-xs text-base-content/40 font-mono">{{ $row->code }}</span>
                    @if($row->description)
                        <span class="block text-xs text-base-content/60">{{ $row->description }}</span>
                    @endif
                </div>
            @endscope

            @scope('cell_usage', $row, $usage)
                <span class="text-sm">{{ $usage[$row->code] ?? 0 }}</span>
            @endscope

            @scope('cell_is_active', $row)
                <x-badge :value="$row->is_active ? 'Active' : 'Hidden'" :class="$row->is_active ? 'badge-success' : 'badge-ghost'" />
            @endscope

            @scope('cell_actions', $row, $usage)
                <div class="flex gap-1 justify-end">
                    <x-button label="Edit" class="btn-ghost btn-xs" wire:click="edit('{{ $row->id }}')" />
                    @unless($row->is_system)
                        <x-button :label="$row->is_active ? 'Hide' : 'Show'"
                            :class="($row->is_active ? 'btn-warning' : 'btn-success') . ' btn-xs'"
                            wire:click="toggleActive('{{ $row->id }}')" spinner />
                        @if(($usage[$row->code] ?? 0) === 0)
                            <x-button icon="o-trash" class="btn-ghost btn-xs text-error"
                                wire:click="delete('{{ $row->id }}')"
                                wire:confirm="Delete {{ $row->label }}? This cannot be undone." spinner />
                        @endif
                    @endunless
                </div>
            @endscope

            <x-slot:empty>
                <x-icon name="o-queue-list" label="No values defined yet." />
            </x-slot:empty>
        </x-table>
    </x-card>

    <x-modal wire:model="showModal" :title="($editingId ? 'Edit ' : 'New ') . Str::singular(strtolower($types[$selectedType][0]))">
        <x-form wire:submit="save">
            <x-input label="Label" wire:model.live.debounce="label" placeholder="Shown to providers and subscribers" />

            <x-input label="Code" wire:model="code" :readonly="(bool) $editingId" class="font-mono"
                :hint="$editingId ? 'Codes are stored on existing records and cannot be changed.' : 'Lowercase letters, numbers and underscores. Cannot be changed later.'" />

            <x-input label="Description" wire:model="description" hint="Optional — admin reference only" />

            <div class="grid grid-cols-2 gap-4 items-end">
                <x-input label="Sort order" wire:model="sort_order" type="number" min="0" hint="Lower numbers list first" />
                @php $isSystem = $editingId && $values->firstWhere('id', $editingId)?->is_system; @endphp
                <x-toggle label="Available for selection" wire:model="is_active" :disabled="$isSystem" />
            </div>

            <x-slot:actions>
                <x-button label="Cancel" wire:click="$set('showModal', false)" />
                <x-button label="Save" type="submit" icon="o-check" class="btn-primary" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
