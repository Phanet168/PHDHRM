<?php

namespace Modules\Report\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Assigns an HRM user to a report unit; can_edit lets them enter that unit's values. */
class ReportUserUnit extends Model
{
    protected $table = 'report_user_units';
    protected $fillable = ['user_id', 'unit_id', 'can_edit'];
    protected $casts = ['can_edit' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function unit()
    {
        return $this->belongsTo(ReportUnit::class, 'unit_id');
    }
}
