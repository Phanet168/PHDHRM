<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeeUnitPosting;
use Modules\HumanResource\Entities\GovSalaryScale;
use Modules\HumanResource\Entities\OrgUnitType;
use Modules\HumanResource\Entities\UserOrgRole;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department_name',
        'unit_type_id',
        'sort_order',
        'ssl_type_id',
        'location_code',
        'latitude',
        'longitude',
        'parent_id',
        'is_active',
    ];

    protected static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if (Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        self::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

        static::addGlobalScope('sortByLatest', function (Builder $builder) {
            $builder->orderByDesc('id');
        });
    }

    public function parentDept()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function unitType()
    {
        return $this->belongsTo(OrgUnitType::class, 'unit_type_id');
    }

    public function sslType()
    {
        return $this->belongsTo(GovSalaryScale::class, 'ssl_type_id');
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'department_id', 'id');
    }

    public function employeePostings()
    {
        return $this->hasMany(EmployeeUnitPosting::class, 'department_id', 'id');
    }

    public function userOrgRoles()
    {
        return $this->hasMany(UserOrgRole::class, 'department_id', 'id');
    }

    public function hasAncestor(int $ancestorId): bool
    {
        $parentId = $this->parent_id;
        $guard = 0;

        while ($parentId && $guard < 1000) {
            if ((int) $parentId === (int) $ancestorId) {
                return true;
            }

            $parent = self::withoutGlobalScopes()->select('id', 'parent_id')->find($parentId);

            if (!$parent) {
                break;
            }

            $parentId = $parent->parent_id;
            $guard++;
        }

        return false;
    }
}
