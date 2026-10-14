<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\DeleteBunnyVideoJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentItem extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'channel_id',
        'title',
        'description',
        'type',
        'file_path',
        'hls_url',
        'dash_url',
        'thumbnail_url',
        'duration_seconds',
        'file_size_bytes',
        'status',
        'resolution',
        'qualities',
        'published_at',
        'bunny_library_id',
        'bunny_video_id',
    ];

    protected static function booted(): void
    {
        // Providers are told deletion is permanent, so free the Bunny storage too.
        static::deleted(function (ContentItem $item): void {
            if ($item->bunny_video_id) {
                DeleteBunnyVideoJob::dispatch($item->id);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'qualities'    => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function playlists(): BelongsToMany
    {
        return $this->belongsToMany(Playlist::class, 'playlist_items')
            ->using(PlaylistItem::class)
            ->withPivot(['id', 'position', 'season_number'])
            ->withTimestamps();
    }

    /** Ready and published: what subscribers may see in the catalog. */
    public function scopePlayable(Builder $query): Builder
    {
        return $query->where('content_items.status', 'ready')
            ->whereNotNull('content_items.published_at')
            ->where('content_items.published_at', '<=', now());
    }
}
