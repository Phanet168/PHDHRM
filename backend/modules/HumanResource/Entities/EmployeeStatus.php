<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EmployeeStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name_km',
        'name_en',
        'transition_group',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'transition_group' => 'string',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
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
            $builder->orderBy('sort_order')->orderBy('name_en');
        });
    }

    public function getDisplayNameAttribute(): string
    {
        $km = trim((string) $this->name_km);
        return $km !== '' ? $km : trim((string) $this->name_en);
    }
}
