<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacilityService extends Model
{
    protected $fillable = ['facility_id', 'icon', 'name', 'sort_order'];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }
}
