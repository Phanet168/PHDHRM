<?php

namespace Modules\Planning\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class PlanComment extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'plan_item_id',
        'org_unit_id',
        'user_id',
        'comment_type',
        'comment',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function item()
    {
        return $this->belongsTo(PlanItem::class, 'plan_item_id');
    }

    public function orgUnit()
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
