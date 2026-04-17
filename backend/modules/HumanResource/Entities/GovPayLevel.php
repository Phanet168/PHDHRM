<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class GovPayLevel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'level_code',
        'level_name_km',
        'budget_amount',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'budget_amount' => 'float',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if (Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        static::addGlobalScope('sortByOrder', function (Builder $builder) {
            $builder->orderBy('sort_order')->orderBy('level_code');
        });
    }

    public function employeePayGradeHistories()
    {
        return $this->hasMany(EmployeePayGradeHistory::class, 'pay_level_id');
    }
}
