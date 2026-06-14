<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class PlanRollup extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'parent_plan_id',
        'child_plan_id',
        'rollup_org_unit_id',
        'rolled_cost',
        'rolled_revenue',
        'rolled_items_count',
        'rolled_at',
        'rolled_by',
    ];

    protected $casts = [
        'rolled_cost' => 'decimal:2',
        'rolled_revenue' => 'decimal:2',
        'rolled_at' => 'datetime',
    ];

    public function parentPlan()
    {
        return $this->belongsTo(Plan::class, 'parent_plan_id');
    }

    public function childPlan()
    {
        return $this->belongsTo(Plan::class, 'child_plan_id');
    }
}
