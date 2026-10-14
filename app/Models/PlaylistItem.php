<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PlaylistItem extends Pivot
{
    use HasUuids;

    protected $table = 'playlist_items';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'position'      => 'integer',
            'season_number' => 'integer',
        ];
    }
}
