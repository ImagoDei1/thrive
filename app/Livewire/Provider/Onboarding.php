<?php

declare(strict_types=1);

namespace App\Livewire\Provider;

use App\Models\ProviderKycDocument;
use App\Models\ProviderProfile;
use App\Models\ReferenceValue;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Mary\Traits\Toast;

#[Title('Verification')]
class Onboarding extends Component
{
    use Toast, WithFileUploads;

    public const STEPS = [
        1 => 'Organisation',
        2 => 'Channel',
        3 => 'Identity',
        4 => 'Documents',
        5 => 'Payouts',
        6 => 'Review',
    ];

    private const FILE_RULES = ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];

    public int $step = 1;

    // Step 1 — organisation
    public string $company_name = '';
    public string $organisation_type = '';
    public string $registration_number = '';
    public string $contact_person = '';
    public string $contact_phone = '';
    public string $physical_address = '';
    public string $district = '';

    // Step 2 — channel
    public string $channel_name = '';
    public string $channel_description = '';
    public string $channel_category = '';
    public string $channel_language = 'en';

    // Step 3 — representative identity
    public string $id_type = '';
    public string $id_number = '';
    public ?TemporaryUploadedFile $id_front = null;
    public ?TemporaryUploadedFile $id_back = null;

    // Step 4 — supporting documents
    public ?TemporaryUploadedFile $registration_certificate = null;
    public ?TemporaryUploadedFile $proof_of_address = null;

    // Step 5 — payouts
    public string $mobile_money_number = '';
    public string $bank_account = '';

    // Step 6 — declaration
    public bool $declaration = false;

    public function mount(): void
    {
        $provider = $this->provider();

        if ($provider->is_approved) {
            $this->redirect(route('provider.dashboard'), navigate: true);
            return;
        }

        $this->fill(array_map('strval', $provider->only([
            'company_name', 'organisation_type', 'registration_number', 'contact_person',
            'contact_phone', 'physical_address', 'district', 'id_type', 'id_number',
            'mobile_money_number', 'bank_account',
        ])));

        if ($channel = $provider->channels->first()) {
            $this->channel_name        = $channel->name;
            $this->channel_description = (string) $channel->description;
            $this->channel_category    = (string) $channel->category;
            $this->channel_language    = $channel->language;
        }
    }

    #[Computed]
    public function provider(): ProviderProfile
    {
        return auth()->user()->providerProfile()->with(['kycDocuments', 'channels'])->firstOrFail();
    }

    public function nextStep(): void
    {
        abort_unless($this->provider()->kycEditable(), 403);

        match ($this->step) {
            1 => $this->saveOrganisation(),
            2 => $this->saveChannel(),
            3 => $this->saveIdentity(),
            4 => $this->saveDocuments(),
            5 => $this->savePayouts(),
            default => null,
        };

        unset($this->provider);
        $this->step = min($this->step + 1, count(self::STEPS));
    }

    public function prevStep(): void
    {
        $this->step = max($this->step - 1, 1);
    }

    public function goToStep(int $step): void
    {
        // Only allow jumping back to steps already completed.
        if ($step >= 1 && $step < $this->step) {
            $this->step = $step;
        }
    }

    public function submit(): void
    {
        $provider = $this->provider();
        abort_unless($provider->kycEditable(), 403);

        $this->validate(['declaration' => ['accepted']], [
            'declaration.accepted' => 'You must confirm the declaration before submitting.',
        ]);

        $missing = $this->missingItems();
        if ($missing !== []) {
            $this->error('Your application is incomplete: ' . implode(', ', $missing) . '.');
            return;
        }

        $provider->update([
            'kyc_status'       => 'submitted',
            'kyc_submitted_at' => now(),
        ]);

        // Full redirect so the sidebar's verification badge reflects the new status.
        $this->success('Application submitted for review.', redirectTo: route('provider.onboarding'));
    }

    /** @return list<string> */
    #[Computed]
    public function missingItems(): array
    {
        $provider = $this->provider();
        $missing = [];

        foreach (['organisation_type' => 'organisation type', 'physical_address' => 'physical address',
                  'district' => 'district', 'id_type' => 'ID type', 'id_number' => 'ID number',
                  'mobile_money_number' => 'mobile money number'] as $field => $label) {
            if (blank($provider->{$field})) {
                $missing[] = $label;
            }
        }

        if (! $provider->isIndividual() && blank($provider->registration_number)) {
            $missing[] = 'registration number';
        }

        if ($provider->channels->isEmpty()) {
            $missing[] = 'channel details';
        }

        $uploaded = $provider->kycDocuments->pluck('type')->all();
        foreach ($provider->requiredKycDocuments() as $type) {
            if (! in_array($type, $uploaded, true)) {
                $missing[] = strtolower(ProviderKycDocument::TYPES[$type]);
            }
        }

        return $missing;
    }

    private function saveOrganisation(): void
    {
        $validated = $this->validate([
            'company_name'        => ['required', 'string', 'max:200'],
            'organisation_type'   => ['required', ReferenceValue::rule(ReferenceValue::ORGANISATION_TYPE)],
            'registration_number' => [Rule::requiredIf($this->organisation_type !== 'individual'), 'nullable', 'string', 'max:100'],
            'contact_person'      => ['required', 'string', 'max:200'],
            'contact_phone'       => ['required', 'string', 'max:20'],
            'physical_address'    => ['required', 'string', 'max:255'],
            'district'            => ['required', 'string', 'max:100'],
        ], [
            'registration_number.required' => 'A registration number is required for organisations.',
        ]);

        $this->provider()->update($validated);
    }

    private function saveChannel(): void
    {
        $this->validate([
            'channel_name'        => ['required', 'string', 'min:3', 'max:100'],
            'channel_description' => ['nullable', 'string', 'max:500'],
            'channel_category'    => ['required', ReferenceValue::rule(ReferenceValue::CHANNEL_CATEGORY)],
            'channel_language'    => ['required', ReferenceValue::rule(ReferenceValue::CHANNEL_LANGUAGE)],
        ]);

        $attributes = [
            'name'        => $this->channel_name,
            'description' => $this->channel_description ?: null,
            'category'    => $this->channel_category,
            'language'    => $this->channel_language,
        ];

        $provider = $this->provider();

        // Before approval the channel is part of the application and can be edited
        // freely; afterwards changes go through admin change requests.
        if ($channel = $provider->channels->first()) {
            $channel->update($attributes);
            return;
        }

        $slug = Str::slug($this->channel_name) . '-' . Str::random(4);

        $provider->channels()->create($attributes + [
            'slug'             => $slug,
            'rtmp_key'         => Str::random(32),
            'rtmp_ingest_url'  => config('streaming.rtmp_ingest_url'),
            'hls_playback_url' => route('hls.playback', ['slug' => $slug, 'file' => 'index.m3u8']),
            'is_active'        => false,
        ]);
    }

    private function saveIdentity(): void
    {
        $hasFront = $this->hasDocument('id_front');

        $this->validate([
            'id_type'   => ['required', ReferenceValue::rule(ReferenceValue::ID_TYPE)],
            'id_number' => ['required', 'string', 'max:50'],
            'id_front'  => [$hasFront ? 'nullable' : 'required', ...self::FILE_RULES],
            'id_back'   => ['nullable', ...self::FILE_RULES],
        ], [
            'id_front.required' => 'Please upload the front of your ID document.',
        ]);

        $this->provider()->update(['id_type' => $this->id_type, 'id_number' => $this->id_number]);

        $this->storeDocument('id_front');
        $this->storeDocument('id_back');
    }

    private function saveDocuments(): void
    {
        $needsCertificate = in_array('registration_certificate', $this->provider()->requiredKycDocuments(), true);

        $this->validate([
            'registration_certificate' => [
                $needsCertificate && ! $this->hasDocument('registration_certificate') ? 'required' : 'nullable',
                ...self::FILE_RULES,
            ],
            'proof_of_address' => [
                $this->hasDocument('proof_of_address') ? 'nullable' : 'required',
                ...self::FILE_RULES,
            ],
        ], [
            'registration_certificate.required' => 'Please upload your registration certificate.',
            'proof_of_address.required'         => 'Please upload a proof of address.',
        ]);

        $this->storeDocument('registration_certificate');
        $this->storeDocument('proof_of_address');
    }

    private function savePayouts(): void
    {
        $validated = $this->validate([
            'mobile_money_number' => ['required', 'string', 'regex:/^\+?[0-9 ]{9,20}$/'],
            'bank_account'        => ['nullable', 'string', 'max:255'],
        ], [
            'mobile_money_number.regex' => 'Enter a valid mobile money number, e.g. +265 999 000 000.',
        ]);

        $this->provider()->update($validated);
    }

    private function hasDocument(string $type): bool
    {
        return $this->provider()->kycDocuments->contains('type', $type);
    }

    private function storeDocument(string $type): void
    {
        $file = $this->{$type};
        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $provider = $this->provider();

        // Replacing a document removes the old file (see ProviderKycDocument::booted).
        $provider->kycDocuments()->where('type', $type)->get()->each->delete();

        // Read metadata before storing: on the same disk as livewire-tmp, store() moves the temp file away.
        $originalName = $file->getClientOriginalName();
        $mimeType     = $file->getMimeType();
        $size         = $file->getSize();

        $provider->kycDocuments()->create([
            'type'          => $type,
            'disk'          => 'local',
            'path'          => $file->store("kyc/{$provider->id}", 'local'),
            'original_name' => $originalName,
            'mime_type'     => $mimeType,
            'size'          => $size,
        ]);

        $this->{$type} = null;
    }

    public function render()
    {
        return view('livewire.provider.onboarding', [
            'provider'          => $this->provider(),
            'steps'             => self::STEPS,
            'organisationTypes' => ReferenceValue::options(ReferenceValue::ORGANISATION_TYPE),
            'idTypes'           => ReferenceValue::options(ReferenceValue::ID_TYPE),
            'documentTypes'     => ProviderKycDocument::TYPES,
            'categories'        => ReferenceValue::options(ReferenceValue::CHANNEL_CATEGORY),
            'languages'         => ReferenceValue::options(ReferenceValue::CHANNEL_LANGUAGE),
        ])->layout('components.provider-layout');
    }
}
