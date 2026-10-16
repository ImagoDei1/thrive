<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;

/**
 * Admin-managed lookup values (channel categories, languages, organisation types, ...).
 * The `code` is what referencing rows store; `label` is display only and may change freely.
 */
class ReferenceValue extends Model
{
    use HasUuids;

    public const CHANNEL_CATEGORY  = 'channel_category';
    public const CHANNEL_LANGUAGE  = 'channel_language';
    public const ORGANISATION_TYPE = 'organisation_type';
    public const ID_TYPE           = 'id_type';

    /** type => [label, description, table.column that stores the code] */
    public const TYPES = [
        self::CHANNEL_CATEGORY  => ['Channel categories', 'Genres subscribers browse by in the app', 'channels.category'],
        self::CHANNEL_LANGUAGE  => ['Languages', 'Primary broadcast language of a channel', 'channels.language'],
        self::ORGANISATION_TYPE => ['Organisation types', 'Provider organisation types captured during KYC', 'provider_profiles.organisation_type'],
        self::ID_TYPE           => ['ID types', 'Identity documents accepted during KYC', 'provider_profiles.id_type'],
    ];

    protected $fillable = [
        'type',
        'code',
        'label',
        'description',
        'sort_order',
        'is_active',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
            'is_system'  => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (ReferenceValue $value) => Cache::forget(self::cacheKey($value->type)));
        static::deleted(fn (ReferenceValue $value) => Cache::forget(self::cacheKey($value->type)));
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type)->orderBy('sort_order')->orderBy('label');
    }

    /** Active values for pick lists: [code => label]. */
    public static function options(string $type): array
    {
        return self::cached($type)->where('is_active', true)->pluck('label', 'code')->all();
    }

    /** Active values shaped for MaryUI selects. */
    public static function selectOptions(string $type): array
    {
        return collect(self::options($type))
            ->map(fn (string $label, string $code) => ['id' => $code, 'name' => $label])
            ->values()
            ->all();
    }

    /** Label for a stored code — includes inactive values so historical records still read well. */
    public static function label(string $type, ?string $code): string
    {
        if ($code === null || $code === '') {
            return '—';
        }

        return self::cached($type)->firstWhere('code', $code)['label'] ?? ucfirst($code);
    }

    /** Validation rule restricting input to active codes of a type. */
    public static function rule(string $type): In
    {
        return Rule::in(array_keys(self::options($type)));
    }

    /** Number of records (including soft-deleted ones) currently storing this value's code. */
    public function usageCount(): int
    {
        return self::usageCounts($this->type)[$this->code] ?? 0;
    }

    /** @return array<string, int> code => number of records storing it */
    public static function usageCounts(string $type): array
    {
        [$table, $column] = explode('.', self::TYPES[$type][2]);

        return DB::table($table)->whereNotNull($column)->pluck($column)->countBy()->all();
    }

    /** @return Collection<int, array{code: string, label: string, is_active: bool}> */
    private static function cached(string $type): Collection
    {
        return collect(Cache::rememberForever(
            self::cacheKey($type),
            fn () => self::query()->ofType($type)->get(['code', 'label', 'is_active'])->toArray(),
        ));
    }

    private static function cacheKey(string $type): string
    {
        return "reference_values:{$type}";
    }
}
