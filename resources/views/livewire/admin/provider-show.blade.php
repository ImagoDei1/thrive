@php
    $kycBadge = match($provider->kyc_status) {
        'approved'  => ['Approved', 'badge-success'],
        'submitted' => ['Awaiting review', 'badge-warning'],
        'rejected'  => ['Rejected', 'badge-error'],
        default     => ['Not started', 'badge-ghost'],
    };
@endphp

<div wire:poll.10s>
    <x-header title="{{ $provider->company_name }}" subtitle="Provider Profile" separator progress-indicator>
        <x-slot:actions>
            <x-button label="Back" icon="o-arrow-left" link="{{ route('admin.providers.index') }}" class="btn-ghost" wire:navigate />
            @if($provider->kycAwaitingReview())
                <x-button label="Reject KYC" icon="o-x-mark" wire:click="openReject" class="btn-error btn-outline" />
                <x-button label="Approve KYC" icon="o-check" wire:click="confirmApprove" class="btn-success" />
            @elseif($provider->is_approved)
                <x-button label="Revoke" icon="o-x-mark" wire:click="confirmRevoke" class="btn-error btn-outline" />
            @elseif($provider->kyc_status === 'approved')
                <x-button label="Reinstate" icon="o-arrow-path" wire:click="confirmReinstate" class="btn-success" />
            @endif
        </x-slot:actions>
    </x-header>

    @if($provider->kycAwaitingReview())
        <x-alert icon="o-shield-exclamation" class="alert-warning mb-6"
            title="KYC submitted {{ $provider->kyc_submitted_at?->diffForHumans() }} — review the details and documents below." />
    @endif

    <div class="grid lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-6">
            <x-card title="Company Details" separator>
                @foreach([
                    ['Company Name',   $provider->company_name],
                    ['Contact Person', $provider->contact_person ?? '—'],
                    ['Phone',          $provider->contact_phone  ?? '—'],
                    ['Email',          $provider->user->email],
                ] as [$label, $value])
                    <x-list-item :item="['label' => $label, 'value' => $value]" value="value" sub-value="label" no-separator />
                @endforeach
                @if($provider->description)
                    <div class="pt-2 px-2">
                        <p class="text-xs font-medium text-base-content/50 uppercase tracking-wide mb-1">Description</p>
                        <p class="text-sm">{{ $provider->description }}</p>
                    </div>
                @endif
            </x-card>

            <x-card title="KYC Verification" separator>
                <x-slot:menu>
                    <x-badge :value="$kycBadge[0]" :class="$kycBadge[1]" />
                </x-slot:menu>

                @if($provider->kyc_status === 'not_started')
                    <p class="text-sm text-base-content/40 px-2 py-4">The provider has not completed the KYC form yet.</p>
                @else
                    <div class="grid sm:grid-cols-2 gap-x-8 gap-y-3 text-sm px-2">
                        @foreach([
                            ['Organisation type',   $organisationTypes[$provider->organisation_type] ?? '—'],
                            ['Registration number', $provider->registration_number ?: '—'],
                            ['Physical address',    $provider->physical_address ?? '—'],
                            ['District / City',     $provider->district ?? '—'],
                            ['ID',                  ($idTypes[$provider->id_type] ?? '—').' · '.($provider->id_number ?? '—')],
                            ['Mobile money',        $provider->mobile_money_number ?? '—'],
                            ['Bank account',        $provider->bank_account ?: '—'],
                            ['Submitted',           $provider->kyc_submitted_at?->format('d M Y, H:i') ?? '—'],
                        ] as [$label, $value])
                            <div>
                                <p class="text-xs uppercase tracking-wide text-base-content/50">{{ $label }}</p>
                                <p class="font-medium">{{ $value }}</p>
                            </div>
                        @endforeach
                    </div>

                    <p class="text-xs uppercase tracking-wide text-base-content/50 mt-6 mb-1 px-2">Documents</p>
                    @forelse($provider->kycDocuments as $document)
                        <x-list-item :item="$document" no-separator>
                            <x-slot:avatar>
                                <x-icon :name="$document->isImage() ? 'o-photo' : 'o-document-text'" class="w-6 h-6 text-base-content/50" />
                            </x-slot:avatar>
                            <x-slot:value>{{ $document->label() }}</x-slot:value>
                            <x-slot:sub-value>{{ $document->original_name }} · {{ number_format($document->size / 1024, 0) }} KB</x-slot:sub-value>
                            <x-slot:actions>
                                <x-button label="View" icon="o-arrow-top-right-on-square" class="btn-ghost btn-sm"
                                    link="{{ route('admin.kyc-documents.show', $document) }}" external />
                            </x-slot:actions>
                        </x-list-item>
                    @empty
                        <p class="text-sm text-base-content/40 px-2 py-2">No documents uploaded.</p>
                    @endforelse

                    @if($provider->kyc_reviewed_at)
                        <p class="text-xs text-base-content/50 mt-4 px-2">
                            Last reviewed {{ $provider->kyc_reviewed_at->format('d M Y, H:i') }}
                            @if($provider->kycReviewer) by {{ $provider->kycReviewer->name }} @endif
                        </p>
                    @endif
                    @if($provider->kyc_status === 'rejected' && $provider->kyc_rejection_reason)
                        <x-alert icon="o-x-circle" class="alert-error mt-3 text-sm" :title="'Rejection reason: '.$provider->kyc_rejection_reason" />
                    @endif
                @endif
            </x-card>

            <x-card title="Channels ({{ $provider->channels->count() }})" separator>
                @forelse($provider->channels as $channel)
                    <x-list-item :item="$channel" value="name"
                        :sub-value="ucfirst($channel->category ?? '—').' · '.strtoupper($channel->language)"
                        no-separator>
                        <x-slot:actions>
                            <x-badge :value="$channel->isVerified() ? 'Verified' : 'Unverified'"
                                :class="$channel->isVerified() ? 'badge-info' : 'badge-ghost'" />
                            <x-badge :value="$channel->is_active ? 'Active' : 'Inactive'"
                                :class="$channel->is_active ? 'badge-success' : 'badge-ghost'" />
                            @if($channel->is_live)
                                <x-badge value="Live" class="badge-error animate-pulse" />
                            @endif
                            @if($provider->kyc_status === 'approved')
                                <x-button :icon="$channel->isVerified() ? 'o-shield-exclamation' : 'o-shield-check'"
                                    wire:click="toggleChannelVerification('{{ $channel->id }}')"
                                    class="btn-ghost btn-xs" spinner
                                    :tooltip="$channel->isVerified() ? 'Remove verification' : 'Verify channel'" />
                            @endif
                        </x-slot:actions>
                    </x-list-item>
                @empty
                    <p class="text-sm text-base-content/40 px-2 py-4">No channels yet.</p>
                @endforelse
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Status" separator>
                <x-list-item :item="['k' => 'Approval']" value="k" no-separator no-hover>
                    <x-slot:actions>
                        <x-badge :value="$provider->is_approved ? 'Approved' : 'Pending'"
                            :class="$provider->is_approved ? 'badge-success' : 'badge-warning'" />
                    </x-slot:actions>
                </x-list-item>
                <x-list-item :item="['k' => 'KYC']" value="k" no-separator no-hover>
                    <x-slot:actions>
                        <x-badge :value="$kycBadge[0]" :class="$kycBadge[1]" />
                    </x-slot:actions>
                </x-list-item>
                @if($provider->onboarded_at)
                    <x-list-item
                        :item="['k' => 'Onboarded', 'v' => $provider->onboarded_at->format('d M Y')]"
                        value="k" sub-value="v" no-separator no-hover />
                @endif
            </x-card>

            @php
                $feeStatus = $provider->setupFeeStatus();
                [$feeLabel, $feeClass] = match($feeStatus) {
                    'active'    => ['Active', 'badge-warning'],
                    'upcoming'  => ['Upcoming', 'badge-info'],
                    'completed' => ['Completed', 'badge-success'],
                    default     => ['None', 'badge-ghost'],
                };
                $fmtPeriod = fn (string $label) => \Carbon\Carbon::createFromFormat('!Y-m', $label)->format('M Y');
            @endphp
            <x-card title="Setup Fee" subtitle="Reduced revenue share instead of an upfront fee" separator>
                <x-slot:menu>
                    <x-badge :value="$feeLabel" :class="$feeClass" />
                </x-slot:menu>

                @if($provider->hasSetupFeeTerms())
                    <div class="space-y-2 text-sm px-2">
                        <div class="flex justify-between">
                            <span class="text-base-content/50">Withheld</span>
                            <span class="font-semibold">{{ rtrim(rtrim(number_format((float) $provider->setup_fee_percentage, 2), '0'), '.') }} percentage points</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/50">Billing periods</span>
                            <span class="font-medium">
                                {{ $fmtPeriod($provider->setup_fee_starts_period) }} – {{ $fmtPeriod($provider->setup_fee_ends_period) }}
                                ({{ $provider->setupFeeMonths() }} {{ Str::plural('month', $provider->setupFeeMonths()) }})
                            </span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-base-content/50">Withheld so far</span>
                            <span class="font-medium">MWK {{ number_format($provider->payouts->sum('setup_fee_mwk'), 2) }}</span>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-base-content/40 px-2 py-2">No setup fee — this provider receives the full provider share.</p>
                @endif

                <x-slot:actions>
                    <x-button label="Edit terms" icon="o-pencil" class="btn-ghost btn-sm" wire:click="openSetupFee" />
                </x-slot:actions>
            </x-card>

            <x-card title="Payout History" separator>
                @forelse($provider->payouts->take(5) as $payout)
                    <x-list-item
                        :item="['period' => $payout->billingPeriod->period_label, 'amount' => 'MWK '.number_format($payout->amount_mwk, 2)]"
                        value="period" sub-value="amount" no-separator />
                @empty
                    <p class="text-sm text-base-content/40 px-2 py-4">No payouts yet.</p>
                @endforelse
            </x-card>
        </div>
    </div>

    <x-modal wire:model="showRejectForm" title="Reject KYC submission" class="backdrop-blur">
        <x-form wire:submit="reject">
            <x-textarea label="Reason" wire:model="rejectionReason" rows="4"
                placeholder="e.g. The ID photo is blurry — please upload a clearer image of the front of the ID."
                hint="Shown to the provider and included in the email, so be specific about what to fix." />

            <x-slot:actions>
                <x-button label="Cancel" wire:click="$set('showRejectForm', false)" />
                <x-button label="Reject & notify" type="submit" class="btn-error" spinner="reject" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-modal wire:model="showSetupFeeForm" title="Setup-fee terms" class="backdrop-blur">
        <x-form wire:submit="saveSetupFee">
            <x-input label="Percentage points withheld" wire:model="setupFeePercentage" type="number" step="0.01" min="0"
                max="{{ config('revenue.setup_fee.max_percentage') }}" suffix="%"
                hint="E.g. 10 on an 80/20 split: the provider receives 70%, the platform keeps 30%." />

            <div class="grid grid-cols-2 gap-4">
                <x-input label="First billing period" wire:model="setupFeeStartsPeriod" type="month" />
                <x-input label="Months" wire:model="setupFeeMonths" type="number" min="0"
                    max="{{ config('revenue.setup_fee.max_months') }}"
                    hint="Max {{ config('revenue.setup_fee.max_months') }}. 0 removes the setup fee." />
            </div>

            <p class="text-xs text-base-content/50">
                Applies when payouts are calculated. Periods already calculated keep their figures until you recalculate them.
            </p>

            <x-slot:actions>
                <x-button label="Cancel" wire:click="$set('showSetupFeeForm', false)" />
                <x-button label="Save" type="submit" icon="o-check" class="btn-primary" spinner="saveSetupFee" />
            </x-slot:actions>
        </x-form>
    </x-modal>

    <x-confirm-modal />
</div>
