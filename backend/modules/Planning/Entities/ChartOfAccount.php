<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class ChartOfAccount extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'code',
        'chapter_code',
        'chapter_name',
        'account_code',
        'account_name',
        'subaccount_code',
        'subaccount_name',
        'expense_type',
        'name',
        'name_km',
        'description',
        'is_active',
    ];

    protected $casts = ['is_active' => 'bool'];

    public function planItemCosts()
    {
        return $this->hasMany(PlanItemCost::class);
    }
}
