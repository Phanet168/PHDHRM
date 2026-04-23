<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\HumanResource\Entities\CandidateSelection;
use Modules\HumanResource\Entities\OrgUnitTypePosition;
use Modules\HumanResource\Entities\UserAssignment;

class Position extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'position_name',
        'position_name_km',
        'position_details',
        'position_rank',
        'budget_amount',
        'is_prov_level',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_prov_level' => 'boolean',
        'position_rank' => 'integer',
        'budget_amount' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();
        if (Auth::check()) {
            self::creating(function ($model) {
                $model->uuid = (string) Str::uuid();
                $model->created_by = Auth::id();
            });

            self::updating(function ($model) {
                $model->updated_by = Auth::id();
            });
        }

        static::addGlobalScope('sortByLatest', function (Builder $builder) {
            $builder->orderByDesc('id');
        });
    }

    public function candidates()
    {
        return $this->hasMany(CandidateSelection::class, 'position_id', 'id');
    }

    public function unitTypeMappings()
    {
        return $this->hasMany(OrgUnitTypePosition::class, 'position_id', 'id');
    }

    public function userAssignments()
    {
        return $this->hasMany(UserAssignment::class, 'position_id', 'id');
    }
}
