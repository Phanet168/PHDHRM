<?php

namespace Modules\Report\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** An issue (បញ្ហាប្រឈម) raised against an indicator for a period, with its follow-up action. */
class ReportAction extends Model
{
    public const STATUSES = [
        'planned' => 'គ្រោងទុក',
        'ongoing' => 'កំពុងអនុវត្ត',
        'done' => 'បានដោះស្រាយ',
        'blocked' => 'ជាប់គាំង',
    ];

    protected $table = 'report_actions';
    protected $fillable = [
        'period_id', 'indicator_id', 'issue_text', 'action_text', 'action_owner', 'action_due',
        'action_status', 'resolution_text', 'resolution_date', 'created_by', 'updated_by',
    ];
    protected $casts = ['action_due' => 'date', 'resolution_date' => 'date'];

    public function period()
    {
        return $this->belongsTo(ReportPeriod::class, 'period_id');
    }

    public function indicator()
    {
        return $this->belongsTo(ReportIndicator::class, 'indicator_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOpen($query)
    {
        return $query->where('action_status', '!=', 'done');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->action_status] ?? (string) $this->action_status;
    }
}
