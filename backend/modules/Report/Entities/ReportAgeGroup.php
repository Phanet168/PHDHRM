<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;

class ReportAgeGroup extends Model
{
    protected $table = 'report_age_groups';
    protected $fillable = ['code', 'label', 'min_days', 'max_days', 'sort_order'];
}
