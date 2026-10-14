<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthContentCategory extends Model
{
    protected $fillable = ['name_km', 'name_en', 'slug', 'icon', 'sort_order'];

    public function contents(): HasMany
    {
        return $this->hasMany(HealthContent::class, 'category_id');
    }
}
