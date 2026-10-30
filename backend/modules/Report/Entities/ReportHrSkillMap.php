<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;

/** Free-text skill on a staff profile (e.g. "គិ.មធ្យម", "គិមធ្យម") → profession category. */
class ReportHrSkillMap extends Model
{
    protected $table = 'report_hr_skill_maps';
    protected $fillable = ['skill_text', 'profession_id', 'auto'];
    protected $casts = ['auto' => 'boolean'];

    public function profession()
    {
        return $this->belongsTo(ReportHrProfession::class, 'profession_id');
    }
}
