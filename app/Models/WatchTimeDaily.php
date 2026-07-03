<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchTimeDaily extends Model
{
    use HasUuids;

    protected $table = 'watch_time_daily';

    protected $fillable = [
        'channel_id',
        'user_id',
        'date',
        'total_seconds',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
