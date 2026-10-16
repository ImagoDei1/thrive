<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContentItem;
use App\Models\ContentLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentLikeController extends Controller
{
    // ---------------------------------------------------------------------------
    // POST /api/content-items/{id}/like — idempotent, safe to double-tap
    // ---------------------------------------------------------------------------
    public function store(Request $request, string $id): JsonResponse
    {
        $item = $this->findLikeable($id);

        if (! $item) {
            return response()->json(['message' => 'Video not found'], 404);
        }

        ContentLike::createOrFirst([
            'user_id'         => $request->user()->id,
            'content_item_id' => $item->id,
        ]);

        return $this->likeState($item, true);
    }

    // ---------------------------------------------------------------------------
    // DELETE /api/content-items/{id}/like — idempotent
    // ---------------------------------------------------------------------------
    public function destroy(Request $request, string $id): JsonResponse
    {
        $item = $this->findLikeable($id);

        if (! $item) {
            return response()->json(['message' => 'Video not found'], 404);
        }

        $item->likes()->where('user_id', $request->user()->id)->delete();

        return $this->likeState($item, false);
    }

    /** Only videos the catalog would show can be liked. */
    private function findLikeable(string $id): ?ContentItem
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return ContentItem::playable()
            ->whereKey($id)
            ->whereHas('channel', fn ($q) => $q->where('is_active', true))
            ->first();
    }

    private function likeState(ContentItem $item, bool $liked): JsonResponse
    {
        return response()->json([
            'content_id' => $item->id,
            'liked'      => $liked,
            'like_count' => $item->likes()->count(),
        ]);
    }
}
