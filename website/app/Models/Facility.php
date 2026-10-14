<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facility extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name_km',
        'name_en',
        'slug',
        'facility_type',
        'district',
        'commune',
        'village',
        'address',
        'phone',
        'email',
        'opening_hours',
        'description',
        'history',
        'vision',
        'mission',
        'latitude',
        'longitude',
        'logo',
        'cover_image',
        'patients_count',
        'staff_count',
        'services_count',
        'status',
        'published_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'published_at' => 'datetime',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(FacilityService::class)->orderBy('sort_order');
    }

    public function leaders(): HasMany
    {
        return $this->hasMany(FacilityLeader::class)->orderBy('sort_order');
    }

    public function partners(): BelongsToMany
    {
        return $this->belongsToMany(Partner::class, 'facility_partners');
    }

    public function gallery(): HasMany
    {
        return $this->hasMany(FacilityGallery::class)->orderBy('sort_order');
    }

    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * Facilities that have a phone number a visitor can actually call: not
     * empty and containing at least one digit (portable LIKE checks, so it
     * works on any database and stays a DB-level filter for pagination).
     */
    public function scopeWithPublicPhone($query)
    {
        return $query->whereNotNull('phone')->where(function ($phone) {
            foreach (range(0, 9) as $digit) {
                $phone->orWhere('phone', 'like', "%{$digit}%");
            }
        });
    }

    /** The number shown/dialled for this facility, or null when it has no usable one. */
    public function publicPhone(): ?string
    {
        $phone = trim((string) $this->phone);

        return preg_match_all('/\d/', $phone) >= 3 ? $phone : null;
    }

    /** tel: link for the public phone (digits and a leading + only), or null. */
    public function phoneHref(): ?string
    {
        $phone = $this->publicPhone();

        return $phone ? 'tel:'.preg_replace('/[^0-9+]/', '', $phone) : null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'referral_hospital' => 'មន្ទីរពេទ្យបង្អែក',
            'health_center' => 'មណ្ឌលសុខភាព',
            'health_post' => 'ប៉ុស្តិ៍សុខភាព',
            'operational_district_office' => 'ការិយាល័យសុខាភិបាលស្រុកប្រតិបត្តិ',
        };
    }

    /**
     * Hex color used consistently for this facility type's badge, map
     * marker, and summary card accent across the public site and admin.
     */
    public static function typeColor(string $type): string
    {
        return match ($type) {
            'referral_hospital' => '#005cad',
            'health_center' => '#0ea962',
            'health_post' => '#f59e0b',
            'operational_district_office' => '#7c3aed',
        };
    }

    public static function typeIcon(string $type): string
    {
        return match ($type) {
            'referral_hospital' => '✚',
            'health_center' => '⌂',
            'health_post' => '⌖',
            'operational_district_office' => '🏛',
        };
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'សេចក្តីព្រាង',
            'published' => 'បានផ្សាយ',
            'archived' => 'បានទុកក្នុងឯកសារ',
        };
    }
}
