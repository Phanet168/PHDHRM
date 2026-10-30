<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;

/** Where an indicator's HC / HOSP values live in the uploaded Excel workbooks. */
class ReportImportMapping extends Model
{
    protected $table = 'report_import_mappings';
    protected $fillable = ['indicator_id', 'hc_sheet', 'hc_cell', 'hc_formula', 'hosp_sheet', 'hosp_cell', 'hosp_formula', 'result_formula', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function indicator()
    {
        return $this->belongsTo(ReportIndicator::class, 'indicator_id');
    }
}
