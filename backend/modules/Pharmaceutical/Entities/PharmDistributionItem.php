<?php

namespace Modules\Pharmaceutical\Entities;

use Illuminate\Database\Eloquent\Model;

class PharmDistributionItem extends Model
{
    protected $table = 'pharm_distribution_items';

    protected $fillable = [
        'distribution_id',
        'medicine_id',
        'quantity_sent',
        'quantity_received',
        'batch_no',
        'expiry_date',
        'unit_price',
        'note',
    ];

    protected $casts = [
        'quantity_sent' => 'decimal:2',
        'quantity_received' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'expiry_date' => 'date',
    ];

    public function distribution()
    {
        return $this->belongsTo(PharmDistribution::class, 'distribution_id');
    }

    public function medicine()
    {
        return $this->belongsTo(PharmMedicine::class, 'medicine_id');
    }
}
