<?php

namespace Modules\HumanResource\Entities;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use Illuminate\Database\Eloquent\SoftDeletes;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use Modules\Branch\Entities\Branch;

class LeaveType extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = [
        'id',
        'uuid',
        'leave_type',
        'leave_type_km',
        'leave_days',
        'leave_code',
        'policy_key',
        'entitlement_scope',
        'entitlement_unit',
        'entitlement_value',
        'max_per_request',
        'is_paid',
        'requires_attachment',
        'requires_medical_certificate',
        'notes',
    ];

    protected $casts = [
        'leave_days' => 'integer',
        'entitlement_value' => 'decimal:2',
        'max_per_request' => 'decimal:2',
        'is_paid' => 'boolean',
        'requires_attachment' => 'boolean',
        'requires_medical_certificate' => 'boolean',
    ];

    public function getDisplayNameAttribute(): string
    {
        $khmer = trim((string) ($this->leave_type_km ?? ''));
        if ($khmer !== '') {
            return $khmer;
        }

        return (string) ($this->leave_type ?? '');
    }

    public static function policyKeyOptions(): array
    {
        return [
            'annual',
            'short',
            'sick',
            'maternity',
            'unpaid',
            'other',
        ];
    }

    public static function entitlementScopeOptions(): array
    {
        return [
            'per_year',
            'per_request',
            'per_service_lifetime',
            'manual',
        ];
    }

    public static function entitlementUnitOptions(): array
    {
        return [
            'day',
            'month',
        ];
    }

    protected static function boot()
    {
        parent::boot();
        if(Auth::check()){

            self::creating(function($model) {
                $model->uuid = (string) Str::uuid();
                $model->created_by = Auth::id();
            });

            self::updating(function($model) {
                $model->updated_by = Auth::id();
            });


            self::deleted(function($model){
                $model->updated_by = Auth::id();
                $model->save();
            });


        }

	    static::addGlobalScope('sortByLatest', function (Builder $builder) {
            $builder->orderByDesc('id');
        });
    }

}
