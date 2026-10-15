<?php

declare(strict_types=1);

use App\Jobs\DeleteBunnyVideoJob;
use App\Livewire\Provider\ContentItems;
use App\Models\Channel;
use App\Models\ContentItem;
use App\Models\Playlist;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Bunny\BunnyStreamService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

function unpublishChannel(): Channel
{
    // UserFactory sets email_verified_at, which the users table does not have.
    $user = User::create(Arr::except(User::factory()->provider()->raw(), ['email_verified_at', 'remember_token']));

    $provider = ProviderProfile::create([
        'user_id'               => $user->id,
        'company_name'          => 'Grace Church',
        'is_approved'           => true,
        'kyc_status'            => 'approved',
        'bunny_library_id'      => (string) random_int(1000, 999999),
        'bunny_library_api_key' => 'library-key-abc',
        'bunny_token_key'       => 'zone-secret',
        'bunny_cdn_hostname'    => 'vz-test.b-cdn.net',
    ]);

    return Channel::create([
        'provider_id' => $provider->id,
        'name'        => 'Sunday Service',
        'slug'        => 'sunday-' . Str::random(6),
        'is_active'   => true,
        'verified_at' => now(),
    ]);
}

function unpublishVideo(Channel $channel, array $attrs = []): ContentItem
{
    return ContentItem::create(array_merge([
        'channel_id'       => $channel->id,
        'title'            => 'Easter Sermon',
        'status'           => 'ready',
        'published_at'     => now()->subDay(),
        'bunny_library_id' => $channel->provider->bunny_library_id,
        'bunny_video_id'   => (string) Str::uuid(),
    ], $attrs));
}

function catalogAs(string $uri)
{
    if (! User::where('api_token', 'plain-token')->exists()) {
        $user = User::create(Arr::except(User::factory()->subscriber()->raw(), ['email_verified_at', 'remember_token']));
        $user->forceFill(['api_token' => 'plain-token'])->save();
    }

    return test()->withToken('plain-token')->getJson($uri);
}

beforeEach(fn () => Queue::fake());

it('unpublishes a video without deleting it', function () {
    $channel = unpublishChannel();
    $video = unpublishVideo($channel);

    Livewire::actingAs($channel->provider->user)
        ->test(ContentItems::class, ['channel' => $channel])
        ->call('unpublish', $video->id);

    $video->refresh();

    expect($video->isUnpublished())->toBeTrue()
        ->and($video->trashed())->toBeFalse()
        ->and($video->published_at)->not->toBeNull();

    Queue::assertNotPushed(DeleteBunnyVideoJob::class); // No Bunny deletion.
});

it('republishes an unpublished video', function () {
    $channel = unpublishChannel();
    $video = unpublishVideo($channel, ['unpublished_at' => now()]);

    Livewire::actingAs($channel->provider->user)
        ->test(ContentItems::class, ['channel' => $channel])
        ->call('republish', $video->id);

    expect($video->fresh()->isUnpublished())->toBeFalse()
        ->and(ContentItem::playable()->whereKey($video->id)->exists())->toBeTrue();
});

it('hides unpublished videos from the channel catalog and playlists', function () {
    $channel = unpublishChannel();
    $visible = unpublishVideo($channel, ['title' => 'Visible']);
    $hidden = unpublishVideo($channel, ['title' => 'Hidden', 'unpublished_at' => now()]);

    $playlist = Playlist::create(['channel_id' => $channel->id, 'title' => 'Easter', 'is_published' => true, 'published_at' => now()]);
    $playlist->addVideos([$visible, $hidden]);

    $titles = collect(catalogAs("/api/channels/{$channel->id}")->assertOk()->json('content_items'))->pluck('title');
    expect($titles->all())->toBe(['Visible']);

    $response = catalogAs("/api/playlists/{$playlist->id}")->assertOk();
    expect($response->json('playlist.item_count'))->toBe(1);
});

it('keeps a video unpublished when Bunny re-syncs it', function () {
    Http::fake(['video.bunnycdn.com/*' => Http::response(['status' => 4, 'length' => 60])]);
    $video = unpublishVideo(unpublishChannel(), ['unpublished_at' => now()]);

    app(BunnyStreamService::class)->syncVideo($video);

    expect($video->fresh()->isUnpublished())->toBeTrue()
        ->and(ContentItem::playable()->whereKey($video->id)->exists())->toBeFalse();
});

it('does not let providers unpublish videos from another channel', function () {
    $mine = unpublishChannel();
    $theirs = unpublishVideo(unpublishChannel());

    Livewire::actingAs($mine->provider->user)
        ->test(ContentItems::class, ['channel' => $mine])
        ->call('unpublish', $theirs->id);
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

it('leaves other channels\' videos untouched', function () {
    $mine = unpublishChannel();
    $theirs = unpublishVideo(unpublishChannel());

    try {
        Livewire::actingAs($mine->provider->user)
            ->test(ContentItems::class, ['channel' => $mine])
            ->call('unpublish', $theirs->id);
    } catch (Illuminate\Database\Eloquent\ModelNotFoundException) {
    }

    expect($theirs->fresh()->isUnpublished())->toBeFalse();
});

it('shows unpublish and republish controls on the videos page', function () {
    $channel = unpublishChannel();
    unpublishVideo($channel, ['title' => 'Live one']);
    unpublishVideo($channel, ['title' => 'Hidden one', 'unpublished_at' => now()]);

    Livewire::actingAs($channel->provider->user)
        ->test(ContentItems::class, ['channel' => $channel])
        ->assertSee('Unpublished')
        ->assertSeeHtml('confirmUnpublish(')
        ->assertSeeHtml('republish(');
});
