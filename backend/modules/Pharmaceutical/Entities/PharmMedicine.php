<?php

namespace Modules\Pharmaceutical\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PharmMedicine extends Model
{
    use SoftDeletes;

    protected $table = 'pharm_medicines';

    protected $fillable = [
        'category_id',
        'code',
        'name',
        'name_kh',
        'dosage_form',
        'strength',
        'unit',
        'manufacturer',
        'unit_price',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(PharmCategory::class, 'category_id');
    }

    public function facilityStocks()
    {
        return $this->hasMany(PharmFacilityStock::class, 'medicine_id');
    }
}
