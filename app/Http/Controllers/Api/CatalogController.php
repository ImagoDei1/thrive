<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    // ---------------------------------------------------------------------------
    // GET /api/categories
    // ---------------------------------------------------------------------------
    public function categories(): JsonResponse
    {
        $categories = Channel::query()
            ->where('is_active', true)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json(['data' => $categories]);
    }

    // ---------------------------------------------------------------------------
    // GET /api/channels
    // ---------------------------------------------------------------------------
    public function channels(Request $request): JsonResponse
    {
        $request->validate([
            'category' => 'nullable|string|max:100',
            'is_live'  => 'nullable|in:0,1',
            'page'     => 'nullable|integer|min:1',
        ]);

        $hasActiveSubscription = $this->hasActiveSubscription($request->user());

        $query = Channel::query()
            ->where('is_active', true)
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('is_live'), fn ($q) => $q->where('is_live', (bool) $request->is_live))
            ->orderByDesc('is_live')
            ->orderBy('name');

        $paginated = $query->paginate(12);

        $paginated->through(fn (Channel $channel) => $this->channelShape($channel, $hasActiveSubscription));

        return response()->json($paginated);
    }

    // ---------------------------------------------------------------------------
    // GET /api/channels/{id}
    // ---------------------------------------------------------------------------
    public function channel(Request $request, string $id): JsonResponse
    {
        $channel = Channel::where('id', $id)
            ->where('is_active', true)
            ->first();

        if (! $channel) {
            return response()->json(['message' => 'Channel not found'], 404);
        }

        $hasActiveSubscription = $this->hasActiveSubscription($request->user());

        $contentItems = $channel->contentItems()
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderByDesc('published_at')
            ->get()
            ->map(fn ($item) => $this->contentItemShape($item, $hasActiveSubscription));

        return response()->json([
            'channel'      => $this->channelShape($channel, $hasActiveSubscription),
            'content_items' => $contentItems,
        ]);
    }

    // ---------------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------------

    private function hasActiveSubscription(User $user): bool
    {
        $profile = $user->subscriberProfile()->first();

        if (! $profile) {
            return false;
        }

        return $profile->subscriptions()
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->exists();
    }

    private function channelShape(Channel $channel, bool $hasActiveSub): array
    {
        return [
            'id'               => $channel->id,
            'name'             => $channel->name,
            'slug'             => $channel->slug,
            'description'      => $channel->description,
            'logo_url'         => $channel->logo_url,
            'banner_url'       => $channel->banner_url,
            'category'         => $channel->category,
            'language'         => $channel->language,
            'is_live'          => $channel->is_live,
            'hls_playback_url' => $hasActiveSub ? $channel->hls_playback_url : null,
        ];
    }

    private function contentItemShape(\App\Models\ContentItem $item, bool $hasActiveSub): array
    {
        return [
            'id'               => $item->id,
            'title'            => $item->title,
            'description'      => $item->description,
            'type'             => $item->type,
            'thumbnail_url'    => $item->thumbnail_url,
            'duration_seconds' => $item->duration_seconds,
            'published_at'     => $item->published_at?->toIso8601String(),
            'hls_url'          => $hasActiveSub ? $item->hls_url : null,
        ];
    }
}
