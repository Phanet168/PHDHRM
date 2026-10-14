<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per singleton About section: hero, history, mission. */
class AboutSection extends Model
{
    public const KEYS = [
        'hero' => 'ផ្ទាំងខាងលើ (Hero)',
        'history' => 'ប្រវត្តិ និងបេសកកម្ម',
        'mission' => 'សារបេសកកម្ម',
    ];

    protected $fillable = [
        'key', 'title', 'subtitle', 'body', 'media_id', 'image_position',
        'icon_key', 'is_active', 'sort_order', 'is_demo', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function for(string $key): self
    {
        abort_unless(array_key_exists($key, self::KEYS), 404);

        return static::firstOrNew(['key' => $key], ['is_active' => true, 'image_position' => 'right']);
    }
}
