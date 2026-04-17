<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EmployeeServiceHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'event_type',
        'event_date',
        'title',
        'details',
        'from_value',
        'to_value',
        'reference_type',
        'reference_id',
        'metadata',
    ];

    protected $casts = [
        'event_date' => 'date',
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
