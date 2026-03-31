<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class EmployeeLegalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'document_type',
        'document_number',
        'document_date',
        'document_subject',
        'effective_date',
        'is_current',
        'note',
    ];

    protected $casts = [
        'document_date' => 'date',
        'effective_date' => 'date',
        'is_current' => 'boolean',
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
