<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;

/** ជំនាញ — profession category the HR indicators count staff by. */
class ReportHrProfession extends Model
{
    protected $table = 'report_hr_professions';
    protected $fillable = ['code', 'name', 'sort_order'];
}
