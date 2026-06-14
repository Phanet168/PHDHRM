<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\HumanResource\Entities\Department;
use Modules\Planning\Support\GeneratesUuid;

class OrgUnit extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'source_department_id',
        'parent_id',
        'code',
        'name',
        'name_km',
        'unit_type',
        'level',
        'hierarchy_path',
        'org_path_code',
        'manager_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function sourceDepartment()
    {
        return $this->belongsTo(Department::class, 'source_department_id');
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function plans()
    {
        return $this->hasMany(Plan::class);
    }

    public function descendantsQuery()
    {
        $path = trim((string) $this->hierarchy_path, '/');

        return self::query()->where(function ($query) use ($path) {
            $query
                ->where('id', $this->id)
                ->orWhere('hierarchy_path', 'like', $path . '/%');
        });
    }
}
