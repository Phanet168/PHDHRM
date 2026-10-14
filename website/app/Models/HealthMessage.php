<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthMessage extends Model
{
    protected $fillable = [
        'message_km',
        'icon',
        'accent_color',
        'source_name',
        'source_url',
        'sort_order',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }
}
