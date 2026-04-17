<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrgUnitType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'name_km',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parentRules()
    {
        return $this->hasMany(OrgUnitTypeRule::class, 'parent_type_id');
    }

    public function childRules()
    {
        return $this->hasMany(OrgUnitTypeRule::class, 'child_type_id');
    }

    public function positionMappings()
    {
        return $this->hasMany(OrgUnitTypePosition::class, 'unit_type_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getDisplayNameAttribute(): string
    {
        if (app()->getLocale() === 'km' && !empty($this->name_km)) {
            return $this->name_km;
        }

        return $this->name;
    }
}
