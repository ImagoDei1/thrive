<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelChangeRequest extends Model
{
    use HasUuids;

    protected $fillable = [
        'channel_id',
        'provider_id',
        'field',
        'current_value',
        'requested_value',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function fieldLabel(): string
    {
        return match ($this->field) {
            'name'        => 'Channel Name',
            'description' => 'Description',
            'category'    => 'Category',
            'language'    => 'Language',
            default       => ucfirst($this->field),
        };
    }
}
