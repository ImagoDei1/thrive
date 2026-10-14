<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Playlist extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'channel_id',
        'title',
        'description',
        'thumbnail_url',
        'kind',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** Ordered by season (series only), then position. */
    public function contentItems(): BelongsToMany
    {
        return $this->belongsToMany(ContentItem::class, 'playlist_items')
            ->using(PlaylistItem::class)
            ->withPivot(['id', 'position', 'season_number'])
            ->withTimestamps()
            ->orderByPivot('season_number')
            ->orderByPivot('position');
    }

    public function isSeries(): bool
    {
        return $this->kind === 'series';
    }

    public function latestSeason(): int
    {
        return max(1, (int) PlaylistItem::where('playlist_id', $this->id)->max('season_number'));
    }

    /**
     * Appends videos to the end of the playlist, skipping ones already in it.
     * Series videos go into $seasonNumber, defaulting to the latest season.
     */
    public function addVideos(iterable $contentItems, ?int $seasonNumber = null): int
    {
        $seasonNumber = $this->isSeries() ? ($seasonNumber ?? $this->latestSeason()) : null;
        $existing = $this->contentItems()->pluck('content_items.id')->all();
        $position = (int) PlaylistItem::where('playlist_id', $this->id)->max('position');
        $added = 0;

        foreach ($contentItems as $item) {
            if ($item->channel_id !== $this->channel_id) {
                throw new InvalidArgumentException('Videos can only be added to playlists on the same channel.');
            }

            if (in_array($item->id, $existing, true)) {
                continue;
            }

            $this->contentItems()->attach($item->id, [
                'position'      => ++$position,
                'season_number' => $seasonNumber,
            ]);
            $existing[] = $item->id;
            $added++;
        }

        return $added;
    }

    /** Published playlists, for the subscriber catalog. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
