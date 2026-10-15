@php
    $toOptions = fn (array $map) => collect($map)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values()->all();
    $documents = $provider->kycDocuments->keyBy('type');
    $channel   = $provider->channels->first();
@endphp

<div class="flex flex-col gap-6 max-w-4xl">
    <x-header title="Verification" subtitle="Set up your channel and verify your organisation" separator class="!mb-0" />

    {{-- ───────────── Approved earlier, access since revoked ───────────── --}}
    @if($provider->kyc_status === 'approved')
        <x-card class="text-center py-6">
            <div class="w-16 h-16 mx-auto rounded-full bg-error/10 flex items-center justify-center">
                <x-icon name="o-no-symbol" class="w-8 h-8 text-error" />
            </div>
            <h1 class="text-2xl font-bold mt-4">Account suspended</h1>
            <p class="text-base-content/60 mt-2 max-w-md mx-auto">
                Your provider access for <strong>{{ $provider->company_name }}</strong> has been suspended by an administrator.
                Please contact Thrive support to resolve this.
            </p>
        </x-card>

    {{-- ───────────── Submitted, waiting for an admin ───────────── --}}
    @elseif($provider->kycAwaitingReview())
        <div class="text-center">
            <div class="w-20 h-20 mx-auto rounded-full bg-warning/10 flex items-center justify-center">
                <x-icon name="o-clock" class="w-10 h-10 text-warning" />
            </div>
            <h1 class="text-2xl font-bold mt-4">Verification under review</h1>
            <p class="text-base-content/60 mt-2 max-w-md mx-auto">
                Thank you, <strong>{{ $provider->contact_person ?? auth()->user()->name }}</strong>.
                We received your documents for <strong>{{ $provider->company_name }}</strong>
                on {{ $provider->kyc_submitted_at?->format('d M Y, H:i') }}.
            </p>
        </div>

        <x-card title="Onboarding progress" separator>
            <ul class="space-y-3 text-sm">
                @foreach([
                    ['Account created', true],
                    ['Organisation & channel details submitted', true],
                    ['Identity and supporting documents uploaded', true],
                    ['Compliance review by the Thrive team', false],
                    ['Channel verified — stream credentials issued', false],
                ] as [$label, $done])
                    <li class="flex items-center gap-3">
                        <x-icon :name="$done ? 'o-check-circle' : 'o-clock'"
                            @class(['w-5 h-5 shrink-0', 'text-success' => $done, 'text-warning' => ! $done]) />
                        <span @class(['text-base-content/60' => ! $done])>{{ $label }}</span>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <p class="text-sm text-center text-base-content/50">
            We'll email <strong>{{ auth()->user()->email }}</strong> when the review is complete. This usually takes 1–2 business days.
        </p>

    {{-- ───────────── KYC wizard (new or rejected) ───────────── --}}
    @else
        <x-alert icon="o-shield-check" class="bg-base-100 border-base-300">
            <div>
                <p class="font-semibold">Verify your organisation</p>
                <p class="text-sm text-base-content/60">
                    Videos, playlists and stream setup unlock once our team approves your verification.
                    Your progress is saved at every step, so you can come back any time.
                </p>
            </div>
        </x-alert>

        @if($provider->kyc_status === 'rejected')
            <x-alert icon="o-exclamation-triangle" class="alert-error">
                <div>
                    <p class="font-semibold">Your previous submission was not approved</p>
                    <p class="text-sm mt-1">{{ $provider->kyc_rejection_reason }}</p>
                    <p class="text-sm mt-1 opacity-80">Update the relevant details or documents below and submit again.</p>
                </div>
            </x-alert>
        @endif

        <ul class="steps w-full text-xs">
            @foreach($steps as $n => $label)
                <li class="step {{ $step >= $n ? 'step-primary' : '' }} {{ $n < $step ? 'cursor-pointer' : '' }}"
                    @if($n < $step) wire:click="goToStep({{ $n }})" @endif>{{ $label }}</li>
            @endforeach
        </ul>

        <x-card>
            {{-- Step 1: Organisation --}}
            @if($step === 1)
                <x-header title="Organisation details" subtitle="As they appear on your registration documents" size="text-lg" class="!mb-4" />
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-input label="Organisation / legal name" wire:model="company_name" class="sm:col-span-2" required />
                    <x-select label="Organisation type" wire:model.live="organisation_type" :options="$toOptions($organisationTypes)"
                        placeholder="Select type" required />
                    @if($organisation_type !== 'individual')
                        <x-input label="Registration number" wire:model="registration_number"
                            hint="Registrar General / NGO Board / Ministry number" required />
                    @endif
                    <x-input label="Primary contact person" wire:model="contact_person" required />
                    <x-input label="Contact phone" wire:model="contact_phone" placeholder="+265 999 000 000" required />
                    <x-input label="Physical address" wire:model="physical_address" class="sm:col-span-2" required />
                    <x-input label="District / City" wire:model="district" placeholder="Lilongwe" required />
                </div>
            @endif

            {{-- Step 2: Channel --}}
            @if($step === 2)
                <x-header title="Your channel" subtitle="How subscribers will find you in the Thrive app" size="text-lg" class="!mb-4" />
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-input label="Channel name" wire:model="channel_name" placeholder="Faith TV" class="sm:col-span-2" required />
                    <x-select label="Category" wire:model="channel_category" :options="$toOptions($categories)"
                        placeholder="Select a category" required />
                    <x-select label="Primary language" wire:model="channel_language" :options="$toOptions($languages)" required />
                    <x-textarea label="Description" wire:model="channel_description" rows="3" class="sm:col-span-2"
                        placeholder="What will subscribers watch on your channel?" hint="Optional · up to 500 characters" />
                </div>
            @endif

            {{-- Step 3: Identity --}}
            @if($step === 3)
                <x-header title="Representative identity" subtitle="Government ID of the person responsible for this account" size="text-lg" class="!mb-4" />
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-select label="ID type" wire:model="id_type" :options="$toOptions($idTypes)" placeholder="Select ID type" required />
                    <x-input label="ID number" wire:model="id_number" required />

                    @foreach(['id_front' => true, 'id_back' => false] as $type => $required)
                        @include('livewire.provider.partials.kyc-upload', [
                            'type' => $type, 'label' => $documentTypes[$type], 'required' => $required,
                            'existing' => $documents->get($type), 'upload' => $$type,
                        ])
                    @endforeach
                </div>
            @endif

            {{-- Step 4: Supporting documents --}}
            @if($step === 4)
                <x-header title="Supporting documents" subtitle="PDF, JPG or PNG — max 5 MB each" size="text-lg" class="!mb-4" />
                <div class="grid sm:grid-cols-2 gap-4">
                    @if(! $provider->isIndividual())
                        @include('livewire.provider.partials.kyc-upload', [
                            'type' => 'registration_certificate', 'label' => $documentTypes['registration_certificate'],
                            'required' => true, 'existing' => $documents->get('registration_certificate'),
                            'upload' => $registration_certificate,
                        ])
                    @endif
                    @include('livewire.provider.partials.kyc-upload', [
                        'type' => 'proof_of_address', 'label' => $documentTypes['proof_of_address'],
                        'required' => true, 'existing' => $documents->get('proof_of_address'),
                        'upload' => $proof_of_address,
                        'hint' => 'Utility bill or bank statement from the last 3 months',
                    ])
                </div>
            @endif

            {{-- Step 5: Payouts --}}
            @if($step === 5)
                <x-header title="Payout details" subtitle="Where your 80% revenue share is paid each billing period" size="text-lg" class="!mb-4" />
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-input label="Mobile money number" wire:model="mobile_money_number" placeholder="+265 999 000 000"
                        hint="MTN or Airtel Money, registered to the organisation or representative" required />
                    <x-input label="Bank account (optional)" wire:model="bank_account" placeholder="Bank, branch, account no."
                        hint="Used for large payouts" />
                </div>
            @endif

            {{-- Step 6: Review --}}
            @if($step === 6)
                <x-header title="Review & submit" size="text-lg" class="!mb-4" />

                <div class="grid sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    @foreach([
                        ['Organisation',  $provider->company_name],
                        ['Type',          $organisationTypes[$provider->organisation_type] ?? '—'],
                        ['Registration',  $provider->registration_number ?: '—'],
                        ['Contact',       trim(($provider->contact_person ?? '').' · '.($provider->contact_phone ?? ''), ' ·')],
                        ['Address',       trim(($provider->physical_address ?? '').', '.($provider->district ?? ''), ', ')],
                        ['ID',            ($idTypes[$provider->id_type] ?? '—').' · '.($provider->id_number ?? '—')],
                        ['Mobile money',  $provider->mobile_money_number ?? '—'],
                        ['Channel',       $channel ? $channel->name.' · '.($categories[$channel->category] ?? ucfirst((string) $channel->category)).' · '.($languages[$channel->language] ?? strtoupper($channel->language)) : '—'],
                    ] as [$label, $value])
                        <div>
                            <p class="text-xs uppercase tracking-wide text-base-content/50">{{ $label }}</p>
                            <p class="font-medium">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="divider"></div>

                <p class="text-xs uppercase tracking-wide text-base-content/50 mb-2">Documents</p>
                <ul class="space-y-2 text-sm">
                    @foreach($provider->requiredKycDocuments() as $type)
                        <li class="flex items-center gap-2">
                            <x-icon :name="$documents->has($type) ? 'o-check-circle' : 'o-x-circle'"
                                @class(['w-5 h-5', 'text-success' => $documents->has($type), 'text-error' => ! $documents->has($type)]) />
                            {{ $documentTypes[$type] }}
                            @if($documents->has($type))
                                <span class="text-base-content/40 truncate">— {{ $documents[$type]->original_name }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>

                @if($this->missingItems !== [])
                    <x-alert icon="o-exclamation-circle" class="alert-warning mt-4 text-sm"
                        title="Still missing: {{ implode(', ', $this->missingItems) }}" />
                @endif

                <div class="mt-6">
                    <x-checkbox wire:model="declaration"
                        label="I confirm that the information and documents provided are true, and that I am authorised to act on behalf of this organisation." />
                </div>
            @endif

            <x-slot:actions>
                @if($step > 1)
                    <x-button label="Back" icon="o-arrow-left" wire:click="prevStep" class="btn-ghost" />
                @endif
                @if($step < count($steps))
                    <x-button label="Save & continue" icon-right="o-arrow-right" wire:click="nextStep" class="btn-primary" spinner="nextStep" />
                @else
                    <x-button label="Submit for verification" icon-right="o-paper-airplane" wire:click="submit"
                        class="btn-primary" spinner="submit" :disabled="$this->missingItems !== []" />
                @endif
            </x-slot:actions>
        </x-card>

        <p class="text-xs text-center text-base-content/40">
            <x-icon name="o-lock-closed" class="w-3 h-3 inline" />
            Your documents are stored privately and are only visible to the Thrive compliance team.
        </p>
    @endif
</div>
