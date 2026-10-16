<?php

declare(strict_types=1);

use App\Models\Channel;
use App\Models\ContentItem;
use App\Models\ContentLike;
use App\Models\Playlist;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

function likeChannel(): Channel
{
    $user = User::create(Arr::except(User::factory()->provider()->raw(), ['email_verified_at', 'remember_token']));
    $provider = ProviderProfile::create([
        'user_id'      => $user->id,
        'company_name' => 'Grace Church',
        'is_approved'  => true,
    ]);

    return Channel::create([
        'provider_id' => $provider->id,
        'name'        => 'Sunday Service',
        'slug'        => 'sunday-' . Str::random(6),
        'is_active'   => true,
    ]);
}

function likeVideo(Channel $channel, array $attrs = []): ContentItem
{
    return ContentItem::create(array_merge([
        'channel_id'   => $channel->id,
        'title'        => 'Sermon',
        'status'       => 'ready',
        'published_at' => now()->subDay(),
    ], $attrs));
}

function likeSubscriber(string $token = 'like-token'): User
{
    $user = User::create(Arr::except(User::factory()->subscriber()->raw(), ['email_verified_at', 'remember_token']));
    $user->forceFill(['api_token' => $token])->save();

    return $user;
}

it('likes a video once, even when tapped twice', function () {
    $video = likeVideo(likeChannel());
    likeSubscriber();

    $this->withToken('like-token')->postJson("/api/content-items/{$video->id}/like")
        ->assertOk()
        ->assertExactJson(['content_id' => $video->id, 'liked' => true, 'like_count' => 1]);

    $this->withToken('like-token')->postJson("/api/content-items/{$video->id}/like")
        ->assertOk()
        ->assertJson(['liked' => true, 'like_count' => 1]);

    expect(ContentLike::count())->toBe(1);
});

it('unlikes a video and is idempotent', function () {
    $video = likeVideo(likeChannel());
    $user = likeSubscriber();
    ContentLike::create(['user_id' => $user->id, 'content_item_id' => $video->id]);

    $this->withToken('like-token')->deleteJson("/api/content-items/{$video->id}/like")
        ->assertOk()
        ->assertJson(['liked' => false, 'like_count' => 0]);

    $this->withToken('like-token')->deleteJson("/api/content-items/{$video->id}/like")
        ->assertOk()
        ->assertJson(['liked' => false, 'like_count' => 0]);
});

it('counts likes from all users but only marks the viewer’s own', function () {
    $channel = likeChannel();
    $video = likeVideo($channel);
    $other = likeSubscriber('other-token');
    ContentLike::create(['user_id' => $other->id, 'content_item_id' => $video->id]);
    likeSubscriber();

    $this->withToken('like-token')->getJson("/api/channels/{$channel->id}")
        ->assertOk()
        ->assertJsonPath('content_items.0.like_count', 1)
        ->assertJsonPath('content_items.0.liked', false);

    $this->withToken('like-token')->postJson("/api/content-items/{$video->id}/like")
        ->assertJson(['like_count' => 2]);

    $this->withToken('like-token')->getJson("/api/channels/{$channel->id}")
        ->assertJsonPath('content_items.0.like_count', 2)
        ->assertJsonPath('content_items.0.liked', true);
});

it('includes like state on playlist items', function () {
    $channel = likeChannel();
    $video = likeVideo($channel);
    $playlist = Playlist::create([
        'channel_id'   => $channel->id,
        'title'        => 'Romans',
        'is_published' => true,
        'published_at' => now()->subDay(),
    ]);
    $playlist->contentItems()->attach($video->id, ['position' => 1]);
    $user = likeSubscriber();
    ContentLike::create(['user_id' => $user->id, 'content_item_id' => $video->id]);

    $this->withToken('like-token')->getJson("/api/playlists/{$playlist->id}")
        ->assertOk()
        ->assertJsonPath('items.0.like_count', 1)
        ->assertJsonPath('items.0.liked', true);
});

it('refuses to like videos subscribers cannot see', function (array $attrs) {
    $video = likeVideo(likeChannel(), $attrs);
    likeSubscriber();

    $this->withToken('like-token')->postJson("/api/content-items/{$video->id}/like")->assertNotFound();

    expect(ContentLike::count())->toBe(0);
})->with([
    'unpublished' => [['unpublished_at' => now()]],
    'processing'  => [['status' => 'processing']],
    'scheduled'   => [['published_at' => now()->addDay()]],
]);

it('returns 404 for unknown or malformed ids', function () {
    likeSubscriber();

    $this->withToken('like-token')->postJson('/api/content-items/' . Str::uuid() . '/like')->assertNotFound();
    $this->withToken('like-token')->postJson('/api/content-items/not-a-uuid/like')->assertNotFound();
});

it('requires an api token', function () {
    $video = likeVideo(likeChannel());

    $this->postJson("/api/content-items/{$video->id}/like")->assertUnauthorized();
});
