<?php

declare(strict_types=1);

namespace App\Services\Bunny;

/**
 * Bunny Stream video object status (GET /library/{id}/videos/{guid} → "status").
 * Note: webhook payload "Status" uses a different enum; we only use the
 * webhook as a trigger and always re-read the video from the API.
 */
enum BunnyVideoStatus: int
{
    case Created = 0;
    case Uploaded = 1;
    case Processing = 2;
    case Transcoding = 3;
    case Finished = 4;
    case Error = 5;
    case UploadFailed = 6;
    case JitSegmenting = 7;
    case JitPlaylistsCreated = 8;

    /** Maps to content_items.status. */
    public function contentStatus(): string
    {
        return match ($this) {
            self::Created => 'uploading',
            self::Finished => 'ready',
            self::Error, self::UploadFailed => 'failed',
            default => 'processing',
        };
    }
}
