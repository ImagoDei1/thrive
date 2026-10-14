<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ContentItem;
use App\Services\Bunny\BunnyStreamService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DeleteBunnyVideoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 600];

    public function __construct(public readonly string $contentItemId) {}

    public function handle(BunnyStreamService $bunny): void
    {
        $item = ContentItem::withTrashed()->find($this->contentItemId);

        // Only delete from Bunny if the item is still deleted (not restored meanwhile).
        if ($item?->trashed() || ($item && $item->status === 'failed')) {
            $bunny->deleteVideo($item);
        }
    }
}
