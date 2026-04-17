<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkflowDefinitionStep extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'workflow_definition_id',
        'step_order',
        'step_key',
        'step_name',
        'action_type',
        'org_role',
        'system_role_id',
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
}

