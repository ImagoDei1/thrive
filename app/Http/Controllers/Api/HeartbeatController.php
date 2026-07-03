<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\WatchSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Validator;

class HeartbeatController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'session_id'            => 'required|uuid',
            'channel_id'            => 'required|uuid',
            'actual_elapsed_seconds'=> 'required|integer|min:1|max:60',
            'playback_position'     => 'required|integer|min:0',
            'content_type'          => 'required|in:live,vod',
            'content_id'            => 'nullable|uuid',
            'platform'              => 'nullable|in:android,ios,web,tv',
            'quality'               => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }

        $user = $request->user();

        // Verify channel exists and is active
        $channel = Channel::where('id', $request->channel_id)
            ->where('is_active', true)
            ->first();

        if (! $channel) {
            return response()->json(['error' => 'Channel not found or inactive'], 404);
        }

        // Ensure watch session exists, create if missing (e.g. app resumed after crash)
        WatchSession::firstOrCreate(
            ['id' => $request->session_id],
            [
                'user_id'      => $user->id,
                'channel_id'   => $request->channel_id,
                'content_id'   => $request->content_id,
                'content_type' => $request->content_type,
                'platform'     => $request->platform,
                'quality'      => $request->quality,
                'started_at'   => now(),
                'is_active'    => true,
            ]
        );

        // Push heartbeat payload to Redis queue
        Redis::lpush('heartbeats:queue', json_encode([
            'session_id'             => $request->session_id,
            'viewer_id'              => $user->id,
            'channel_id'             => $request->channel_id,
            'actual_elapsed_seconds' => $request->actual_elapsed_seconds,
            'playback_position'      => $request->playback_position,
            'timestamp'              => now()->toIso8601String(),
        ]));

        return response()->json(['ok' => true]);
    }
}
