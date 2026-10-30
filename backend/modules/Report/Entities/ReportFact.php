<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;

/** One value: period × facility × data element × [diagnosis × age group × sex × option]. */
class ReportFact extends Model
{
    public const SEXES = ['M' => 'ប្រុស', 'F' => 'ស្រី'];

    protected $table = 'report_facts';
    protected $fillable = [
        'period_id', 'facility_id', 'data_element_id', 'diagnosis_id', 'age_group_id', 'sex', 'option_id',
        'dim_key', 'value', 'batch_id', 'source_cell',
    ];
    protected $casts = ['value' => 'float'];

    public static function dimKey(?int $diagnosisId, ?int $ageGroupId, ?string $sex, ?int $optionId): string
    {
        return sprintf('d%s|a%s|s%s|o%s', $diagnosisId ?? '', $ageGroupId ?? '', $sex ?? '', $optionId ?? '');
    }

    public function period()
    {
        return $this->belongsTo(ReportPeriod::class, 'period_id');
    }

    public function facility()
    {
        return $this->belongsTo(ReportFacility::class, 'facility_id');
    }

    public function element()
    {
        return $this->belongsTo(ReportDataElement::class, 'data_element_id');
    }

    public function diagnosis()
    {
        return $this->belongsTo(ReportDiagnosis::class, 'diagnosis_id');
    }

    public function ageGroup()
    {
        return $this->belongsTo(ReportAgeGroup::class, 'age_group_id');
    }

    public function option()
    {
        return $this->belongsTo(ReportCategoryOption::class, 'option_id');
    }
}
