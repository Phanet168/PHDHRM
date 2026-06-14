<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class SubProgram extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = ['program_id', 'code', 'name', 'name_km', 'description', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'bool'];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function activityClusters()
    {
        return $this->hasMany(ActivityCluster::class);
    }
}
