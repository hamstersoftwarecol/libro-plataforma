<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Key/value settings editable from the admin dashboard. Secret values are encrypted at rest.
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const CACHE_KEY = 'settings.all';

    /**
     * Keys whose values are encrypted with the application key.
     */
    public const SECRET_KEYS = ['mail.password', 'bold.secret_key'];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * All settings, decrypted, cached until the next change.
     *
     * @return array<string, ?string>
     */
    public static function allValues(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => static::query()->pluck('value', 'key')
            ->map(fn (?string $value, string $key): ?string => $value !== null && in_array($key, self::SECRET_KEYS, true) ? Crypt::decryptString($value) : $value)
            ->all());
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = static::allValues()[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public static function has(string $key): bool
    {
        return static::get($key) !== null;
    }

    /**
     * Store several settings at once. Null removes a value.
     *
     * @param  array<string, ?string>  $values
     */
    public static function putMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value === null) {
                static::query()->whereKey($key)->delete();

                continue;
            }

            static::query()->updateOrCreate(['key' => $key], [
                'value' => in_array($key, self::SECRET_KEYS, true) ? Crypt::encryptString($value) : $value,
            ]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
