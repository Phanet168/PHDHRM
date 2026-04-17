<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MobileDeviceRegistration extends Model
{
    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'platform',
        'imei',
        'fingerprint',
        'status',
        'blocked_by',
        'blocked_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'register_ip',
        'register_ua',
        'last_login_at',
    ];

    protected $casts = [
        'blocked_at'    => 'datetime',
        'approved_at'   => 'datetime',
        'rejected_at'   => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isActive(): bool   { return $this->status === 'active'; }
    public function isBlocked(): bool  { return $this->status === 'blocked'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }

    public function canLogin(): bool   { return $this->status === 'active'; }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }
}
