<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WatchSession extends Model
{
    use HasUuids;
    protected $fillable = [
        'user_id',
        'channel_id',
        'content_id',
        'content_type',
        'total_seconds',
        'last_position',
        'started_at',
        'last_heartbeat_at',
        'platform',
        'quality',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'started_at'        => 'datetime',
            'last_heartbeat_at' => 'datetime',
            'is_active'         => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_id');
    }

    public function anomalies(): HasMany
    {
        return $this->hasMany(WatchAnomaly::class, 'session_id');
    }
}
