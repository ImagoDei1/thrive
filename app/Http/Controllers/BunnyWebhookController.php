<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SyncBunnyVideoJob;
use App\Models\ContentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /webhooks/bunny
 * Payload: {"VideoLibraryId": 123, "VideoGuid": "...", "Status": 3}
 */
class BunnyWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'VideoLibraryId' => 'required',
            'VideoGuid'      => 'required|uuid',
            'Status'         => 'required|integer',
        ]);

        $item = ContentItem::where('bunny_video_id', $data['VideoGuid'])
            ->where('bunny_library_id', (string) $data['VideoLibraryId'])
            ->first();

        // Unknown videos are acknowledged so Bunny does not keep retrying.
        if ($item) {
            SyncBunnyVideoJob::dispatch($item->id);
        }

        return response()->json(['ok' => true]);
    }
}
