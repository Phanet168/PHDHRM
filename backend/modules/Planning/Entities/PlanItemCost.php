<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class PlanItemCost extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'plan_item_id',
        'chapter_id',
        'account_id',
        'sub_account_id',
        'chart_of_account_id',
        'funding_source_id',
        'cost_code',
        'cost_name',
        'chapter_code',
        'account_code',
        'subaccount_code',
        'qty',
        'implementer_count',
        'occurrence_count',
        'unit',
        'unit_price',
        'currency_code',
        'total_cost',
        'note',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'implementer_count' => 'decimal:2',
        'occurrence_count' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function item()
    {
        return $this->belongsTo(PlanItem::class, 'plan_item_id');
    }

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function subAccount()
    {
        return $this->belongsTo(SubAccount::class);
    }

    public function fundingSource()
    {
        return $this->belongsTo(FundingSource::class);
    }
}
