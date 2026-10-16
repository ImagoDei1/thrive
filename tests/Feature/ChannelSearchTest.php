<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

function searchChannel(string $name): Channel
{
    // UserFactory sets email_verified_at, which the users table does not have.
    $user = User::create(Arr::except(User::factory()->provider()->raw(), ['email_verified_at', 'remember_token']));

    $provider = ProviderProfile::create([
        'user_id'      => $user->id,
        'company_name' => $name,
        'is_approved'  => true,
        'kyc_status'   => 'approved',
    ]);

    return Channel::create([
        'provider_id' => $provider->id,
        'name'        => $name,
        'slug'        => Str::slug($name) . '-' . Str::random(6),
        'is_active'   => true,
        'verified_at' => now(),
    ]);
}

function searchChannelsAs(string $query)
{
    $user = User::create(Arr::except(User::factory()->subscriber()->raw(), ['email_verified_at', 'remember_token']));
    $user->forceFill(['api_token' => 'search-token'])->save();

    return test()->withToken('search-token')->getJson('/api/channels?search=' . urlencode($query));
}

it('filters channels by a case-insensitive name match', function () {
    searchChannel('Gospel Light');
    searchChannel('Kwacha Sports');

    $names = collect(searchChannelsAs('GOSPEL')->assertOk()->json('data'))->pluck('name');

    expect($names->all())->toBe(['Gospel Light']);
});

it('treats LIKE wildcards in the search as literal text', function () {
    searchChannel('100% Gospel');
    searchChannel('Gospel Light');

    $names = collect(searchChannelsAs('100%')->assertOk()->json('data'))->pluck('name');

    expect($names->all())->toBe(['100% Gospel']);
});
