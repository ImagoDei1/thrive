<?php

declare(strict_types=1);

use App\Jobs\AggregateHeartbeatsJob;
use App\Models\Channel;
use App\Models\ContentItem;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Models\WatchSession;
use App\Models\WatchTimeDaily;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

beforeEach(function () {
    Redis::del('heartbeats:queue');
});

function heartbeatVideo(): ContentItem
{
    $user = User::create(Arr::except(User::factory()->provider()->raw(), ['email_verified_at', 'remember_token']));
    $provider = ProviderProfile::create([
        'user_id'      => $user->id,
        'company_name' => 'Grace Church',
        'is_approved'  => true,
    ]);
    $channel = Channel::create([
        'provider_id' => $provider->id,
        'name'        => 'Sunday Service',
        'slug'        => 'sunday-' . Str::random(6),
        'is_active'   => true,
    ]);

    return ContentItem::create([
        'channel_id'   => $channel->id,
        'title'        => 'Sermon',
        'status'       => 'ready',
        'published_at' => now()->subDay(),
    ]);
}

it('keeps the client session id and credits each heartbeat to that session', function () {
    $video = heartbeatVideo();
    $user = User::create(Arr::except(User::factory()->subscriber()->raw(), ['email_verified_at', 'remember_token']));
    $user->forceFill(['api_token' => 'hb-token'])->save();
    $sessionId = (string) Str::uuid();

    foreach ([30, 25] as $i => $elapsed) {
        $this->withToken('hb-token')->postJson('/api/heartbeat', [
            'session_id'             => $sessionId,
            'channel_id'             => $video->channel_id,
            'content_id'             => $video->id,
            'content_type'           => 'vod',
            'actual_elapsed_seconds' => $elapsed,
            'playback_position'      => 30 * ($i + 1),
        ])->assertOk();
    }

    (new AggregateHeartbeatsJob())->handle();

    expect(WatchSession::count())->toBe(1);
    $session = WatchSession::find($sessionId);
    expect($session)->not->toBeNull()
        ->and($session->content_id)->toBe($video->id)
        ->and($session->total_seconds)->toBe(55)
        ->and($session->last_heartbeat_at)->not->toBeNull()
        ->and((int) WatchTimeDaily::sum('total_seconds'))->toBe(55);
});
