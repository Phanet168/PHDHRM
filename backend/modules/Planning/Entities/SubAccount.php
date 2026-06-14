<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class SubAccount extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = ['account_id', 'code', 'name', 'name_km', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'bool'];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
