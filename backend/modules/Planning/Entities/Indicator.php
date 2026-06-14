<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class Indicator extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'activity_cluster_id',
        'code',
        'name',
        'name_km',
        'unit_of_measure',
        'value_type',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
    ];

    public function activityCluster()
    {
        return $this->belongsTo(ActivityCluster::class);
    }
}
