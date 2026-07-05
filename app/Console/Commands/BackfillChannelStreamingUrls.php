<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Channel;
use Illuminate\Console\Command;

class BackfillChannelStreamingUrls extends Command
{
    protected $signature   = 'channels:backfill-streaming-urls';
    protected $description = 'Recompute rtmp_ingest_url and hls_playback_url for all channels using the current streaming config';

    public function handle(): int
    {
        $channels = Channel::all();

        foreach ($channels as $channel) {
            $channel->update([
                'rtmp_ingest_url'  => config('streaming.rtmp_ingest_url'),
                'hls_playback_url' => route('hls.playback', ['slug' => $channel->slug, 'file' => 'index.m3u8']),
            ]);
        }

        $this->info("Updated streaming URLs for {$channels->count()} channel(s).");

        return self::SUCCESS;
    }
}
