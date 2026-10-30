<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;

/** One table of a template: where it sits in the sheet and how its rows are read. */
class ReportImportBlock extends Model
{
    protected $table = 'report_import_blocks';
    protected $fillable = [
        'template_id', 'code', 'title', 'sheet', 'header_rows', 'row_start', 'row_end',
        'label_cols', 'code_col', 'kind', 'measure', 'first_col', 'sort_order', 'active',
    ];
    protected $casts = ['header_rows' => 'array', 'label_cols' => 'array', 'active' => 'boolean'];

    public function template()
    {
        return $this->belongsTo(ReportImportTemplate::class, 'template_id');
    }
}
