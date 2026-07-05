<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MediaMtxController extends Controller
{
    /**
     * Called by MediaMTX (authHTTPAddress) on every publish/read attempt.
     * "path" arrives with no leading slash, e.g. "live/{rtmp_key}". Only
     * publishing is restricted to an approved provider's own channel;
     * reading (HLS/WebRTC playback) is left open since subscriber auth
     * happens at the Laravel/app layer, not at MediaMTX.
     */
    public function auth(Request $request): Response
    {
        if ($request->input('action') !== 'publish') {
            return response()->noContent(200);
        }

        $channel = $this->channelForPath($request->string('path')->toString());

        if (! $channel || ! $channel->provider?->is_approved) {
            return response()->noContent(401);
        }

        return response()->noContent(200);
    }

    /**
     * Called via runOnReady (curl in mediamtx.yml) once the published
     * stream is actually decodable, not just connected.
     */
    public function published(Request $request): JsonResponse
    {
        $this->channelForPath($request->string('path')->toString())
            ?->update(['is_live' => true]);

        return response()->json(['ok' => true]);
    }

    /**
     * Called via runOnNotReady once the stream stops being readable.
     */
    public function unpublished(Request $request): JsonResponse
    {
        $this->channelForPath($request->string('path')->toString())
            ?->update(['is_live' => false]);

        return response()->json(['ok' => true]);
    }

    private function channelForPath(string $path): ?Channel
    {
        $rtmpKey = str($path)->after('live/')->toString();

        return Channel::where('rtmp_key', $rtmpKey)->first();
    }
}
