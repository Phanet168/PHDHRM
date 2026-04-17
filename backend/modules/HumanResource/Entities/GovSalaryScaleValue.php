<?php

namespace Modules\HumanResource\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class GovSalaryScaleValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'gov_salary_scale_id',
        'professional_skill_id',
        'value',
    ];

    protected $casts = [
        'value' => 'float',
    ];

    public function salaryScale()
    {
        return $this->belongsTo(GovSalaryScale::class, 'gov_salary_scale_id');
    }

    public function skill()
    {
        return $this->belongsTo(ProfessionalSkill::class, 'professional_skill_id');
    }
}
