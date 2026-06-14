<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class Program extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = ['code', 'name', 'name_km', 'description', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'bool'];

    public function subPrograms()
    {
        return $this->hasMany(SubProgram::class);
    }
}
