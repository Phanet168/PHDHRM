<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OrgUnitTypePosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_type_id',
        'position_id',
        'hierarchy_rank',
        'is_leadership',
        'can_approve',
        'is_active',
        'note',
    ];

    protected $casts = [
        'hierarchy_rank' => 'integer',
        'is_leadership' => 'boolean',
        'can_approve' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        if (Auth::check()) {
            self::creating(function ($model) {
                $model->created_by = Auth::id();
            });

            self::updating(function ($model) {
                $model->updated_by = Auth::id();
            });
        }
    }

    public function unitType()
    {
        return $this->belongsTo(OrgUnitType::class, 'unit_type_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
