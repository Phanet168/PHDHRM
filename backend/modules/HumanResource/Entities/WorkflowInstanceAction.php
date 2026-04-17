<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class WorkflowInstanceAction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'workflow_instance_id',
        'step_order',
        'action_type',
        'action_status',
        'acted_by',
        'acted_at',
        'decision_ref_no',
        'decision_ref_date',
        'decision_note',
        'payload_json',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'step_order' => 'integer',
        'acted_at' => 'datetime',
        'decision_ref_date' => 'date',
        'payload_json' => 'array',
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

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }
}

