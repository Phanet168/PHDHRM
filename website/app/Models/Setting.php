<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, ?string $default = null): ?string
    {
        return Cache::rememberForever('settings', fn () => static::pluck('value', 'key'))[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings');
    }

    public static function allSettings(): array
    {
        return Cache::rememberForever('settings', fn () => static::query()->pluck('value', 'key'))->toArray();
    }
}
