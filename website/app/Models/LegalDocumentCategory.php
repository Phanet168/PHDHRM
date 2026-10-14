<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalDocumentCategory extends Model
{
    protected $fillable = ['name', 'slug', 'sort_order'];

    public function documents(): HasMany
    {
        return $this->hasMany(LegalDocument::class, 'category_id');
    }
}
