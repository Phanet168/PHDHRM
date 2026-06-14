<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class PlanRevenueLine extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'revenue_code',
        'revenue_name',
        'funding_source_id',
        'quantity',
        'unit',
        'unit_price',
        'total_amount',
        'note',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function fundingSource()
    {
        return $this->belongsTo(FundingSource::class);
    }
}
