<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProviderKycDocument extends Model
{
    use HasUuids;

    public const TYPES = [
        'id_front'                  => 'ID document (front)',
        'id_back'                   => 'ID document (back)',
        'registration_certificate'  => 'Registration certificate',
        'proof_of_address'          => 'Proof of address',
    ];

    protected $fillable = [
        'provider_id',
        'type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected static function booted(): void
    {
        static::deleting(function (ProviderKycDocument $document): void {
            Storage::disk($document->disk)->delete($document->path);
        });
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ProviderProfile::class, 'provider_id');
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type));
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
