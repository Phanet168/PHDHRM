<?php

namespace Modules\Pharmaceutical\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HumanResource\Entities\Department;

class PharmStockAdjustment extends Model
{
    use SoftDeletes;

    public const TYPE_DAMAGED = 'damaged';
    public const TYPE_EXPIRED = 'expired';
    public const TYPE_LOSS = 'loss';
    public const TYPE_CORRECTION = 'correction';

    protected $table = 'pharm_stock_adjustments';

    protected $fillable = [
        'reference_no',
        'department_id',
        'medicine_id',
        'adjustment_type',
        'quantity',
        'batch_no',
        'expiry_date',
        'adjustment_date',
        'reason',
        'adjusted_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'expiry_date' => 'date',
        'adjustment_date' => 'date',
    ];

    public static function typeLabels(): array
    {
        return [
            self::TYPE_DAMAGED    => localize('damaged', 'Damaged'),
            self::TYPE_EXPIRED    => localize('expired', 'Expired'),
            self::TYPE_LOSS       => localize('loss', 'Loss'),
            self::TYPE_CORRECTION => localize('correction', 'Correction'),
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->adjustment_type] ?? $this->adjustment_type;
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function medicine()
    {
        return $this->belongsTo(PharmMedicine::class, 'medicine_id');
    }

    public function adjuster()
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
