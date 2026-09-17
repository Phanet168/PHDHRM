<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MissionReport extends Model
{
    protected $fillable = ['mission_id', 'employee_id', 'summary', 'title', 'activities', 'results', 'issues', 'recommendations'];

    protected $casts = ['submitted_at' => 'datetime', 'revision' => 'integer'];

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->whereNotNull('submitted_at')->whereRaw('LENGTH(TRIM(summary)) > 0');
    }

    public function mission()
    {
        return $this->belongsTo(Mission::class);
    }

    public function submitter()
    {
        return $this->belongsTo(\App\Models\User::class, 'submitted_by');
    }

    public function documents()
    {
        return $this->hasMany(MissionDocument::class, 'mission_report_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
