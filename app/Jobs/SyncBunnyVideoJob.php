<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ContentItem;
use App\Services\Bunny\BunnyStreamService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Triggered by the Bunny webhook. Re-reads the video from the Bunny API rather
 * than trusting the webhook payload.
 */
class SyncBunnyVideoJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public int $uniqueFor = 30;

    public function __construct(public readonly string $contentItemId) {}

    public function uniqueId(): string
    {
        return $this->contentItemId;
    }

    public function handle(BunnyStreamService $bunny): void
    {
        $item = ContentItem::find($this->contentItemId);

        if ($item?->bunny_video_id) {
            $bunny->syncVideo($item);
        }
    }
}
