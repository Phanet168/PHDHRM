<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class ActivityCluster extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = ['sub_program_id', 'code', 'name', 'name_km', 'description', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'bool'];

    public function subProgram()
    {
        return $this->belongsTo(SubProgram::class);
    }

    public function indicators()
    {
        return $this->hasMany(Indicator::class);
    }
}
