<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityGallery extends Model
{
    protected $fillable = ['facility_id', 'media_id', 'image', 'caption', 'sort_order'];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
