<?php

declare(strict_types=1);

use App\Jobs\DeleteBunnyVideoJob;
use App\Jobs\ProvisionBunnyLibraryJob;
use App\Jobs\SyncBunnyVideoJob;
use App\Livewire\Provider\ContentUpload;
use App\Models\Channel;
use App\Models\ContentItem;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Bunny\BunnyStreamService;
use App\Services\VideoSourceService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;

const LIB_ID = '4242';
const LIB_KEY = 'library-key-abc';
const VIDEO_GUID = '6b1a7d4e-1f3c-4b9a-8e2d-1234567890ab';

beforeEach(function () {
    config([
        'services.bunny.api_key'        => 'account-key',
        'services.bunny.webhook_secret' => 'hook-secret',
        'services.bunny.token_auth'     => true,
    ]);
});

function bunnyProvider(bool $withLibrary = true): ProviderProfile
{
    // UserFactory sets email_verified_at, which the users table does not have.
    $user = User::create(Arr::except(User::factory()->provider()->raw(), ['email_verified_at', 'remember_token']));

    return ProviderProfile::create(array_merge([
        'user_id'      => $user->id,
        'company_name' => 'Grace Church',
        'is_approved'  => true,
    ], $withLibrary ? [
        'bunny_library_id'      => LIB_ID,
        'bunny_library_api_key' => LIB_KEY,
        'bunny_token_key'       => 'zone-secret',
        'bunny_cdn_hostname'    => 'vz-test.b-cdn.net',
    ] : []));
}

function bunnyChannel(ProviderProfile $provider): Channel
{
    return Channel::create([
        'provider_id' => $provider->id,
        'name'        => 'Sunday Service',
        'slug'        => 'sunday-' . Str::random(6),
        'is_active'   => true,
    ]);
}

function bunnyItem(Channel $channel, array $attrs = []): ContentItem
{
    return ContentItem::create(array_merge([
        'channel_id'       => $channel->id,
        'title'            => 'Sermon',
        'status'           => 'processing',
        'bunny_library_id' => LIB_ID,
        'bunny_video_id'   => VIDEO_GUID,
    ], $attrs));
}

// ---------------------------------------------------------------------------
// Library provisioning
// ---------------------------------------------------------------------------

it('queues library provisioning when a provider is approved', function () {
    Queue::fake();
    $provider = bunnyProvider(withLibrary: false);
    $provider->update(['is_approved' => false]);

    $provider->update(['is_approved' => true]);

    Queue::assertPushed(ProvisionBunnyLibraryJob::class, fn ($job) => $job->providerId === $provider->id);
});

it('provisions a library and stores encrypted credentials', function () {
    Queue::fake();
    Http::fake([
        'api.bunny.net/videolibrary'      => Http::response(['Id' => 999, 'ApiKey' => 'new-lib-key', 'PullZoneId' => 77]),
        'api.bunny.net/videolibrary/999'  => Http::response([], 200),
        'api.bunny.net/pullzone/77'       => Http::response([
            'ZoneSecurityKey' => 'zone-key',
            'Hostnames'       => [['Value' => 'cdn.example.com', 'IsSystemHostname' => false], ['Value' => 'vz-999.b-cdn.net', 'IsSystemHostname' => true]],
        ]),
    ]);
    $provider = bunnyProvider(withLibrary: false);

    app(BunnyStreamService::class)->provisionLibrary($provider);

    $provider->refresh();
    expect($provider->bunny_library_id)->toBe('999')
        ->and($provider->bunny_library_api_key)->toBe('new-lib-key')
        ->and($provider->bunny_cdn_hostname)->toBe('vz-999.b-cdn.net')
        ->and($provider->getRawOriginal('bunny_library_api_key'))->not->toBe('new-lib-key');

    Http::assertSent(fn ($r) => $r->url() === 'https://api.bunny.net/videolibrary/999'
        && str_contains($r['WebhookUrl'], '/webhooks/bunny?secret=hook-secret')
        && $r['EnableTokenAuthentication'] === true);
});

// ---------------------------------------------------------------------------
// Upload initiation
// ---------------------------------------------------------------------------

function uploadPage(ProviderProfile $provider, Channel $channel)
{
    return Livewire::actingAs($provider->user)->test(ContentUpload::class, ['channel' => $channel]);
}

it('renders the upload page only for the channel owner', function () {
    $provider = bunnyProvider();
    $channel = bunnyChannel($provider);

    $this->actingAs($provider->user)->get(route('provider.content.upload', $channel))
        ->assertOk()->assertSee('Drop video files here');

    $other = bunnyProvider(withLibrary: false);
    $this->actingAs($other->user)->get(route('provider.content.upload', $channel))->assertForbidden();
});

it('queues multiple files with titles derived from file names', function () {
    $provider = bunnyProvider();

    uploadPage($provider, bunnyChannel($provider))
        ->call('addFiles', [
            ['key' => 'key-aaaaaaaa', 'name' => 'easter_sunday-2026.mp4', 'size' => 1000, 'type' => 'video/mp4'],
            ['key' => 'key-bbbbbbbb', 'name' => 'choir.mov', 'size' => 2000, 'type' => 'video/quicktime'],
            ['key' => 'key-cccccccc', 'name' => 'notes.pdf', 'size' => 10, 'type' => 'application/pdf'],
            ['key' => 'key-dddddddd', 'name' => 'choir.mov', 'size' => 2000, 'type' => 'video/quicktime'],
        ])
        ->assertSet('uploads.key-aaaaaaaa.title', 'easter sunday 2026')
        ->assertSet('uploads.key-bbbbbbbb.status', 'pending')
        ->assertCount('uploads', 2);
});

it('starts every pending file and returns presigned TUS headers per file', function () {
    $guids = [VIDEO_GUID, '7c2b8e5f-2a4d-4cab-9f3e-abcdefabcdef'];
    Http::fake(['video.bunnycdn.com/library/' . LIB_ID . '/videos' => Http::sequence()
        ->push(['guid' => $guids[0]])
        ->push(['guid' => $guids[1]])]);
    $provider = bunnyProvider();

    $page = uploadPage($provider, bunnyChannel($provider))
        ->call('addFiles', [
            ['key' => 'key-aaaaaaaa', 'name' => 'one.mp4', 'size' => 1000, 'type' => 'video/mp4'],
            ['key' => 'key-bbbbbbbb', 'name' => 'two.mp4', 'size' => 2000, 'type' => 'video/mp4'],
        ])
        ->set('uploads.key-bbbbbbbb.title', 'Second Service')
        ->call('startAll')
        ->assertHasNoErrors()
        ->assertSet('uploads.key-aaaaaaaa.status', 'uploading')
        ->assertDispatched('bunny-upload-start', function ($name, $params) use ($guids) {
            if (count($params['uploads']) !== 2) {
                return false;
            }
            foreach ($params['uploads'] as $i => $job) {
                $h = $job['tus']['headers'];
                if ($h['VideoId'] !== $guids[$i]
                    || $h['AuthorizationSignature'] !== hash('sha256', LIB_ID . LIB_KEY . $h['AuthorizationExpire'] . $guids[$i])) {
                    return false;
                }
            }

            return true;
        });

    expect(ContentItem::where('status', 'uploading')->count())->toBe(2)
        ->and(ContentItem::where('bunny_video_id', $guids[1])->value('title'))->toBe('Second Service');

    $id = $page->get('uploads.key-aaaaaaaa.content_item_id');
    $page->dispatch('bunny-upload-finished', key: 'key-aaaaaaaa', id: $id)
        ->assertSet('uploads.key-aaaaaaaa.status', 'done');
    expect(ContentItem::find($id)->status)->toBe('processing');
});

it('requires a title for each file', function () {
    Http::fake();
    $provider = bunnyProvider();

    uploadPage($provider, bunnyChannel($provider))
        ->call('addFiles', [['key' => 'key-aaaaaaaa', 'name' => 'one.mp4', 'size' => 1000, 'type' => 'video/mp4']])
        ->set('uploads.key-aaaaaaaa.title', '')
        ->call('startAll')
        ->assertHasErrors('uploads.key-aaaaaaaa.title')
        ->assertNotDispatched('bunny-upload-start');

    Http::assertNothingSent();
});

it('marks a failed file and resumes the same Bunny video on retry', function () {
    Http::fake(['video.bunnycdn.com/library/' . LIB_ID . '/videos' => Http::response(['guid' => VIDEO_GUID])]);
    $provider = bunnyProvider();

    $page = uploadPage($provider, bunnyChannel($provider))
        ->call('addFiles', [['key' => 'key-aaaaaaaa', 'name' => 'one.mp4', 'size' => 1000, 'type' => 'video/mp4']])
        ->call('startAll');
    $id = $page->get('uploads.key-aaaaaaaa.content_item_id');

    $page->dispatch('bunny-upload-failed', key: 'key-aaaaaaaa', id: $id, message: 'network')
        ->assertSet('uploads.key-aaaaaaaa.status', 'failed')
        ->call('retry', 'key-aaaaaaaa')
        ->assertSet('uploads.key-aaaaaaaa.status', 'uploading')
        ->assertDispatched('bunny-upload-start', fn ($n, $p) => $p['uploads'][0]['tus']['headers']['VideoId'] === VIDEO_GUID);

    Http::assertSentCount(1);
    expect(ContentItem::count())->toBe(1);
});

it('marks a file failed when Bunny rejects the video creation', function () {
    Http::fake(['video.bunnycdn.com/*' => Http::response([], 500)]);
    $provider = bunnyProvider();

    uploadPage($provider, bunnyChannel($provider))
        ->call('addFiles', [['key' => 'key-aaaaaaaa', 'name' => 'one.mp4', 'size' => 1000, 'type' => 'video/mp4']])
        ->call('startAll')
        ->assertSet('uploads.key-aaaaaaaa.status', 'failed')
        ->assertNotDispatched('bunny-upload-start');
});

it('blocks marking another channel\'s item as uploaded', function () {
    $provider = bunnyProvider();
    $other = bunnyChannel(bunnyProvider(withLibrary: false));
    $item = bunnyItem($other, ['status' => 'uploading']);

    uploadPage($provider, bunnyChannel($provider))
        ->call('uploadFinished', 'key-aaaaaaaa', $item->id)
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Webhook
// ---------------------------------------------------------------------------

it('rejects webhooks without the shared secret', function () {
    $this->postJson('/webhooks/bunny', ['VideoLibraryId' => LIB_ID, 'VideoGuid' => VIDEO_GUID, 'Status' => 3])
        ->assertUnauthorized();
});

it('queues a sync for a known video', function () {
    Queue::fake();
    $item = bunnyItem(bunnyChannel(bunnyProvider()));

    $this->postJson('/webhooks/bunny?secret=hook-secret', ['VideoLibraryId' => (int) LIB_ID, 'VideoGuid' => VIDEO_GUID, 'Status' => 3])
        ->assertOk();

    Queue::assertPushed(SyncBunnyVideoJob::class, fn ($job) => $job->contentItemId === $item->id);
});

it('ignores webhooks whose library does not match the item', function () {
    Queue::fake();
    bunnyItem(bunnyChannel(bunnyProvider()));

    $this->postJson('/webhooks/bunny?secret=hook-secret', ['VideoLibraryId' => 1, 'VideoGuid' => VIDEO_GUID, 'Status' => 3])
        ->assertOk();

    Queue::assertNothingPushed();
});

it('marks the item ready from the authoritative Bunny video state', function () {
    Http::fake(["video.bunnycdn.com/library/" . LIB_ID . "/videos/" . VIDEO_GUID => Http::response([
        'status' => 4, 'length' => 3605, 'width' => 1920, 'height' => 1080,
        'availableResolutions' => '360p,720p,1080p', 'thumbnailFileName' => 'thumbnail.jpg', 'storageSize' => 5555,
    ])]);
    $item = bunnyItem(bunnyChannel(bunnyProvider()));

    (new SyncBunnyVideoJob($item->id))->handle(app(BunnyStreamService::class));

    $item->refresh();
    expect($item->status)->toBe('ready')
        ->and($item->hls_url)->toBe('https://vz-test.b-cdn.net/' . VIDEO_GUID . '/playlist.m3u8')
        ->and($item->thumbnail_url)->toBe('https://vz-test.b-cdn.net/' . VIDEO_GUID . '/thumbnail.jpg')
        ->and($item->duration_seconds)->toBe(3605)
        ->and($item->resolution)->toBe('1920x1080')
        ->and($item->qualities)->toBe(['360p', '720p', '1080p'])
        ->and($item->published_at)->not->toBeNull();
});

it('marks the item failed when Bunny reports an error', function () {
    Http::fake(['video.bunnycdn.com/*' => Http::response(['status' => 5])]);
    $item = bunnyItem(bunnyChannel(bunnyProvider()));

    app(BunnyStreamService::class)->syncVideo($item);

    expect($item->fresh()->status)->toBe('failed');
});

it('does not regress a ready item on a late event', function () {
    Http::fake(['video.bunnycdn.com/*' => Http::response(['status' => 3])]);
    $item = bunnyItem(bunnyChannel(bunnyProvider()), ['status' => 'ready']);

    app(BunnyStreamService::class)->syncVideo($item);

    expect($item->fresh()->status)->toBe('ready');
});

// ---------------------------------------------------------------------------
// Playback URLs
// ---------------------------------------------------------------------------

it('signs HLS URLs with a Bunny directory token', function () {
    $item = bunnyItem(bunnyChannel(bunnyProvider()), [
        'status'  => 'ready',
        'hls_url' => 'https://vz-test.b-cdn.net/' . VIDEO_GUID . '/playlist.m3u8',
    ]);

    $url = app(VideoSourceService::class)->hlsUrl($item);

    expect($url)->toStartWith('https://vz-test.b-cdn.net/bcdn_token=')
        ->toContain('&token_path=' . urlencode('/' . VIDEO_GUID . '/'))
        ->toEndWith('/' . VIDEO_GUID . '/playlist.m3u8');
});

it('matches the reference Bunny token algorithm', function () {
    $url = VideoSourceService::signBunnyUrl('https://cdn.test/abc/playlist.m3u8', 'key', 3600, '/abc/', now: 1_000_000);

    $expected = rtrim(strtr(base64_encode(hash('sha256', 'key/abc/1003600token_path=/abc/', true)), '+/', '-_'), '=');

    expect($url)->toBe("https://cdn.test/bcdn_token={$expected}&expires=1003600&token_path=%2Fabc%2F/abc/playlist.m3u8");
});

it('returns no HLS URL until the video is ready', function () {
    $item = bunnyItem(bunnyChannel(bunnyProvider()), ['status' => 'processing', 'hls_url' => 'https://x/y.m3u8']);

    expect(app(VideoSourceService::class)->hlsUrl($item))->toBeNull();
});

// ---------------------------------------------------------------------------
// Deletion and maintenance
// ---------------------------------------------------------------------------

it('queues Bunny deletion when a content item is deleted', function () {
    Queue::fake();
    $item = bunnyItem(bunnyChannel(bunnyProvider()));

    $item->delete();

    Queue::assertPushed(DeleteBunnyVideoJob::class, fn ($job) => $job->contentItemId === $item->id);
});

it('deletes the video from Bunny for a trashed item', function () {
    Queue::fake();
    Http::fake(['video.bunnycdn.com/*' => Http::response([], 200)]);
    $item = bunnyItem(bunnyChannel(bunnyProvider()));
    $item->delete();

    (new DeleteBunnyVideoJob($item->id))->handle(app(BunnyStreamService::class));

    Http::assertSent(fn ($r) => $r->method() === 'DELETE'
        && $r->url() === 'https://video.bunnycdn.com/library/' . LIB_ID . '/videos/' . VIDEO_GUID
        && $r->header('AccessKey')[0] === LIB_KEY);
});

it('skips Bunny deletion if the item was restored', function () {
    Queue::fake();
    Http::fake();
    $item = bunnyItem(bunnyChannel(bunnyProvider()));
    $item->delete();
    $item->restore();

    (new DeleteBunnyVideoJob($item->id))->handle(app(BunnyStreamService::class));

    Http::assertNothingSent();
});

it('treats a 404 on delete as already deleted', function () {
    Queue::fake();
    Http::fake(['video.bunnycdn.com/*' => Http::response([], 404)]);
    $item = bunnyItem(bunnyChannel(bunnyProvider()));
    $item->delete();

    (new DeleteBunnyVideoJob($item->id))->handle(app(BunnyStreamService::class));
})->throwsNoExceptions();

it('re-syncs stale processing items and fails abandoned uploads', function () {
    Queue::fake();
    $channel = bunnyChannel(bunnyProvider());
    $stale = bunnyItem($channel);
    $fresh = bunnyItem($channel, ['bunny_video_id' => (string) Str::uuid()]);
    $abandoned = bunnyItem($channel, ['status' => 'uploading', 'bunny_video_id' => (string) Str::uuid()]);
    $active = bunnyItem($channel, ['status' => 'uploading', 'bunny_video_id' => (string) Str::uuid()]);

    ContentItem::whereKey($stale->id)->update(['updated_at' => now()->subHour()]);
    ContentItem::whereKey($abandoned->id)->update(['created_at' => now()->subDays(2)]);

    $this->artisan('bunny:sync-pending')->assertSuccessful();

    Queue::assertPushed(SyncBunnyVideoJob::class, 1);
    Queue::assertPushed(SyncBunnyVideoJob::class, fn ($job) => $job->contentItemId === $stale->id);
    Queue::assertPushed(DeleteBunnyVideoJob::class, fn ($job) => $job->contentItemId === $abandoned->id);
    expect($abandoned->fresh()->status)->toBe('failed')
        ->and($active->fresh()->status)->toBe('uploading')
        ->and($fresh->fresh()->status)->toBe('processing');
});

it('backfills libraries only for approved providers without one', function () {
    $needs = bunnyProvider(withLibrary: false);
    bunnyProvider();
    $unapproved = bunnyProvider(withLibrary: false);
    $unapproved->update(['is_approved' => false]);
    Queue::fake();

    $this->artisan('bunny:provision-libraries')->assertSuccessful();

    Queue::assertPushed(ProvisionBunnyLibraryJob::class, 1);
    Queue::assertPushed(ProvisionBunnyLibraryJob::class, fn ($job) => $job->providerId === $needs->id);
});

// ---------------------------------------------------------------------------
// Admin preview (Bunny embed player)
// ---------------------------------------------------------------------------

it('builds a token-signed Bunny embed URL for ready videos only', function () {
    $channel = bunnyChannel(bunnyProvider());
    $item = bunnyItem($channel, ['status' => 'ready']);

    $url = app(VideoSourceService::class)->embedUrl($item);
    parse_str((string) parse_url($url, PHP_URL_QUERY), $q);

    expect($url)->toStartWith('https://iframe.mediadelivery.net/embed/' . LIB_ID . '/' . VIDEO_GUID . '?')
        ->and($q['token'])->toBe(hash('sha256', 'zone-secret' . VIDEO_GUID . $q['expires']))
        ->and(app(VideoSourceService::class)->embedUrl(bunnyItem($channel, [
            'status' => 'processing', 'bunny_video_id' => (string) Str::uuid(),
        ])))->toBeNull();
});

it('lets admins open the Bunny player preview', function () {
    $item = bunnyItem(bunnyChannel(bunnyProvider()), ['status' => 'ready']);
    $admin = User::create(Arr::except(User::factory()->admin()->raw(), ['email_verified_at', 'remember_token']));

    Livewire::actingAs($admin)->test(\App\Livewire\Admin\ContentItems::class)
        ->assertSee('Sermon')
        ->call('preview', $item->id)
        ->assertSet('showPreview', true)
        ->assertSee('iframe.mediadelivery.net/embed/' . LIB_ID . '/' . VIDEO_GUID, false)
        ->set('showPreview', false)
        ->assertSet('previewUrl', null);
});

it('blocks providers from the admin content page', function () {
    $provider = bunnyProvider();

    $this->actingAs($provider->user)->get(route('admin.content.index'))->assertForbidden();
});

it('lets providers preview their own videos in the Bunny player', function () {
    $provider = bunnyProvider();
    $channel = bunnyChannel($provider);
    $item = bunnyItem($channel, ['status' => 'ready']);

    Livewire::actingAs($provider->user)->test(\App\Livewire\Provider\ContentItems::class, ['channel' => $channel])
        ->call('preview', $item->id)
        ->assertSet('showPreview', true)
        ->assertSee('iframe.mediadelivery.net/embed/' . LIB_ID . '/' . VIDEO_GUID, false);
});

it('does not let providers preview videos from another channel', function () {
    $provider = bunnyProvider();
    $own = bunnyChannel($provider);
    $foreign = bunnyItem(bunnyChannel($provider), ['status' => 'ready']);

    expect(fn () => Livewire::actingAs($provider->user)->test(\App\Livewire\Provider\ContentItems::class, ['channel' => $own])
        ->call('preview', $foreign->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});
