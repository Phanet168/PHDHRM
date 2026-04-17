<?php

namespace Modules\Pharmaceutical\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PharmCategory extends Model
{
    use SoftDeletes;

    protected $table = 'pharm_categories';

    protected $fillable = [
        'name',
        'name_kh',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function medicines()
    {
        return $this->hasMany(PharmMedicine::class, 'category_id');
    }
}
