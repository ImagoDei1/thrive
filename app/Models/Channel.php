<?php

declare(strict_types=1);

namespace App\Models;

use App\Jobs\SyncBunnyLibraryNameJob;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Channel extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'provider_id',
        'name',
        'slug',
        'description',
        'logo_url',
        'banner_url',
        'category',
        'language',
        'is_active',
        'is_live',
        'rtmp_key',
        'rtmp_ingest_url',
        'hls_playback_url',
        'bunny_stream_id',
        'verified_at',
        'verified_by',
    ];

    protected static function booted(): void
    {
        // Keep the provider's Bunny library named after their first channel.
        static::created(fn (Channel $channel) => SyncBunnyLibraryNameJob::dispatch($channel->provider_id));

        static::updated(function (Channel $channel): void {
            if ($channel->wasChanged('slug')) {
                SyncBunnyLibraryNameJob::dispatch($channel->provider_id);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_live'   => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function categoryLabel(): string
    {
        return ReferenceValue::label(ReferenceValue::CHANNEL_CATEGORY, $this->category);
    }

    public function languageLabel(): string
    {
        return ReferenceValue::label(ReferenceValue::CHANNEL_LANGUAGE, $this->language);
    }

    /** Stream credentials are only issued for channels an admin has verified. */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_id');
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(ContentItem::class);
    }

    public function playlists(): HasMany
    {
        return $this->hasMany(Playlist::class);
    }

    public function watchSessions(): HasMany
    {
        return $this->hasMany(WatchSession::class);
    }

    public function watchTimeDaily(): HasMany
    {
        return $this->hasMany(WatchTimeDaily::class);
    }

    public function watchTimeMonthly(): HasMany
    {
        return $this->hasMany(WatchTimeMonthly::class);
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChannelChangeRequest::class);
    }
}
