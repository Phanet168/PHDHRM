<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class UnitClusterPermission extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'org_unit_id',
        'activity_cluster_id',
        'can_create',
        'can_review',
        'can_approve',
        'can_consolidate',
    ];

    protected $casts = [
        'can_create' => 'bool',
        'can_review' => 'bool',
        'can_approve' => 'bool',
        'can_consolidate' => 'bool',
    ];
}
