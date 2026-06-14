<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class PlanPersonnelLine extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'plan_item_id',
        'cadre_name',
        'cadre_name_km',
        'person_count',
        'days_count',
        'unit_cost',
        'total_cost',
        'note',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(PlanItem::class, 'plan_item_id');
    }
}
