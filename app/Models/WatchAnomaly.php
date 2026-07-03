<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchAnomaly extends Model
{
    use HasUuids;
    protected $fillable = [
        'session_id',
        'position_delta',
        'actual_elapsed',
        'discrepancy_seconds',
        'type',
        'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WatchSession::class, 'session_id');
    }
}
