<?php

namespace Modules\HumanResource\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class UserOrgRole extends Model
{
    use HasFactory, SoftDeletes;

    public const ROLE_HEAD = 'head';
    public const ROLE_DEPUTY_HEAD = 'deputy_head';
    public const ROLE_MANAGER = 'manager';

    public const SCOPE_SELF = 'self';
    public const SCOPE_SELF_AND_CHILDREN = 'self_and_children';

    protected $fillable = [
        'uuid',
        'user_id',
        'department_id',
        'org_role',
        'scope_type',
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
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        self::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });

        static::addGlobalScope('sortByLatest', function (Builder $builder) {
            $builder->orderByDesc('id');
        });
    }

    public static function roleOptions(): array
    {
        return [
            self::ROLE_HEAD,
            self::ROLE_DEPUTY_HEAD,
            self::ROLE_MANAGER,
        ];
    }

    public static function scopeOptions(): array
    {
        return [
            self::SCOPE_SELF,
            self::SCOPE_SELF_AND_CHILDREN,
        ];
    }

    public function scopeEffective(Builder $query, ?Carbon $at = null): Builder
    {
        $at = $at ?: now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $at->toDateString());
            })
            ->where(function (Builder $q) use ($at) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $at->toDateString());
            });
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
