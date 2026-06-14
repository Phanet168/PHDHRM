<?php

namespace Modules\Planning\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Planning\Support\GeneratesUuid;

class PlanAttachment extends Model
{
    use GeneratesUuid;
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'plan_item_id',
        'uploaded_by',
        'disk',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function item()
    {
        return $this->belongsTo(PlanItem::class, 'plan_item_id');
    }
}
