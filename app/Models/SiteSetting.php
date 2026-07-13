<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::remember('site_setting:' . $key, 300, function () use ($key, $default) {
            $row = static::query()->where('key', $key)->first();
            return $row?->value ?? $default;
        });
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        Cache::forget('site_setting:' . $key);
    }
}
