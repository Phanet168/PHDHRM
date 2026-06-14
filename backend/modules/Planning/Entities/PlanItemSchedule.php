<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class PlanItemSchedule extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'plan_item_id',
        'quarter',
        'month',
        'period_no',
        'period_type',
        'start_date',
        'end_date',
        'planned_quantity',
        'period_label',
        'activity_task_text',
        'goal_text',
        'expected_result_text',
        'verification_text',
        'note',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'planned_quantity' => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(PlanItem::class, 'plan_item_id');
    }
}
