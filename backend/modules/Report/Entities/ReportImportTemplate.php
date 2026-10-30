<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;

/** An HIS workbook layout (HC1 = OD health-centre report, HO2 = hospital report). */
class ReportImportTemplate extends Model
{
    protected $table = 'report_import_templates';
    protected $fillable = ['code', 'name', 'facility_type', 'description'];

    public function blocks()
    {
        return $this->hasMany(ReportImportBlock::class, 'template_id')->orderBy('sort_order')->orderBy('id');
    }

    public function batches()
    {
        return $this->hasMany(ReportImportBatch::class, 'template_id');
    }
}
