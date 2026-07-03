<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WatchSession;
use App\Models\WatchTimeDaily;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class AggregateHeartbeatsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $heartbeats = [];

        // Drain up to 500 heartbeats per run
        while (count($heartbeats) < 500) {
            $raw = Redis::rpop('heartbeats:queue');
            if ($raw === null) {
                break;
            }
            $heartbeats[] = json_decode($raw, true);
        }

        if (empty($heartbeats)) {
            return;
        }

        foreach ($heartbeats as $hb) {
            // Update the live session — credit actual_elapsed_seconds only (never position_delta)
            WatchSession::where('id', $hb['session_id'])
                ->where('user_id', $hb['viewer_id'])
                ->update([
                    'total_seconds'     => DB::raw("total_seconds + {$hb['actual_elapsed_seconds']}"),
                    'last_heartbeat_at' => $hb['timestamp'],
                    'last_position'     => $hb['playback_position'],
                ]);

            // Upsert into daily aggregate
            WatchTimeDaily::upsert(
                [
                    'channel_id'    => $hb['channel_id'],
                    'user_id'       => $hb['viewer_id'],
                    'date'          => now()->toDateString(),
                    'total_seconds' => $hb['actual_elapsed_seconds'],
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ],
                ['channel_id', 'user_id', 'date'],
                ['total_seconds' => DB::raw('watch_time_daily.total_seconds + excluded.total_seconds'), 'updated_at' => now()]
            );
        }
    }
}
