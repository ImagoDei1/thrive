<div>
    <x-header title="{{ $channel->name }}" subtitle="Channel Information" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Stream Setup" icon="o-signal" link="{{ route('provider.channels.setup', $channel) }}" wire:navigate class="btn-primary" />
            <x-button label="Dashboard" icon="o-home" link="{{ route('provider.dashboard') }}" wire:navigate class="btn-ghost" />
        </x-slot:actions>
    </x-header>

    <div class="grid lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">

            {{-- Read-only channel details --}}
            <x-card title="Channel Details" separator>
                <x-slot:menu>
                    <x-button label="Request Change" icon="o-pencil-square" wire:click="openRequest" class="btn-ghost btn-sm" />
                </x-slot:menu>

                @foreach([
                    ['name',        'Channel Name',  $channel->name],
                    ['description', 'Description',   $channel->description ?? '—'],
                    ['category',    'Category',       ucfirst($channel->category ?? '—')],
                    ['language',    'Language',       strtoupper($channel->language)],
                ] as [$field, $label, $value])
                    <x-list-item :item="['label' => $label, 'value' => $value]" value="value" sub-value="label" no-separator>
                        <x-slot:actions>
                            @if(in_array($field, $pendingRequests))
                                <x-badge value="Pending review" class="badge-warning badge-sm" />
                            @else
                                <x-button icon="o-pencil" wire:click="openRequest('{{ $field }}')"
                                    class="btn-ghost btn-xs" tooltip="Request change" />
                            @endif
                        </x-slot:actions>
                    </x-list-item>
                @endforeach
            </x-card>

            {{-- Change request history --}}
            @if($recentRequests->isNotEmpty())
                <x-card title="Change Request History" separator>
                    @foreach($recentRequests as $req)
                        <x-list-item
                            :item="['label' => $req->fieldLabel(), 'value' => $req->requested_value]"
                            value="value" sub-value="label" no-separator>
                            <x-slot:actions>
                                <x-badge :value="ucfirst($req->status)" :class="
                                    $req->status === 'pending'  ? 'badge-warning' :
                                   ($req->status === 'approved' ? 'badge-success' : 'badge-error')" />
                            </x-slot:actions>
                        </x-list-item>
                        @if($req->admin_note)
                            <p class="text-xs text-base-content/50 pl-4 pb-2">Admin: {{ $req->admin_note }}</p>
                        @endif
                    @endforeach
                </x-card>
            @endif
        </div>

        <div class="space-y-6">

            {{-- Logo --}}
            <x-card title="Channel Logo" separator>
                <div class="flex flex-col items-center gap-4">
                    @if($channel->logo_url)
                        <img src="{{ $channel->logo_url }}" alt="{{ $channel->name }} logo"
                            class="w-28 h-28 rounded-2xl object-cover ring-2 ring-base-300" />
                    @else
                        <div class="w-28 h-28 rounded-2xl bg-base-200 flex items-center justify-center ring-2 ring-base-300">
                            <x-icon name="o-tv" class="w-10 h-10 text-base-content/20" />
                        </div>
                    @endif

                    <x-form wire:submit="uploadLogo" class="w-full">
                        <x-file wire:model="logo" accept="image/*" hide-errors>
                            <div class="flex flex-col items-center gap-1 py-2 text-base-content/50 hover:text-base-content transition-colors cursor-pointer">
                                <x-icon name="o-arrow-up-tray" class="w-5 h-5" />
                                <span class="text-xs">{{ $channel->logo_url ? 'Replace logo' : 'Upload logo' }}</span>
                                <span class="text-xs text-base-content/30">PNG, JPG up to 2 MB</span>
                            </div>
                        </x-file>

                        @if($logo)
                            <div class="flex items-center justify-center gap-3">
                                <img src="{{ $logo->temporaryUrl() }}" class="w-12 h-12 rounded-xl object-cover" alt="Preview" />
                                <div class="flex gap-2">
                                    <x-button label="Save" type="submit" class="btn-primary btn-sm" spinner="uploadLogo" />
                                    <x-button label="Cancel" wire:click="$set('logo', null)" class="btn-ghost btn-sm" />
                                </div>
                            </div>
                        @endif
                    </x-form>
                </div>
            </x-card>

            <x-card title="Status" separator>
                <x-list-item :item="['k' => 'Visibility']" value="k" no-separator no-hover>
                    <x-slot:actions>
                        <x-badge :value="$channel->is_active ? 'Active' : 'Inactive'"
                            :class="$channel->is_active ? 'badge-success' : 'badge-ghost'" />
                    </x-slot:actions>
                </x-list-item>
                <x-list-item :item="['k' => 'Live Now']" value="k" no-separator no-hover>
                    <x-slot:actions>
                        @if($channel->is_live)
                            <x-badge value="LIVE" class="badge-error animate-pulse" />
                        @else
                            <span class="text-sm text-base-content/30">Offline</span>
                        @endif
                    </x-slot:actions>
                </x-list-item>
            </x-card>

            <x-card title="Quick Links" separator>
                <div class="flex flex-col gap-2">
                    <x-button label="Stream Setup" icon="o-signal"
                        link="{{ route('provider.channels.setup', $channel) }}" wire:navigate class="btn-outline btn-sm justify-start" />
                    <x-button label="Videos" icon="o-film"
                        link="{{ route('provider.content.index', $channel) }}" wire:navigate class="btn-outline btn-sm justify-start" />
                    <x-button label="Payout History" icon="o-banknotes"
                        link="{{ route('provider.payouts.index') }}" wire:navigate class="btn-outline btn-sm justify-start" />
                </div>
            </x-card>
        </div>
    </div>

    {{-- Change request modal --}}
    <x-modal wire:model="showRequestForm" title="Request Channel Change">
        <x-form wire:submit="submitRequest">
            <x-select label="Field to change" wire:model.live="requestField"
                :options="$editableFields" option-value="id" option-label="name"
                placeholder="Select field..." native />

            @if($requestField)
                <div class="bg-base-200 rounded-lg p-3 text-sm">
                    <p class="text-base-content/50 text-xs mb-1">Current value</p>
                    <p class="font-medium">
                        {{ match($requestField) {
                            'language' => strtoupper($channel->language),
                            'category' => ucfirst($channel->category ?? '—'),
                            default    => $channel->{$requestField} ?? '—'
                        } }}
                    </p>
                </div>

                @if(in_array($requestField, ['description']))
                    <x-textarea label="New value" wire:model="requestedValue" rows="3" />
                @else
                    <x-input label="New value" wire:model="requestedValue" />
                @endif
            @endif

            <x-slot:actions>
                <x-button label="Cancel" wire:click="$set('showRequestForm', false)" />
                <x-button label="Submit Request" type="submit" class="btn-primary" spinner="submitRequest" />
            </x-slot:actions>
        </x-form>
    </x-modal>
</div>
