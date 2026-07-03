<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WatchTimeMonthly extends Model
{
    use HasUuids;

    protected $table = 'watch_time_monthly';

    protected $fillable = [
        'channel_id',
        'billing_period',
        'total_seconds',
        'total_hours',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
