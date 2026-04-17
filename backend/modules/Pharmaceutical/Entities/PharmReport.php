<?php

namespace Modules\Pharmaceutical\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HumanResource\Entities\Department;

class PharmReport extends Model
{
    use SoftDeletes;

    public const TYPE_MONTHLY = 'monthly';
    public const TYPE_QUARTERLY = 'quarterly';
    public const TYPE_ANNUAL = 'annual';
    public const TYPE_ADHOC = 'adhoc';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_APPROVED = 'approved';

    protected $table = 'pharm_reports';

    protected $fillable = [
        'reference_no',
        'department_id',
        'parent_department_id',
        'report_type',
        'period_label',
        'period_start',
        'period_end',
        'status',
        'note',
        'reviewer_note',
        'submitted_by',
        'submitted_at',
        'reviewed_by',
        'reviewed_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public static function typeLabels(): array
    {
        return [
            self::TYPE_MONTHLY   => localize('monthly', 'Monthly'),
            self::TYPE_QUARTERLY => localize('quarterly', 'Quarterly'),
            self::TYPE_ANNUAL    => localize('annual', 'Annual'),
            self::TYPE_ADHOC     => localize('adhoc', 'Ad-hoc'),
        ];
    }

    public static function statusLabels(): array
    {
        return [
            self::STATUS_DRAFT     => localize('draft', 'Draft'),
            self::STATUS_SUBMITTED => localize('submitted', 'Submitted'),
            self::STATUS_REVIEWED  => localize('reviewed', 'Reviewed'),
            self::STATUS_APPROVED  => localize('approved', 'Approved'),
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function parentDepartment()
    {
        return $this->belongsTo(Department::class, 'parent_department_id');
    }

    public function items()
    {
        return $this->hasMany(PharmReportItem::class, 'report_id');
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /* ── Display helpers ── */

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->report_type] ?? $this->report_type;
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function getStatusBadgeAttribute(): string
    {
        $map = [
            self::STATUS_DRAFT     => 'bg-secondary',
            self::STATUS_SUBMITTED => 'bg-primary',
            self::STATUS_REVIEWED  => 'bg-info',
            self::STATUS_APPROVED  => 'bg-success',
        ];
        $cls = $map[$this->status] ?? 'bg-secondary';
        return '<span class="badge ' . $cls . '">' . e($this->status_label) . '</span>';
    }
}
