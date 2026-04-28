<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ResponsibilityTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'module_key',
        'template_key',
        'name',
        'name_km',
        'position_id',
        'responsibility_id',
        'action_presets_json',
        'default_scope_type',
        'sort_order',
        'is_system',
        'is_active',
        'note',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'action_presets_json' => 'array',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::addGlobalScope('sortByLatest', function (Builder $builder): void {
            $builder->orderByDesc('id');
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function responsibility(): BelongsTo
    {
        return $this->belongsTo(SystemRole::class, 'responsibility_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(UserAssignment::class, 'responsibility_template_id');
    }

    public function supportsAction(string $actionKey): bool
    {
        $actionKey = trim(mb_strtolower($actionKey));
        if ($actionKey === '') {
            return false;
        }

        $actions = collect((array) ($this->action_presets_json ?? []))
            ->map(fn ($item) => trim(mb_strtolower((string) $item)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($actions)) {
            return false;
        }

        return in_array($actionKey, $actions, true);
    }

    public function displayName(): string
    {
        $name = trim((string) ($this->name_km ?: $this->name));
        if ($name === '') {
            $name = trim((string) $this->template_key);
        }

        return $name !== '' ? $name : ('Template #' . (int) $this->id);
    }
}

