<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;

/** ជំពូក — top-level grouping of report units. */
class ReportDepartment extends Model
{
    protected $table = 'report_departments';
    protected $fillable = ['code', 'source_id', 'name', 'sort_order', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function units()
    {
        return $this->hasMany(ReportUnit::class, 'department_id')->orderBy('sort_order')->orderBy('id');
    }

    public function indicators()
    {
        return $this->hasMany(ReportIndicator::class, 'department_id');
    }
}
