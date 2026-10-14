<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaderRecommendationItem extends Model
{
    protected $fillable = ['recommendation_id', 'content_km', 'sort_order', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(LeaderRecommendation::class, 'recommendation_id');
    }
}
