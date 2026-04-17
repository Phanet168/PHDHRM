<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class OrgRoleModulePermission extends Model
{
    use HasFactory;

    public const MODULE_CORRESPONDENCE = 'correspondence';
    public const MODULE_LEAVE = 'leave';
    public const MODULE_NOTICE = 'notice';
    public const MODULE_PROMOTION = 'promotion';
    public const MODULE_PHARMACEUTICAL = 'pharmaceutical';

    protected $fillable = [
        'module_key',
        'action_key',
        'org_role',
        'system_role_id',
        'is_active',
        'note',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        self::creating(function ($model) {
            $authId = Auth::id();
            if ($authId) {
                $model->created_by = $authId;
                $model->updated_by = $authId;
            }
        });

        self::updating(function ($model) {
            $authId = Auth::id();
            if ($authId) {
                $model->updated_by = $authId;
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function systemRole()
    {
        return $this->belongsTo(SystemRole::class, 'system_role_id');
    }

    public static function moduleActionMap(): array
    {
        return [
            self::MODULE_CORRESPONDENCE => [
                'create_incoming',
                'create_outgoing',
                'delegate',
                'office_comment',
                'deputy_review',
                'director_decision',
                'distribute',
                'acknowledge',
                'feedback',
                'close',
                'print',
            ],
            self::MODULE_LEAVE => [
                'create',
                'review',
                'recommend',
                'approve',
                'reject',
            ],
            self::MODULE_NOTICE => [
                'create',
                'review',
                'approve',
                'publish',
            ],
            self::MODULE_PROMOTION => [
                'create',
                'review',
                'approve',
                'finalize',
            ],
            self::MODULE_PHARMACEUTICAL => [
                'view_inventory',
                'manage_inventory',
                'dispense',
                'distribute',
                'manage_products',
                'view_reports',
            ],
        ];
    }
}
