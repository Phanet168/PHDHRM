<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\HumanResource\Enums\Mission\DestinationScope;

class MissionDestination extends Model
{
    protected $fillable = [
        'mission_id', 'scope', 'destination_type', 'department_id', 'province_code',
        'district_code', 'commune_code', 'village_code', 'venue_name', 'address',
        'location_snapshot', 'sort_order',
    ];

    protected $casts = [
        'scope' => DestinationScope::class, 'location_snapshot' => 'array', 'sort_order' => 'integer',
        'province_code' => 'string', 'district_code' => 'string', 'commune_code' => 'string', 'village_code' => 'string',
    ];

    public function mission(): BelongsTo
    {
        return $this->belongsTo(Mission::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
