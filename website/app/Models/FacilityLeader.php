<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Facility leaders; rows with facility_id NULL are the department leadership shown on About. */
class FacilityLeader extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'facility_id', 'name', 'name_en', 'title', 'title_en', 'photo', 'portrait_media_id',
        'bio', 'sort_order', 'is_active', 'is_featured', 'is_demo',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_demo' => 'boolean',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function portrait(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'portrait_media_id');
    }

    public function scopeDepartment($query)
    {
        return $query->whereNull('facility_id');
    }

    /** Media Library portrait first, then the legacy photo path; null means show the placeholder. */
    public function portraitUrl(): ?string
    {
        if ($this->portrait?->isImage()) {
            return $this->portrait->url;
        }

        return Media::resolveLegacyPath($this->photo);
    }
}
