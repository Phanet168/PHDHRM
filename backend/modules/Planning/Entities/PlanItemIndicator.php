<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class PlanItemIndicator extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'plan_item_id',
        'indicator_id',
        'baseline_value',
        'target_value',
        'achieved_value',
        'value_text',
        'note',
    ];

    protected $casts = [
        'baseline_value' => 'decimal:2',
        'target_value' => 'decimal:2',
        'achieved_value' => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(PlanItem::class, 'plan_item_id');
    }

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }
}
