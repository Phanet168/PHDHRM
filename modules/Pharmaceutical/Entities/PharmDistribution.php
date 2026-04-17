<?php

namespace Modules\Pharmaceutical\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HumanResource\Entities\Department;

class PharmDistribution extends Model
{
    use SoftDeletes;

    public const TYPE_PHD_TO_HOSPITAL = 'phd_to_hospital';
    public const TYPE_PHD_TO_OD = 'phd_to_od';
    public const TYPE_OD_TO_HC = 'od_to_hc';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_COMPLETED = 'completed';

    protected $table = 'pharm_distributions';

    protected $fillable = [
        'reference_no',
        'distribution_type',
        'from_department_id',
        'to_department_id',
        'distribution_date',
        'status',
        'note',
        'received_date',
        'received_note',
        'sent_by',
        'received_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'distribution_date' => 'date',
        'received_date' => 'date',
    ];

    public static function typeLabels(): array
    {
        return [
            self::TYPE_PHD_TO_HOSPITAL => localize('phd_to_hospital', 'មន្ទីរសុខាភិបាលខេត្ត → មន្ទីរពេទ្យ'),
            self::TYPE_PHD_TO_OD       => localize('phd_to_od', 'មន្ទីរសុខាភិបាលខេត្ត → ស្រុកប្រតិបត្តិ'),
            self::TYPE_OD_TO_HC        => localize('od_to_hc', 'ស្រុកប្រតិបត្តិ → មណ្ឌលសុខភាព'),
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT     => localize('draft', 'ព្រាង'),
            self::STATUS_SENT      => localize('sent', 'បានផ្ញើ'),
            self::STATUS_RECEIVED  => localize('received', 'បានទទួល'),
            self::STATUS_PARTIAL   => localize('partial', 'ទទួលមិនគ្រប់'),
            self::STATUS_COMPLETED => localize('completed', 'បានបញ្ចប់'),
        ];
    }

    public function getTypeLabelAttribute(): string
    {
        return (string) (self::typeLabels()[$this->distribution_type] ?? $this->distribution_type);
    }

    public function getStatusLabelAttribute(): string
    {
        return (string) (self::statusLabels()[$this->status] ?? $this->status);
    }

    public function fromDepartment()
    {
        return $this->belongsTo(Department::class, 'from_department_id');
    }

    public function toDepartment()
    {
        return $this->belongsTo(Department::class, 'to_department_id');
    }

    public function items()
    {
        return $this->hasMany(PharmDistributionItem::class, 'distribution_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
