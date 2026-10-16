<?php

declare(strict_types=1);

use App\Livewire\Admin\ReferenceData;
use App\Livewire\Provider\ChannelCreate;
use App\Models\Channel;
use App\Models\ProviderProfile;
use App\Models\ReferenceValue;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

function refUser(string $role): User
{
    // UserFactory sets email_verified_at, which the users table does not have.
    return User::create(Arr::except(User::factory()->{$role}()->raw(), ['email_verified_at', 'remember_token']));
}

function refChannel(string $category): Channel
{
    $provider = ProviderProfile::create([
        'user_id'        => refUser('provider')->id,
        'company_name'   => 'Grace Church',
        'contact_person' => 'Jane Banda',
        'contact_phone'  => '+265999000000',
    ]);

    return Channel::create([
        'provider_id' => $provider->id,
        'name'        => 'Grace TV',
        'slug'        => 'grace-' . Str::random(6),
        'category'    => $category,
        'rtmp_key'    => Str::random(32),
    ]);
}

beforeEach(function () {
    Queue::fake(); // Channel creation dispatches SyncBunnyLibraryNameJob.
});

it('ships the previously hardcoded values as defaults', function () {
    expect(ReferenceValue::options(ReferenceValue::CHANNEL_CATEGORY))->toHaveKey('church')
        ->and(ReferenceValue::options(ReferenceValue::CHANNEL_LANGUAGE))->toHaveKey('ny')
        ->and(ReferenceValue::options(ReferenceValue::ORGANISATION_TYPE))->toHaveKey('individual')
        ->and(ReferenceValue::options(ReferenceValue::ID_TYPE))->toHaveKey('passport');
});

it('is admin only', function () {
    $this->actingAs(refUser('admin'))->get(route('admin.reference-data.index'))->assertOk();
    $this->actingAs(refUser('provider'))->get(route('admin.reference-data.index'))->assertForbidden();
});

it('lets an admin add a value that providers can then pick', function () {
    Livewire::actingAs(refUser('admin'))
        ->test(ReferenceData::class)
        ->set('selectedType', ReferenceValue::CHANNEL_CATEGORY)
        ->call('create')
        ->set('label', 'Health & Wellness')
        ->assertSet('code', 'health_wellness')
        ->call('save')
        ->assertHasNoErrors();

    expect(ReferenceValue::options(ReferenceValue::CHANNEL_CATEGORY))->toHaveKey('health_wellness');

    $provider = ProviderProfile::create([
        'user_id'        => refUser('provider')->id,
        'company_name'   => 'Wellness Org',
        'contact_person' => 'Jane Banda',
        'contact_phone'  => '+265999000001',
        'is_approved'    => true,
    ]);

    Livewire::actingAs($provider->user)
        ->test(ChannelCreate::class)
        ->set('name', 'Wellness TV')
        ->set('category', 'health_wellness')
        ->set('language', 'en')
        ->call('save')
        ->assertHasNoErrors();

    expect($provider->channels()->first()->categoryLabel())->toBe('Health & Wellness');
});

it('rejects duplicate and malformed codes', function () {
    Livewire::actingAs(refUser('admin'))
        ->test(ReferenceData::class)
        ->call('create')
        ->set('label', 'Church again')
        ->set('code', 'church')
        ->call('save')
        ->assertHasErrors(['code' => 'unique'])
        ->set('code', 'Bad Code!')
        ->call('save')
        ->assertHasErrors(['code' => 'regex']);
});

it('renames a label without touching stored codes', function () {
    $channel = refChannel('music');
    $value   = ReferenceValue::where('type', ReferenceValue::CHANNEL_CATEGORY)->where('code', 'music')->first();

    Livewire::actingAs(refUser('admin'))
        ->test(ReferenceData::class)
        ->call('edit', $value->id)
        ->set('code', 'tunes') // ignored — code is immutable
        ->set('label', 'Music & Gospel')
        ->call('save')
        ->assertHasNoErrors();

    expect($value->fresh()->code)->toBe('music')
        ->and($channel->fresh()->categoryLabel())->toBe('Music & Gospel');
});

it('hides a value from new selections but keeps its label for existing records', function () {
    $channel = refChannel('sports');
    $value   = ReferenceValue::where('type', ReferenceValue::CHANNEL_CATEGORY)->where('code', 'sports')->first();

    Livewire::actingAs(refUser('admin'))
        ->test(ReferenceData::class)
        ->call('toggleActive', $value->id);

    expect($value->fresh()->is_active)->toBeFalse()
        ->and(ReferenceValue::options(ReferenceValue::CHANNEL_CATEGORY))->not->toHaveKey('sports')
        ->and($channel->fresh()->categoryLabel())->toBe('Sports');
});

it('only deletes values that are unused and not system values', function () {
    refChannel('news');
    $used   = ReferenceValue::where('type', ReferenceValue::CHANNEL_CATEGORY)->where('code', 'news')->first();
    $unused = ReferenceValue::where('type', ReferenceValue::CHANNEL_CATEGORY)->where('code', 'documentary')->first();
    $system = ReferenceValue::where('type', ReferenceValue::ORGANISATION_TYPE)->where('code', 'individual')->first();

    $component = Livewire::actingAs(refUser('admin'))->test(ReferenceData::class);

    $component->call('delete', $used->id);
    $component->call('delete', $unused->id);
    $component->set('selectedType', ReferenceValue::ORGANISATION_TYPE)
        ->call('delete', $system->id)
        ->call('toggleActive', $system->id);

    expect($used->fresh())->not->toBeNull()
        ->and($unused->fresh())->toBeNull()
        ->and($system->fresh())->not->toBeNull()
        ->and($system->fresh()->is_active)->toBeTrue();
});
