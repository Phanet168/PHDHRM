<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransportType extends Model
{
    protected $fillable = ['code', 'name', 'name_km', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function missions(): HasMany
    {
        return $this->hasMany(Mission::class);
    }
}

