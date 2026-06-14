<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Planning\Support\GeneratesUuid;

class PlanItem extends Model
{
    use GeneratesUuid;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'plan_id',
        'program_id',
        'sub_program_id',
        'activity_cluster_id',
        'responsible_org_unit_id',
        'item_code',
        'title',
        'description',
        'item_type',
        'indicator_text',
        'target_text',
        'indicator',
        'target',
        'target_unit',
        'item_year',
        'item_period_no',
        'item_quarter',
        'period_label',
        'planned_quantity',
        'planned_unit',
        'total_cost',
        'sort_order',
    ];

    protected $casts = [
        'planned_quantity' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function responsibleOrgUnit()
    {
        return $this->belongsTo(OrgUnit::class, 'responsible_org_unit_id');
    }

    public function schedules()
    {
        return $this->hasMany(PlanItemSchedule::class);
    }

    public function costs()
    {
        return $this->hasMany(PlanItemCost::class);
    }

    public function indicators()
    {
        return $this->hasMany(PlanItemIndicator::class);
    }

    public function personnelLines()
    {
        return $this->hasMany(PlanPersonnelLine::class);
    }
}
