<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SystemRole extends Model
{
    public const CODE_HEAD = 'head';
    public const CODE_DEPUTY_HEAD = 'deputy_head';
    public const CODE_MANAGER = 'manager';
    public const CODE_REVIEWER = 'reviewer';
    public const CODE_STAFF = 'staff';
    public const CODE_VIEWER = 'viewer';

    protected $fillable = [
        'uuid',
        'code',
        'name',
        'name_km',
        'level',
        'can_approve',
        'is_system',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'level'       => 'integer',
        'can_approve' => 'boolean',
        'is_system'   => 'boolean',
        'is_active'   => 'boolean',
        'sort_order'  => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /* ── Relationships ─────────────────────── */

    public function userOrgRoles(): HasMany
    {
        return $this->hasMany(UserOrgRole::class, 'system_role_id');
    }

    public function modulePermissions(): HasMany
    {
        return $this->hasMany(OrgRoleModulePermission::class, 'system_role_id');
    }

    public function workflowSteps(): HasMany
    {
        return $this->hasMany(WorkflowDefinitionStep::class, 'system_role_id');
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(UserAssignment::class, 'responsibility_id');
    }

    public function responsibilityTemplates(): HasMany
    {
        return $this->hasMany(ResponsibilityTemplate::class, 'responsibility_id');
    }

    /* ── Scopes ────────────────────────────── */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeApprovers(Builder $query): Builder
    {
        return $query->where('can_approve', true)->where('is_active', true);
    }

    /* ── Helpers ───────────────────────────── */

    public function getDisplayNameAttribute(): string
    {
        return $this->name_km ?: $this->name;
    }

    public static function codeOptions(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('code')
            ->all();
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    public static function dropdownOptions(): array
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'code', 'name', 'name_km'])
            ->mapWithKeys(fn (self $r) => [$r->id => $r->display_name . ' (' . $r->code . ')'])
            ->all();
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
