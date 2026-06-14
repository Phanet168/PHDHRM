<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class Account extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = ['chapter_id', 'code', 'name', 'name_km', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'bool'];

    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }

    public function subAccounts()
    {
        return $this->hasMany(SubAccount::class);
    }
}
