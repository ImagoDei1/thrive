<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ContentItem;
use App\Models\Playlist;
use App\Models\Subscription;
use App\Models\User;
use App\Services\VideoSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    public function __construct(private readonly VideoSourceService $videoSources) {}

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
            'search'   => 'nullable|string|max:100',
            'page'     => 'nullable|integer|min:1',
        ]);

        $hasActiveSubscription = $this->hasActiveSubscription($request->user());

        $query = Channel::query()
            ->where('is_active', true)
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->category))
            ->when($request->filled('is_live'), fn ($q) => $q->where('is_live', (bool) $request->is_live))
            ->when($request->filled('search'), fn ($q) => $q->whereLike(
                'name',
                '%' . addcslashes($request->search, '%_\\') . '%',
            ))
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

        $channel->load('provider');

        $contentItems = $channel->contentItems()
            ->playable()
            ->withLikeState($request->user())
            ->orderByDesc('published_at')
            ->get()
            ->each(fn ($item) => $item->setRelation('channel', $channel))
            ->map(fn ($item) => $this->contentItemShape($item, $hasActiveSubscription));

        // First playable item doubles as the cover when no thumbnail was set.
        $playlists = $channel->playlists()
            ->visible()
            ->whereHas('contentItems', fn ($q) => $q->playable())
            ->withCount(['contentItems as item_count' => fn ($q) => $q->playable()])
            ->with(['contentItems' => fn ($q) => $q->playable()->limit(1)])
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (Playlist $playlist) => $this->playlistShape($playlist, $channel));

        return response()->json([
            'channel'       => $this->channelShape($channel, $hasActiveSubscription),
            'content_items' => $contentItems,
            'playlists'     => $playlists,
        ]);
    }

    // ---------------------------------------------------------------------------
    // GET /api/playlists/{id}
    // ---------------------------------------------------------------------------
    public function playlist(Request $request, string $id): JsonResponse
    {
        $playlist = ! Str::isUuid($id) ? null : Playlist::visible()
            ->whereKey($id)
            ->whereHas('channel', fn ($q) => $q->where('is_active', true))
            ->with('channel.provider')
            ->first();

        if (! $playlist) {
            return response()->json(['message' => 'Playlist not found'], 404);
        }

        $hasActiveSubscription = $this->hasActiveSubscription($request->user());
        $channel = $playlist->channel;

        $items = $playlist->contentItems()
            ->playable()
            ->withLikeState($request->user())
            ->get()
            ->each(fn (ContentItem $item) => $item->setRelation('channel', $channel));

        $playlist->setRelation('contentItems', $items->take(1));
        $playlist->item_count = $items->count();

        $response = [
            'playlist' => $this->playlistShape($playlist, $channel),
            'channel'  => $this->channelShape($channel, $hasActiveSubscription),
        ];

        if ($playlist->isSeries()) {
            // Episode numbers are derived from order within each season, so
            // removing or hiding a video never leaves gaps.
            $response['seasons'] = $items
                ->groupBy(fn (ContentItem $item) => $item->pivot->season_number ?? 1)
                ->map(fn ($episodes, $season) => [
                    'season_number' => (int) $season,
                    'episodes'      => $episodes->values()->map(fn (ContentItem $item, int $i) => [
                        ...$this->contentItemShape($item, $hasActiveSubscription),
                        'season_number'  => (int) $season,
                        'episode_number' => $i + 1,
                    ]),
                ])
                ->values();
        } else {
            $response['items'] = $items->values()->map(fn (ContentItem $item, int $i) => [
                ...$this->contentItemShape($item, $hasActiveSubscription),
                'position' => $i + 1,
            ]);
        }

        return response()->json($response);
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

    private function playlistShape(Playlist $playlist, Channel $channel): array
    {
        $cover = $playlist->contentItems->first()?->setRelation('channel', $channel);

        return [
            'id'            => $playlist->id,
            'channel_id'    => $playlist->channel_id,
            'title'         => $playlist->title,
            'description'   => $playlist->description,
            'kind'          => $playlist->kind,
            'thumbnail_url' => $playlist->thumbnail_url ?? ($cover ? $this->videoSources->thumbnailUrl($cover) : null),
            'item_count'    => (int) $playlist->item_count,
            'published_at'  => $playlist->published_at?->toIso8601String(),
        ];
    }

    private function contentItemShape(ContentItem $item, bool $hasActiveSub): array
    {
        return [
            'id'               => $item->id,
            'title'            => $item->title,
            'description'      => $item->description,
            'type'             => $item->type,
            'thumbnail_url'    => $this->videoSources->thumbnailUrl($item),
            'duration_seconds' => $item->duration_seconds,
            'published_at'     => $item->published_at?->toIso8601String(),
            'hls_url'          => $hasActiveSub ? $this->videoSources->hlsUrl($item) : null,
            'like_count'       => (int) $item->likes_count,
            'liked'            => (bool) $item->liked,
        ];
    }
}
