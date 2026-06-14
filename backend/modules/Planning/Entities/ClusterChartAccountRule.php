<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class ClusterChartAccountRule extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'activity_cluster_id',
        'chart_of_account_id',
        'is_required',
        'is_default',
        'min_amount',
        'max_amount',
    ];

    protected $casts = [
        'is_required' => 'bool',
        'is_default' => 'bool',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
    ];
}
