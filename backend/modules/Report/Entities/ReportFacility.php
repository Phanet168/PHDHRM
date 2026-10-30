<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Read-only view of the HRM org units (`departments`) used as health facilities:
 * PHD, operational district, hospitals, health centres, health posts.
 */
class ReportFacility extends Model
{
    use SoftDeletes;

    public const TYPES = ['phd', 'operational_district', 'provincial_hospital', 'district_hospital', 'health_center', 'health_center_with_bed', 'health_center_without_bed', 'health_post'];

    protected $table = 'departments';
    protected $guarded = ['*'];

    public function getNameAttribute(): string
    {
        return (string) $this->department_name;
    }

    public function scopeHealthFacilities($query)
    {
        return $query->select('departments.*', 't.code as type_code', 't.name_km as type_name')
            ->join('org_unit_types as t', 't.id', '=', 'departments.unit_type_id')
            ->whereIn('t.code', self::TYPES)
            ->orderBy('t.id')->orderBy('departments.sort_order')->orderBy('departments.department_name');
    }
}
