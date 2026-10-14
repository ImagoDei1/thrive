<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\DeleteBunnyVideoJob;
use App\Jobs\SyncBunnyVideoJob;
use App\Models\ContentItem;
use Illuminate\Console\Command;

/**
 * Safety net for missed Bunny webhooks and abandoned browser uploads.
 */
class SyncPendingBunnyVideos extends Command
{
    protected $signature   = 'bunny:sync-pending {--stale-minutes=10 : Re-check processing items not updated for this long}';
    protected $description = 'Re-sync Bunny videos stuck in processing and fail uploads abandoned past the upload window';

    public function handle(): int
    {
        $staleBefore = now()->subMinutes((int) $this->option('stale-minutes'));

        $processing = ContentItem::query()
            ->where('status', 'processing')
            ->whereNotNull('bunny_video_id')
            ->where('updated_at', '<', $staleBefore)
            ->pluck('id');

        $processing->each(fn (string $id) => SyncBunnyVideoJob::dispatch($id));

        // Presigned TUS headers expire after upload_ttl_seconds, so these can never finish.
        $abandoned = ContentItem::query()
            ->where('status', 'uploading')
            ->whereNotNull('bunny_video_id')
            ->where('created_at', '<', now()->subSeconds((int) config('services.bunny.upload_ttl_seconds')))
            ->get();

        foreach ($abandoned as $item) {
            $item->update(['status' => 'failed']);
            DeleteBunnyVideoJob::dispatch($item->id);
        }

        $this->info("Queued {$processing->count()} sync(s); failed {$abandoned->count()} abandoned upload(s).");

        return self::SUCCESS;
    }
}
