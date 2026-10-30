<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;

/** ផ្នែក — the unit responsible for a set of indicators. */
class ReportUnit extends Model
{
    protected $table = 'report_units';
    protected $fillable = ['department_id', 'code', 'source_id', 'name', 'unit_type', 'sort_order', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function department()
    {
        return $this->belongsTo(ReportDepartment::class, 'department_id');
    }

    public function indicators()
    {
        return $this->hasMany(ReportIndicator::class, 'unit_id');
    }
}
