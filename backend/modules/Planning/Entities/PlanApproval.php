<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class PlanApproval extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'org_unit_id',
        'workflow_level',
        'review_scope',
        'acted_by',
        'action',
        'from_status',
        'to_status',
        'comment',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class);
    }
}
