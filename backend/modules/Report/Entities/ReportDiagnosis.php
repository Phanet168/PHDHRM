<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;

class ReportDiagnosis extends Model
{
    protected $table = 'report_diagnoses';
    protected $fillable = ['icd10', 'name', 'group_name'];
}
