<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkflowInstance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'module_key',
        'request_type_key',
        'source_type',
        'source_id',
        'workflow_definition_id',
        'status',
        'current_step_order',
        'submitted_by',
        'submitted_at',
        'finalized_at',
        'context_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'current_step_order' => 'integer',
        'submitted_at' => 'datetime',
        'finalized_at' => 'datetime',
        'context_json' => 'array',
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

    public function actions(): HasMany
    {
        return $this->hasMany(WorkflowInstanceAction::class, 'workflow_instance_id')
            ->orderBy('id');
    }
}

