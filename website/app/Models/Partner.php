<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Partner extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'logo',
        'website',
        'description',
        'sort_order',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facility_partners');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
