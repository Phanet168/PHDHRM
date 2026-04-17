<?php

namespace Modules\Pharmaceutical\Entities;

use Illuminate\Database\Eloquent\Model;

class PharmReportItem extends Model
{
    protected $table = 'pharm_report_items';

    protected $fillable = [
        'report_id',
        'medicine_id',
        'opening_stock',
        'received_qty',
        'dispensed_qty',
        'adjustment_qty',
        'expired_qty',
        'damaged_qty',
        'closing_stock',
        'note',
    ];

    protected $casts = [
        'opening_stock' => 'decimal:2',
        'received_qty' => 'decimal:2',
        'dispensed_qty' => 'decimal:2',
        'adjustment_qty' => 'decimal:2',
        'expired_qty' => 'decimal:2',
        'damaged_qty' => 'decimal:2',
        'closing_stock' => 'decimal:2',
    ];

    public function report()
    {
        return $this->belongsTo(PharmReport::class, 'report_id');
    }

    public function medicine()
    {
        return $this->belongsTo(PharmMedicine::class, 'medicine_id');
    }
}
