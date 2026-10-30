<?php

namespace Modules\Report\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ReportValue extends Model
{
    protected $table = 'report_values';
    protected $fillable = ['period_id', 'indicator_id', 'unit_id', 'value', 'target', 'value_hc', 'value_hosp', 'source', 'updated_by'];
    protected $casts = ['value' => 'float', 'target' => 'float', 'value_hc' => 'float', 'value_hosp' => 'float'];

    public function period()
    {
        return $this->belongsTo(ReportPeriod::class, 'period_id');
    }

    public function indicator()
    {
        return $this->belongsTo(ReportIndicator::class, 'indicator_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
