<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;

/** ធាតុទិន្នន័យ — something counted in the HIS forms (OPD new cases, discharges, ANC visit 1 …). */
class ReportDataElement extends Model
{
    protected $table = 'report_data_elements';
    protected $fillable = ['uid', 'code', 'name', 'section', 'value_type', 'aggregation', 'active'];
    protected $casts = ['active' => 'boolean'];

    public function facts()
    {
        return $this->hasMany(ReportFact::class, 'data_element_id');
    }
}
