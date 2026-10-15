<?php

declare(strict_types=1);

use App\Livewire\Provider\ContentUpload;
use App\Livewire\Provider\PlaylistShow;
use App\Livewire\Provider\Playlists;
use App\Models\Channel;
use App\Models\ContentItem;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;

function playlistProvider(): ProviderProfile
{
    // UserFactory sets email_verified_at, which the users table does not have.
    $user = User::create(Arr::except(User::factory()->provider()->raw(), ['email_verified_at', 'remember_token']));

    return ProviderProfile::create([
        'user_id'               => $user->id,
        'company_name'          => 'Grace Church',
        'is_approved'           => true,
        'bunny_library_id'      => (string) random_int(1000, 999999),
        'bunny_library_api_key' => 'library-key-abc',
        'bunny_token_key'       => 'zone-secret',
        'bunny_cdn_hostname'    => 'vz-test.b-cdn.net',
    ]);
}

function playlistChannel(ProviderProfile $provider): Channel
{
    return Channel::create([
        'provider_id' => $provider->id,
        'name'        => 'Sunday Service',
        'slug'        => 'sunday-' . Str::random(6),
        'is_active'   => true,
    ]);
}

function playlistVideo(Channel $channel, string $title, array $attrs = []): ContentItem
{
    return ContentItem::create(array_merge([
        'channel_id'       => $channel->id,
        'title'            => $title,
        'status'           => 'ready',
        'published_at'     => now()->subDay(),
        'bunny_library_id' => $channel->provider->bunny_library_id,
        'bunny_video_id'   => (string) Str::uuid(),
    ], $attrs));
}

function apiSubscriber(): User
{
    $user = User::create(Arr::except(User::factory()->subscriber()->raw(), ['email_verified_at', 'remember_token']));
    $user->forceFill(['api_token' => 'plain-token'])->save();

    return $user;
}

function apiGet(string $uri)
{
    apiSubscriber();

    return test()->withToken('plain-token')->getJson($uri);
}

// ---------------------------------------------------------------------------
// Model
// ---------------------------------------------------------------------------

it('appends videos in order and skips duplicates', function () {
    $channel = playlistChannel(playlistProvider());
    $playlist = Playlist::create(['channel_id' => $channel->id, 'title' => 'Romans']);
    [$a, $b] = [playlistVideo($channel, 'Part 1'), playlistVideo($channel, 'Part 2')];

    expect($playlist->addVideos([$a, $b]))->toBe(2)
        ->and($playlist->addVideos([$a]))->toBe(0)
        ->and($playlist->contentItems()->pluck('title')->all())->toBe(['Part 1', 'Part 2'])
        ->and($playlist->contentItems->first()->pivot->season_number)->toBeNull();
});

it('lets one video belong to several playlists', function () {
    $channel = playlistChannel(playlistProvider());
    $video = playlistVideo($channel, 'Easter');

    Playlist::create(['channel_id' => $channel->id, 'title' => 'Easter 2026'])->addVideos([$video]);
    Playlist::create(['channel_id' => $channel->id, 'title' => 'Best of'])->addVideos([$video]);

    expect($video->playlists()->count())->toBe(2);
});

it('refuses videos from another channel', function () {
    $provider = playlistProvider();
    $playlist = Playlist::create(['channel_id' => playlistChannel($provider)->id, 'title' => 'Romans']);

    $playlist->addVideos([playlistVideo(playlistChannel($provider), 'Elsewhere')]);
})->throws(InvalidArgumentException::class);

it('puts series videos in the latest season by default', function () {
    $channel = playlistChannel(playlistProvider());
    $series = Playlist::create(['channel_id' => $channel->id, 'title' => 'Kids Bible Stories', 'kind' => 'series']);

    $series->addVideos([playlistVideo($channel, 'S1E1')]);
    $series->addVideos([playlistVideo($channel, 'S2E1')], 2);
    $series->addVideos([playlistVideo($channel, 'S2E2')]);

    expect(PlaylistItem::where('playlist_id', $series->id)->orderBy('position')->pluck('season_number')->all())
        ->toBe([1, 2, 2]);
});

// ---------------------------------------------------------------------------
// Catalog API
// ---------------------------------------------------------------------------

it('lists published playlists with playable videos on the channel endpoint', function () {
    $channel = playlistChannel(playlistProvider());
    $ready = playlistVideo($channel, 'Ready', ['thumbnail_url' => 'https://vz-test.b-cdn.net/x/thumbnail.jpg']);
    $processing = playlistVideo($channel, 'Processing', ['status' => 'processing', 'published_at' => null]);

    $shown = Playlist::create(['channel_id' => $channel->id, 'title' => 'Shown', 'is_published' => true, 'published_at' => now()]);
    $shown->addVideos([$ready, $processing]);

    Playlist::create(['channel_id' => $channel->id, 'title' => 'Draft', 'is_published' => false])->addVideos([$ready]);
    Playlist::create(['channel_id' => $channel->id, 'title' => 'Only processing', 'is_published' => true])->addVideos([$processing]);

    $response = apiGet("/api/channels/{$channel->id}")->assertOk();

    expect($response->json('playlists'))->toHaveCount(1)
        ->and($response->json('playlists.0.title'))->toBe('Shown')
        ->and($response->json('playlists.0.item_count'))->toBe(1)
        ->and($response->json('playlists.0.thumbnail_url'))->toEndWith('/x/thumbnail.jpg'); // signed cover from the first playable video
});

it('returns playlist items in order with contiguous positions', function () {
    $channel = playlistChannel(playlistProvider());
    $playlist = Playlist::create(['channel_id' => $channel->id, 'title' => 'Romans', 'is_published' => true]);
    $playlist->addVideos([
        playlistVideo($channel, 'Part 1'),
        playlistVideo($channel, 'Hidden', ['status' => 'processing']),
        playlistVideo($channel, 'Part 2'),
    ]);

    $response = apiGet("/api/playlists/{$playlist->id}")->assertOk();

    expect(collect($response->json('items'))->map(fn ($i) => [$i['position'], $i['title']])->all())
        ->toBe([[1, 'Part 1'], [2, 'Part 2']])
        ->and($response->json('items.0.hls_url'))->toBeNull() // no active subscription
        ->and($response->json())->not->toHaveKey('seasons');
});

it('groups series episodes by season', function () {
    $channel = playlistChannel(playlistProvider());
    $series = Playlist::create(['channel_id' => $channel->id, 'title' => 'Stories', 'kind' => 'series', 'is_published' => true]);
    $series->addVideos([playlistVideo($channel, 'S1 A'), playlistVideo($channel, 'S1 B')], 1);
    $series->addVideos([playlistVideo($channel, 'S2 A')], 2);

    $response = apiGet("/api/playlists/{$series->id}")->assertOk();

    expect($response->json('seasons'))->toHaveCount(2)
        ->and($response->json('seasons.0.season_number'))->toBe(1)
        ->and(collect($response->json('seasons.0.episodes'))->pluck('episode_number')->all())->toBe([1, 2])
        ->and($response->json('seasons.1.episodes.0.title'))->toBe('S2 A')
        ->and($response->json('seasons.1.episodes.0.episode_number'))->toBe(1);
});

it('hides unpublished playlists and those on inactive channels', function () {
    $channel = playlistChannel(playlistProvider());
    $draft = Playlist::create(['channel_id' => $channel->id, 'title' => 'Draft']);

    apiGet("/api/playlists/{$draft->id}")->assertNotFound();
    test()->withToken('plain-token')->getJson('/api/playlists/not-a-uuid')->assertNotFound();

    $draft->update(['is_published' => true]);
    $channel->update(['is_active' => false]);
    test()->withToken('plain-token')->getJson("/api/playlists/{$draft->id}")->assertNotFound();
});

// ---------------------------------------------------------------------------
// Provider portal
// ---------------------------------------------------------------------------

it('creates a playlist and redirects to manage it', function () {
    $provider = playlistProvider();
    $channel = playlistChannel($provider);

    Livewire::actingAs($provider->user)->test(Playlists::class, ['channel' => $channel])
        ->call('create')
        ->set('title', 'Book of Romans')
        ->set('kind', 'series')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $playlist = Playlist::sole();
    expect($playlist->kind)->toBe('series')
        ->and($playlist->is_published)->toBeTrue()
        ->and($playlist->published_at)->not->toBeNull();
});

it('blocks providers from other channels', function () {
    $owner = playlistProvider();
    $channel = playlistChannel($owner);
    $playlist = Playlist::create(['channel_id' => $channel->id, 'title' => 'Romans']);
    $other = playlistProvider();

    $this->actingAs($owner->user)->get(route('provider.playlists.index', $channel))->assertOk();
    $this->actingAs($owner->user)->get(route('provider.playlists.show', [$channel, $playlist]))->assertOk()->assertSee('Romans');
    $this->actingAs($other->user)->get(route('provider.playlists.index', $channel))->assertForbidden();
    $this->actingAs($other->user)->get(route('provider.playlists.show', [$channel, $playlist]))->assertForbidden();

    // A playlist from another channel can't be reached through this channel's URL.
    $foreign = Playlist::create(['channel_id' => playlistChannel($other)->id, 'title' => 'Foreign']);
    $this->actingAs($owner->user)->get(route('provider.playlists.show', [$channel, $foreign]))->assertNotFound();
});

it('adds, reorders and removes videos', function () {
    $provider = playlistProvider();
    $channel = playlistChannel($provider);
    $playlist = Playlist::create(['channel_id' => $channel->id, 'title' => 'Romans']);
    [$a, $b] = [playlistVideo($channel, 'Part 1'), playlistVideo($channel, 'Part 2')];
    $foreign = playlistVideo(playlistChannel(playlistProvider()), 'Not mine');

    $page = Livewire::actingAs($provider->user)
        ->test(PlaylistShow::class, ['channel' => $channel, 'playlist' => $playlist])
        ->call('openAdd')
        ->assertSee('Part 1')
        ->assertDontSee('Not mine')
        ->set('selected', [$a->id, $b->id, $foreign->id])
        ->call('addSelected')
        ->assertHasNoErrors();

    expect($playlist->contentItems()->pluck('title')->all())->toBe(['Part 1', 'Part 2']);

    $rowB = PlaylistItem::where('content_item_id', $b->id)->value('id');
    $page->call('moveUp', $rowB);
    expect($playlist->contentItems()->pluck('title')->all())->toBe(['Part 2', 'Part 1']);

    $page->call('remove', $rowB);
    expect($playlist->contentItems()->pluck('title')->all())->toBe(['Part 1'])
        ->and(ContentItem::find($b->id))->not->toBeNull();
});

it('moves a series episode to another season', function () {
    $provider = playlistProvider();
    $channel = playlistChannel($provider);
    $series = Playlist::create(['channel_id' => $channel->id, 'title' => 'Stories', 'kind' => 'series']);
    $series->addVideos([playlistVideo($channel, 'One'), playlistVideo($channel, 'Two')]);
    $row = PlaylistItem::where('playlist_id', $series->id)->orderBy('position')->first();

    Livewire::actingAs($provider->user)
        ->test(PlaylistShow::class, ['channel' => $channel, 'playlist' => $series])
        ->call('setSeason', $row->id, '2')
        ->call('setSeason', $row->id, '0');

    expect($row->fresh()->season_number)->toBe(2);
});

it('resets seasons when a playlist changes kind', function () {
    $provider = playlistProvider();
    $channel = playlistChannel($provider);
    $playlist = Playlist::create(['channel_id' => $channel->id, 'title' => 'Romans']);
    $playlist->addVideos([playlistVideo($channel, 'One')]);

    Livewire::actingAs($provider->user)->test(Playlists::class, ['channel' => $channel])
        ->call('edit', $playlist->id)
        ->set('kind', 'series')
        ->call('save');

    expect(PlaylistItem::where('playlist_id', $playlist->id)->value('season_number'))->toBe(1);
});

it('adds new uploads to the chosen playlists', function () {
    config(['services.bunny.api_key' => 'account-key']);
    Http::fake([
        'video.bunnycdn.com/library/*/videos' => Http::response(['guid' => (string) Str::uuid()]),
        'api.bunny.net/*'                     => Http::response([], 200), // library rename on channel create
    ]);

    $provider = playlistProvider();
    $channel = playlistChannel($provider);
    $playlist = Playlist::create(['channel_id' => $channel->id, 'title' => 'Romans']);
    $foreign = Playlist::create(['channel_id' => playlistChannel(playlistProvider())->id, 'title' => 'Foreign']);

    Livewire::actingAs($provider->user)->test(ContentUpload::class, ['channel' => $channel])
        ->call('addFiles', [['key' => 'key-aaaaaaaa', 'name' => 'part1.mp4', 'size' => 1000, 'type' => 'video/mp4']])
        ->set('playlistIds', [$playlist->id, $foreign->id])
        ->call('startAll')
        ->assertHasNoErrors();

    expect($playlist->contentItems()->count())->toBe(1)
        ->and($foreign->contentItems()->count())->toBe(0);
});
