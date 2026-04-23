<?php

namespace Modules\HumanResource\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class WorkflowDefinitionStep extends Model
{
    use HasFactory, SoftDeletes;

    public const ACTOR_TYPE_SPECIFIC_USER = 'specific_user';
    public const ACTOR_TYPE_POSITION = 'position';
    public const ACTOR_TYPE_RESPONSIBILITY = 'responsibility';
    public const ACTOR_TYPE_SPATIE_ROLE = 'spatie_role';

    protected $fillable = [
        'uuid',
        'workflow_definition_id',
        'step_order',
        'step_key',
        'step_name',
        'action_type',
        'org_role',
        'system_role_id',
        'actor_type',
        'actor_user_id',
        'actor_position_id',
        'actor_responsibility_id',
        'actor_role_id',
        'scope_type',
        'is_final_approval',
        'is_required',
        'can_return',
        'can_reject',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'actor_user_id' => 'integer',
        'actor_position_id' => 'integer',
        'actor_responsibility_id' => 'integer',
        'actor_role_id' => 'integer',
        'is_final_approval' => 'boolean',
        'is_required' => 'boolean',
        'can_return' => 'boolean',
        'can_reject' => 'boolean',
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

    public function definition(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_definition_id');
    }

    public function systemRole(): BelongsTo
    {
        return $this->belongsTo(\Modules\HumanResource\Entities\SystemRole::class, 'system_role_id');
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'actor_position_id');
    }

    public function actorResponsibility(): BelongsTo
    {
        return $this->belongsTo(SystemRole::class, 'actor_responsibility_id');
    }

    public function actorRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'actor_role_id');
    }

    public static function actorTypeOptions(): array
    {
        return [
            self::ACTOR_TYPE_SPECIFIC_USER,
            self::ACTOR_TYPE_POSITION,
            self::ACTOR_TYPE_RESPONSIBILITY,
            self::ACTOR_TYPE_SPATIE_ROLE,
        ];
    }

    public function getEffectiveActorType(): string
    {
        $actorType = trim((string) ($this->actor_type ?? ''));
        if ($actorType === self::ACTOR_TYPE_SPECIFIC_USER && !empty($this->actor_user_id)) {
            return self::ACTOR_TYPE_SPECIFIC_USER;
        }
        if ($actorType === self::ACTOR_TYPE_POSITION && !empty($this->actor_position_id)) {
            return self::ACTOR_TYPE_POSITION;
        }
        if (
            $actorType === self::ACTOR_TYPE_RESPONSIBILITY
            && (!empty($this->actor_responsibility_id) || !empty($this->system_role_id) || !empty($this->org_role))
        ) {
            return self::ACTOR_TYPE_RESPONSIBILITY;
        }
        if ($actorType === self::ACTOR_TYPE_SPATIE_ROLE && !empty($this->actor_role_id)) {
            return self::ACTOR_TYPE_SPATIE_ROLE;
        }

        // Resolver fallback priority:
        // specific_user -> position -> responsibility -> spatie_role.
        if (!empty($this->actor_user_id)) {
            return self::ACTOR_TYPE_SPECIFIC_USER;
        }
        if (!empty($this->actor_position_id)) {
            return self::ACTOR_TYPE_POSITION;
        }
        if (!empty($this->actor_responsibility_id) || !empty($this->system_role_id) || !empty($this->org_role)) {
            return self::ACTOR_TYPE_RESPONSIBILITY;
        }
        if (!empty($this->actor_role_id)) {
            return self::ACTOR_TYPE_SPATIE_ROLE;
        }

        if (in_array($actorType, self::actorTypeOptions(), true)) {
            return $actorType;
        }

        return self::ACTOR_TYPE_RESPONSIBILITY;
    }

    public function getEffectiveRoleCode(): string
    {
        if ($this->actor_responsibility_id) {
            if ($this->relationLoaded('actorResponsibility') && $this->actorResponsibility) {
                return (string) $this->actorResponsibility->code;
            }

            $code = $this->actorResponsibility()
                ->withoutGlobalScopes()
                ->value('code');
            if (!empty($code)) {
                return (string) $code;
            }
        }

        if ($this->system_role_id) {
            if ($this->relationLoaded('systemRole') && $this->systemRole) {
                return (string) $this->systemRole->code;
            }

            $code = $this->systemRole()
                ->withoutGlobalScopes()
                ->value('code');
            if (!empty($code)) {
                return (string) $code;
            }
        }

        return (string) ($this->org_role ?? '');
    }
}

