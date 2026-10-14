<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * About-page organization entries. Hospitals and operational districts link to
 * their existing Facility record (facility_id) instead of re-typing it.
 */
class AboutOrgUnit extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'office' => 'ការិយាល័យចំណុះ',
        'hospital' => 'មន្ទីរពេទ្យ',
        'operational_district' => 'ស្រុកប្រតិបត្តិ',
        'unit' => 'អង្គភាព',
        'other' => 'អង្គភាពផ្សេងៗ',
    ];

    public const TYPE_ICONS = [
        'office' => 'briefcase',
        'hospital' => 'hospital',
        'operational_district' => 'pin',
        'unit' => 'users',
        'other' => 'categories',
    ];

    /** Facility types the "import from facilities" action brings in, mapped to unit types. */
    public const FACILITY_TYPE_MAP = [
        'referral_hospital' => 'hospital',
        'operational_district_office' => 'operational_district',
    ];

    protected $fillable = ['parent_id', 'facility_id', 'name', 'type', 'description', 'is_active', 'sort_order', 'is_demo'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function typeLabel(string $type): string
    {
        return self::TYPES[$type] ?? $type;
    }

    /** The linked facility's public profile, only while that facility is published. */
    public function publicUrl(): ?string
    {
        return $this->facility && $this->facility->status === 'published' ? route('facilities.show', $this->facility) : null;
    }
}
