<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

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
}
