<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrgUnitTypeRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_type_id',
        'child_type_id',
    ];

    public function parentType()
    {
        return $this->belongsTo(OrgUnitType::class, 'parent_type_id');
    }

    public function childType()
    {
        return $this->belongsTo(OrgUnitType::class, 'child_type_id');
    }
}
