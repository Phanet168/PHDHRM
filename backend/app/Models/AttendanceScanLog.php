<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;

class AttendanceScanLog extends Model
{
    protected $fillable = [
        'employee_id',
        'user_id',
        'workplace_id',
        'status',
        'error_code',
        'message',
        'machine_state',
        'range_meters',
        'acceptable_range_meters',
        'geofence_source',
        'latitude',
        'longitude',
        'request_ip',
        'user_agent',
        'qr_token_hash',
        'meta_payload',
        'scanned_at',
    ];

    protected $casts = [
        'meta_payload' => 'array',
        'scanned_at'   => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function workplace()
    {
        return $this->belongsTo(Department::class, 'workplace_id');
    }
}
