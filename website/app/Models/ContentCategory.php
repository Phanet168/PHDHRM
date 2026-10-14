<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentCategory extends Model
{
    protected $fillable = ['parent_id', 'name_km', 'name_en', 'slug', 'description', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(Content::class, 'category_id');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
