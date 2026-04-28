<?php

namespace Modules\HumanResource\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class UserAssignment extends Model
{
    use HasFactory, SoftDeletes;

    public const SCOPE_SELF_ONLY = 'self_only';
    public const SCOPE_SELF_UNIT_ONLY = 'self_unit_only';
    public const SCOPE_SELF_AND_CHILDREN = 'self_and_children';
    public const SCOPE_ALL = 'all';

    protected $fillable = [
        'uuid',
        'user_id',
        'department_id',
        'position_id',
        'responsibility_template_id',
        'responsibility_id',
        'scope_type',
        'is_primary',
        'effective_from',
        'effective_to',
        'is_active',
        'note',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_primary' => 'boolean',
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

        static::addGlobalScope('sortByLatest', function (Builder $builder): void {
            $builder->orderByDesc('id');
        });
    }

    public static function scopeOptions(): array
    {
        return [
            self::SCOPE_SELF_ONLY,
            self::SCOPE_SELF_UNIT_ONLY,
            self::SCOPE_SELF_AND_CHILDREN,
            self::SCOPE_ALL,
        ];
    }

    public function scopeEffective(Builder $query, ?Carbon $at = null): Builder
    {
        $at = $at ?: now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', $at->toDateString());
            })
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $at->toDateString());
            });
    }

    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('is_primary', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function responsibility(): BelongsTo
    {
        return $this->belongsTo(SystemRole::class, 'responsibility_id');
    }

    public function responsibilityTemplate(): BelongsTo
    {
        return $this->belongsTo(ResponsibilityTemplate::class, 'responsibility_template_id');
    }

    public function legacyOrgRole(): HasOne
    {
        return $this->hasOne(UserOrgRole::class, 'user_assignment_id', 'id');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
