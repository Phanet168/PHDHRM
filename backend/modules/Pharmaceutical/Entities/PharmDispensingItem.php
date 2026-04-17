<?php

namespace Modules\Pharmaceutical\Entities;

use Illuminate\Database\Eloquent\Model;

class PharmDispensingItem extends Model
{
    protected $table = 'pharm_dispensing_items';

    protected $fillable = [
        'dispensing_id',
        'medicine_id',
        'quantity',
        'batch_no',
        'dosage_instruction',
        'duration_days',
        'note',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
    ];

    public function dispensing()
    {
        return $this->belongsTo(PharmDispensing::class, 'dispensing_id');
    }

    public function medicine()
    {
        return $this->belongsTo(PharmMedicine::class, 'medicine_id');
    }
}
