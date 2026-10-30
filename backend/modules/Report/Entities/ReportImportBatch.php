<?php

namespace Modules\Report\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** One imported HIS workbook. Deleting it removes all of its facts. */
class ReportImportBatch extends Model
{
    protected $table = 'report_import_batches';
    protected $fillable = ['template_id', 'facility_id', 'period_id', 'file_name', 'file_hash', 'facts_count', 'summary', 'created_by'];
    protected $casts = ['summary' => 'array'];

    public function template()
    {
        return $this->belongsTo(ReportImportTemplate::class, 'template_id');
    }

    public function facility()
    {
        return $this->belongsTo(ReportFacility::class, 'facility_id');
    }

    public function period()
    {
        return $this->belongsTo(ReportPeriod::class, 'period_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function facts()
    {
        return $this->hasMany(ReportFact::class, 'batch_id');
    }
}
