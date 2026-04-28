<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use App\Models\MobileDeviceRegistration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\UserOrgRole;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'email',
        'password',
        'profile_image',
        'cover_image',
        'signature',
        'user_type_id',
        'contact_no',
        'telegram_chat_id',
        'telegram_link_token',
        'telegram_linked_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'telegram_linked_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
            self::creating(function($model) {
                $model->uuid = (string) Str::uuid();
            });


        static::addGlobalScope('sortByLatest', function (Builder $builder) {
            $builder->orderByDesc('id');
        });
    }


    public function admin(){
        if ((int) $this->user_type_id === 1) {
            return true;
        }

        return method_exists($this, 'hasRole') && $this->hasRole('Super Admin');
    }
    
    //user all role
    public function userRole(){
        return $this->belongsToMany(Role::class,'model_has_roles','model_id','role_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('User')
            ->dontLogIfAttributesChangedOnly(['remember_token']);     
    }

    public function getActivityDescriptionForEvent(string $eventName): string
    {
        if ($eventName === 'created') {
            return 'User registered';
        }
        if ($eventName === 'updated') {
            return 'User profile updated';
        }
        if ($eventName === 'deleted') {
            return 'User deleted';
        }
        if ($eventName === 'Login') {
            return 'User logged in';
        }
        if ($eventName === 'Logout') {
            return 'User logged out';
        }

        return '';
    }

    public function employee(){
        return $this->hasOne(Employee::class);
    }

    public function orgRoles()
    {
        return $this->hasMany(UserOrgRole::class, 'user_id', 'id');
    }

    public function userAssignments()
    {
        return $this->hasMany(UserAssignment::class, 'user_id', 'id');
    }

    public function primaryActiveAssignment()
    {
        return $this->hasOne(UserAssignment::class, 'user_id', 'id')
            ->where('is_primary', true)
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', now()->toDateString());
            })
            ->latestOfMany();
    }

    public function latestActiveAssignment()
    {
        return $this->hasOne(UserAssignment::class, 'user_id', 'id')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('effective_from')
                    ->orWhereDate('effective_from', '<=', now()->toDateString());
            })
            ->where(function ($query) {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', now()->toDateString());
            })
            ->latestOfMany();
    }

    public function mobileDeviceRegistrations()
    {
        return $this->hasMany(MobileDeviceRegistration::class, 'user_id', 'id');
    }
}
