<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkflowDefinition extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'module_key',
        'request_type_key',
        'name',
        'description',
        'condition_json',
        'priority',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'condition_json' => 'array',
        'priority' => 'integer',
        'is_active' => 'boolean',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowDefinitionStep::class, 'workflow_definition_id')
            ->orderBy('step_order');
    }
}

