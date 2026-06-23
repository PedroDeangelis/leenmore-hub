<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * An app-wide key/value setting, edited by admins in the Options tool. Reads are
 * cached and busted on write.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    /**
     * Read a setting value, falling back to $default when the key is absent.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = Cache::rememberForever(
            self::cacheKey($key),
            fn () => static::query()->where('key', $key)->value('value'),
        );

        return $value ?? $default;
    }

    /**
     * Create or update a setting, then bust its cached value.
     */
    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::cacheKey($key));
    }

    private static function cacheKey(string $key): string
    {
        return 'setting:'.$key;
    }
}
