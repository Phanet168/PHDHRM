<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EmployeeStatusTransition extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'transition_type',
        'transition_source',
        'from_work_status_name',
        'to_work_status_name',
        'from_service_state',
        'to_service_state',
        'from_is_active',
        'to_is_active',
        'from_is_left',
        'to_is_left',
        'effective_date',
        'document_date',
        'document_reference',
        'note',
        'metadata',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'document_date' => 'date',
        'from_is_active' => 'boolean',
        'to_is_active' => 'boolean',
        'from_is_left' => 'boolean',
        'to_is_left' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if (Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
