<?php

namespace Modules\HumanResource\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Phase 3D.1: the ONE canonical Delegation record -- see the creating
 * migration's docblock for the schema rationale. This model is deliberately
 * "dumb": validation, scope-bounding, and live authority re-verification all
 * live in Modules\HumanResource\Services\DelegationService, not here, so
 * there is exactly one place authority decisions are made.
 */
class Delegation extends Model
{
    use HasFactory, SoftDeletes;

    public const AUTHORITY_TYPE_WORKFLOW_APPROVAL = 'workflow_approval';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'uuid',
        'delegator_user_id',
        'delegatee_user_id',
        'authority_type',
        'authority_module_key',
        'scope_type',
        'scope_department_ids',
        'starts_at',
        'ends_at',
        'status',
        'reason',
        'created_by',
        'updated_by',
        'revoked_by',
        'revoked_at',
    ];

    protected $casts = [
        'scope_department_ids' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'revoked_at' => 'datetime',
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

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_user_id');
    }

    public function delegatee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegatee_user_id');
    }

    public function revokedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED;
    }

    /**
     * "Active" here means: not revoked AND the current moment is within
     * [starts_at, ends_at]. Does NOT verify the delegator still qualifies as
     * a workflow actor -- that is a live, per-check re-verification done by
     * DelegationService against WorkflowActorResolverService, never cached
     * on this row.
     */
    public function isEffectiveAt(?Carbon $at = null): bool
    {
        if ($this->isRevoked()) {
            return false;
        }

        $at = $at ?: now();

        return $this->starts_at !== null && $this->ends_at !== null
            && $at->greaterThanOrEqualTo($this->starts_at)
            && $at->lessThanOrEqualTo($this->ends_at);
    }

    /**
     * Computed display state -- upcoming/active/expired are never stored,
     * only 'revoked' is a real column value.
     */
    public function computedState(?Carbon $at = null): string
    {
        if ($this->isRevoked()) {
            return 'revoked';
        }

        $at = $at ?: now();

        if ($this->starts_at !== null && $at->lessThan($this->starts_at)) {
            return 'upcoming';
        }
        if ($this->ends_at !== null && $at->greaterThan($this->ends_at)) {
            return 'expired';
        }

        return 'active';
    }

    public function scopeNotRevoked(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_REVOKED);
    }

    public function scopeEffectiveNow(Builder $query, ?Carbon $at = null): Builder
    {
        $at = $at ?: now();

        return $query->notRevoked()
            ->where('starts_at', '<=', $at)
            ->where('ends_at', '>=', $at);
    }

    /** @return int[] concrete department ids this delegation's scope is rooted at */
    public function scopeDepartmentIds(): array
    {
        return array_values(array_filter(array_map('intval', (array) ($this->scope_department_ids ?? []))));
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
