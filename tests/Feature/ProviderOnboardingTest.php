<?php

declare(strict_types=1);

use App\Livewire\Admin\ProviderShow;
use App\Livewire\Provider\ChannelSetup;
use App\Livewire\Provider\Onboarding;
use App\Models\Channel;
use App\Models\ProviderKycDocument;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Notifications\ProviderKycApproved;
use App\Notifications\ProviderKycRejected;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

function kycUser(string $role): User
{
    // UserFactory sets email_verified_at, which the users table does not have.
    return User::create(Arr::except(User::factory()->{$role}()->raw(), ['email_verified_at', 'remember_token']));
}

function kycProvider(array $attrs = []): ProviderProfile
{
    $provider = ProviderProfile::create(array_merge([
        'user_id'        => kycUser('provider')->id,
        'company_name'   => 'Grace Church',
        'contact_person' => 'Jane Banda',
        'contact_phone'  => '+265999000000',
        'is_approved'    => false,
    ], $attrs));

    Channel::create([
        'provider_id' => $provider->id,
        'name'        => 'Grace TV',
        'slug'        => 'grace-' . Str::random(6),
        'rtmp_key'    => Str::random(32),
    ]);

    return $provider->fresh();
}

function submittedKycProvider(): ProviderProfile
{
    $provider = kycProvider([
        'kyc_status'          => 'submitted',
        'kyc_submitted_at'    => now(),
        'organisation_type'   => 'church',
        'registration_number' => 'RG-123',
    ]);

    return $provider;
}

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    Queue::fake(); // Approval dispatches ProvisionBunnyLibraryJob.
});

// ---------------------------------------------------------------------------
// Provider KYC wizard
// ---------------------------------------------------------------------------

it('walks a provider through KYC and submits for review', function () {
    $provider = kycProvider();

    Livewire::actingAs($provider->user)
        ->test(Onboarding::class)
        ->set('organisation_type', 'church')
        ->set('registration_number', 'RG-123')
        ->set('physical_address', 'Area 47, Plot 12')
        ->set('district', 'Lilongwe')
        ->call('nextStep')->assertHasNoErrors()->assertSet('step', 2)
        ->assertSet('channel_name', 'Grace TV')
        ->set('channel_category', 'church')
        ->call('nextStep')->assertHasNoErrors()->assertSet('step', 3)
        ->set('id_type', 'national_id')
        ->set('id_number', 'AB12CD34')
        ->set('id_front', UploadedFile::fake()->create('id.jpg', 120, 'image/jpeg'))
        ->call('nextStep')->assertHasNoErrors()->assertSet('step', 4)
        ->set('registration_certificate', UploadedFile::fake()->create('cert.pdf', 200, 'application/pdf'))
        ->set('proof_of_address', UploadedFile::fake()->create('bill.pdf', 200, 'application/pdf'))
        ->call('nextStep')->assertHasNoErrors()->assertSet('step', 5)
        ->set('mobile_money_number', '+265 999 123 456')
        ->call('nextStep')->assertHasNoErrors()->assertSet('step', 6)
        ->set('declaration', true)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('provider.onboarding'));

    $provider->refresh();

    expect($provider->kyc_status)->toBe('submitted')
        ->and($provider->kyc_submitted_at)->not->toBeNull()
        ->and($provider->is_approved)->toBeFalse()
        ->and($provider->kycDocuments()->pluck('type')->sort()->values()->all())
            ->toBe(['id_front', 'proof_of_address', 'registration_certificate']);

    $provider->kycDocuments->each(fn ($doc) => Storage::disk('local')->assertExists($doc->path));
});

it('requires a registration number for organisations but not individuals', function () {
    $provider = kycProvider();
    $base = fn () => Livewire::actingAs($provider->user)->test(Onboarding::class)
        ->set('physical_address', 'Area 47')
        ->set('district', 'Lilongwe');

    $base()->set('organisation_type', 'ngo')->call('nextStep')
        ->assertHasErrors(['registration_number' => 'required'])->assertSet('step', 1);

    $base()->set('organisation_type', 'individual')->call('nextStep')
        ->assertHasNoErrors()->assertSet('step', 2);
});

it('rejects disallowed document types', function () {
    $provider = kycProvider(['organisation_type' => 'church']);

    Livewire::actingAs($provider->user)
        ->test(Onboarding::class)
        ->set('step', 3)
        ->set('id_type', 'passport')
        ->set('id_number', 'P123')
        ->set('id_front', UploadedFile::fake()->create('id.exe', 10))
        ->call('nextStep')
        ->assertHasErrors(['id_front' => 'mimes']);
});

it('replaces an existing document and deletes the old file', function () {
    $provider = kycProvider(['organisation_type' => 'church', 'id_type' => 'passport', 'id_number' => 'P1']);

    $component = Livewire::actingAs($provider->user)->test(Onboarding::class)->set('step', 3);

    $component->set('id_front', UploadedFile::fake()->create('first.jpg', 120, 'image/jpeg'))->call('nextStep');
    $oldPath = $provider->kycDocuments()->where('type', 'id_front')->value('path');

    $component->set('step', 3)->set('id_front', UploadedFile::fake()->create('second.jpg', 120, 'image/jpeg'))->call('nextStep');

    expect($provider->kycDocuments()->where('type', 'id_front')->count())->toBe(1)
        ->and($provider->kycDocuments()->where('type', 'id_front')->value('original_name'))->toBe('second.jpg');
    Storage::disk('local')->assertMissing($oldPath);
});

it('will not submit an incomplete application', function () {
    $provider = kycProvider(['organisation_type' => 'church']);

    Livewire::actingAs($provider->user)
        ->test(Onboarding::class)
        ->set('step', 6)
        ->set('declaration', true)
        ->call('submit');

    expect($provider->fresh()->kyc_status)->toBe('not_started');
});

it('locks the wizard while the application is under review', function () {
    $provider = submittedKycProvider();

    Livewire::actingAs($provider->user)
        ->test(Onboarding::class)
        ->assertSee('Verification under review')
        ->call('nextStep')
        ->assertForbidden();
});

it('shows the rejection reason so the provider can resubmit', function () {
    $provider = kycProvider(['kyc_status' => 'rejected', 'kyc_rejection_reason' => 'ID photo is blurry.']);

    Livewire::actingAs($provider->user)
        ->test(Onboarding::class)
        ->assertSee('ID photo is blurry.')
        ->assertSee('Save & continue');
});

it('creates the channel during verification when the provider has none', function () {
    $provider = kycProvider();
    $provider->channels()->forceDelete();

    Livewire::actingAs($provider->user)
        ->test(Onboarding::class)
        ->set('step', 2)
        ->set('channel_name', 'Faith TV')
        ->set('channel_category', 'church')
        ->set('channel_language', 'ny')
        ->call('nextStep')
        ->assertHasNoErrors()
        ->assertSet('step', 3);

    $channel = $provider->channels()->sole();

    expect($channel->name)->toBe('Faith TV')
        ->and($channel->language)->toBe('ny')
        ->and($channel->rtmp_key)->not->toBeEmpty()
        ->and($channel->is_active)->toBeFalse()
        ->and($channel->isVerified())->toBeFalse();
});

it('lets unapproved providers into the portal but keeps channel tools locked', function () {
    $provider = kycProvider();
    $channel = $provider->channels->first();

    $this->actingAs($provider->user)
        ->get(route('provider.dashboard'))
        ->assertOk()
        ->assertSee('Complete your verification')
        ->assertSee(route('provider.onboarding'));

    $this->actingAs($provider->user)->get(route('provider.payouts.index'))->assertOk();

    foreach (['provider.channels.show', 'provider.content.index', 'provider.playlists.index'] as $route) {
        $this->actingAs($provider->user)
            ->get(route($route, $channel))
            ->assertRedirect(route('provider.onboarding'));
    }
});

it('signs a provider up with account details only and sends them to verification', function () {
    Livewire::test(\App\Livewire\Provider\Register::class)
        ->set('name', 'Jane Banda')
        ->set('company_name', 'Grace Church')
        ->set('email', 'jane@example.com')
        ->set('password', 'Secret-pass-123')
        ->set('password_confirmation', 'Secret-pass-123')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('provider.onboarding'));

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->role)->toBe('provider')
        ->and($user->providerProfile->company_name)->toBe('Grace Church')
        ->and($user->providerProfile->kyc_status)->toBe('not_started')
        ->and($user->providerProfile->channels()->exists())->toBeFalse();
    $this->assertAuthenticatedAs($user);
});

// ---------------------------------------------------------------------------
// Admin review
// ---------------------------------------------------------------------------

it('approves KYC, approves the provider and verifies their channel', function () {
    $provider = submittedKycProvider();
    $admin = kycUser('admin');

    Livewire::actingAs($admin)
        ->test(ProviderShow::class, ['provider' => $provider])
        ->call('approve');

    $provider->refresh();
    $channel = $provider->channels->first();

    expect($provider->kyc_status)->toBe('approved')
        ->and($provider->is_approved)->toBeTrue()
        ->and($provider->kyc_reviewed_by)->toBe($admin->id)
        ->and($channel->isVerified())->toBeTrue()
        ->and($channel->verified_by)->toBe($admin->id);

    Notification::assertSentTo($provider->user, ProviderKycApproved::class);
});

it('rejects KYC with a reason and notifies the provider', function () {
    $provider = submittedKycProvider();

    Livewire::actingAs(kycUser('admin'))
        ->test(ProviderShow::class, ['provider' => $provider])
        ->set('rejectionReason', 'Registration certificate has expired.')
        ->call('reject')
        ->assertHasNoErrors();

    $provider->refresh();

    expect($provider->kyc_status)->toBe('rejected')
        ->and($provider->is_approved)->toBeFalse()
        ->and($provider->kyc_rejection_reason)->toBe('Registration certificate has expired.')
        ->and($provider->channels->first()->isVerified())->toBeFalse();

    Notification::assertSentTo($provider->user, ProviderKycRejected::class);
});

it('requires a rejection reason', function () {
    Livewire::actingAs(kycUser('admin'))
        ->test(ProviderShow::class, ['provider' => submittedKycProvider()])
        ->set('rejectionReason', '')
        ->call('reject')
        ->assertHasErrors(['rejectionReason' => 'required']);
});

it('cannot approve a provider who has not submitted KYC', function () {
    $provider = kycProvider();

    Livewire::actingAs(kycUser('admin'))
        ->test(ProviderShow::class, ['provider' => $provider])
        ->call('approve');

    expect($provider->fresh()->is_approved)->toBeFalse();
});

it('serves KYC documents to admins only', function () {
    $provider = submittedKycProvider();
    Storage::disk('local')->put("kyc/{$provider->id}/id.pdf", 'pdf-bytes');
    $document = ProviderKycDocument::create([
        'provider_id'   => $provider->id,
        'type'          => 'id_front',
        'path'          => "kyc/{$provider->id}/id.pdf",
        'original_name' => 'id.pdf',
        'mime_type'     => 'application/pdf',
        'size'          => 9,
    ]);

    $this->actingAs(kycUser('admin'))
        ->get(route('admin.kyc-documents.show', $document))
        ->assertOk();

    $this->actingAs($provider->user)
        ->get(route('admin.kyc-documents.show', $document))
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Stream setup gating
// ---------------------------------------------------------------------------

it('hides stream setup until the channel is verified', function () {
    $provider = kycProvider(['is_approved' => true, 'kyc_status' => 'approved']);
    $channel = $provider->channels->first();

    $this->actingAs($provider->user)
        ->get(route('provider.channels.show', $channel))
        ->assertOk()
        ->assertDontSee(route('provider.channels.setup', $channel))
        ->assertSee('Awaiting verification');

    $channel->update(['verified_at' => now()]);

    $this->actingAs($provider->user)
        ->get(route('provider.channels.show', $channel))
        ->assertSee(route('provider.channels.setup', $channel));
});

it('redirects away from stream setup and never renders the key for unverified channels', function () {
    $provider = kycProvider(['is_approved' => true, 'kyc_status' => 'approved']);
    $channel = $provider->channels->first();

    Livewire::actingAs($provider->user)
        ->test(ChannelSetup::class, ['channel' => $channel])
        ->assertRedirect(route('provider.channels.show', $channel))
        ->assertDontSee($channel->rtmp_key);
});

it('refuses RTMP publishing for unverified channels', function () {
    $provider = kycProvider(['is_approved' => true, 'kyc_status' => 'approved']);
    $channel = $provider->channels->first();
    config(['streaming.mediamtx_secret' => 'test-secret']);

    $publish = fn () => $this->postJson('/api/mediamtx/auth?secret=test-secret', [
        'action' => 'publish',
        'path'   => "live/{$channel->rtmp_key}",
    ]);

    $publish()->assertStatus(401);

    $channel->update(['verified_at' => now()]);

    $publish()->assertStatus(200);
});

it('renders the admin provider and channel lists with KYC state', function () {
    submittedKycProvider();
    $admin = kycUser('admin');

    $this->actingAs($admin)->get(route('admin.providers.index'))
        ->assertOk()->assertSee('Awaiting review');

    $this->actingAs($admin)->get(route('admin.channels.index'))
        ->assertOk()->assertSee('Unverified');
});

it('renders the onboarding page for a new provider', function () {
    $provider = kycProvider();

    $this->actingAs($provider->user)->get(route('provider.onboarding'))
        ->assertOk()->assertSee('Verify your organisation');
});
