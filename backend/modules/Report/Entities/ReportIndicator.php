<?php

namespace Modules\Report\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Modules\Report\Services\Hr\HrStatistics;

class ReportIndicator extends Model
{
    public const DIRECTIONS = ['up' => 'ខ្ពស់ល្អ', 'down' => 'ទាបល្អ'];

    public const AGGREGATIONS = [
        'none' => 'មិនគណនា',
        'sum' => 'បូកខែ',
        'avg' => 'មធ្យមខែ',
        'last' => 'ខែចុងក្រោយ',
    ];

    protected $table = 'report_indicators';
    protected $fillable = [
        'code', 'source_id', 'name', 'indicator_type', 'direction', 'aggregation', 'department_id', 'unit_id', 'owner_user_id', 'sort_order', 'active',
        'his_element_id', 'his_sex', 'his_age_group_id', 'his_diagnosis_id', 'his_option_id',
        'hrm_measure', 'hrm_sex', 'hrm_profession_id', 'hrm_employment', 'hrm_workplace',
    ];

    protected $casts = ['active' => 'boolean'];

    /** HIS data element this indicator's value is read from (with the optional narrowing filters). */
    public function hisElement()
    {
        return $this->belongsTo(ReportDataElement::class, 'his_element_id');
    }

    public function hisAgeGroup()
    {
        return $this->belongsTo(ReportAgeGroup::class, 'his_age_group_id');
    }

    public function hisDiagnosis()
    {
        return $this->belongsTo(ReportDiagnosis::class, 'his_diagnosis_id');
    }

    public function hisOption()
    {
        return $this->belongsTo(ReportCategoryOption::class, 'his_option_id');
    }

    public function hrmProfession()
    {
        return $this->belongsTo(ReportHrProfession::class, 'hrm_profession_id');
    }

    /** Short description of the HRM link, e.g. "ចំនួនបុគ្គលិក (ចុងខែ) · វេជ្ជបណ្ឌិត · ស្រី · ក្របខ័ណ្ឌ". */
    public function hrmLabel(): ?string
    {
        if (!$this->hrm_measure) {
            return null;
        }

        return collect([
            HrStatistics::MEASURES[$this->hrm_measure] ?? $this->hrm_measure,
            $this->hrmProfession?->name,
            $this->hrm_sex ? (ReportFact::SEXES[$this->hrm_sex] ?? $this->hrm_sex) : null,
            $this->hrm_employment ? (HrStatistics::EMPLOYMENT_FILTERS[$this->hrm_employment] ?? $this->hrm_employment) : null,
            $this->hrm_workplace ? (HrStatistics::WORKPLACES[$this->hrm_workplace] ?? $this->hrm_workplace) : null,
        ])->filter()->implode(' · ');
    }

    /** Short description of the HIS link, e.g. "ករណីថ្មីពិគ្រោះជំងឺក្រៅ · ស្រី · ០-២៨ថ្ងៃ". */
    public function hisLabel(): ?string
    {
        if (!$this->his_element_id) {
            return null;
        }

        return collect([
            $this->hisElement?->name,
            $this->his_sex ? (ReportFact::SEXES[$this->his_sex] ?? $this->his_sex) : null,
            $this->hisAgeGroup?->label,
            $this->hisDiagnosis ? $this->hisDiagnosis->icd10 . ' ' . $this->hisDiagnosis->name : null,
            $this->hisOption?->label,
        ])->filter()->implode(' · ');
    }

    public function department()
    {
        return $this->belongsTo(ReportDepartment::class, 'department_id');
    }

    public function unit()
    {
        return $this->belongsTo(ReportUnit::class, 'unit_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function mapping()
    {
        return $this->hasOne(ReportImportMapping::class, 'indicator_id');
    }

    public function values()
    {
        return $this->hasMany(ReportValue::class, 'indicator_id');
    }

    /** Display form of a code: PHDReport's generated UUID ids are shortened to their first 8 characters. */
    public static function shortCode(?string $code): string
    {
        $code = (string) $code;

        return preg_match('/^[0-9a-f]{8}-[0-9a-f-]{27,}$/i', $code) ? substr($code, 0, 8) . '…' : $code;
    }

    /** Natural ordering by code (1, 2, 10 rather than 1, 10, 2). */
    public function scopeOrdered($query)
    {
        return $query->orderBy('report_indicators.sort_order')
            ->orderByRaw('LENGTH(report_indicators.code)')
            ->orderBy('report_indicators.code');
    }
}
