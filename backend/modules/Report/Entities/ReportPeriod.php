<?php

namespace Modules\Report\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\Report\Services\PeriodCode;

class ReportPeriod extends Model
{
    protected $table = 'report_periods';
    protected $fillable = ['code', 'year', 'tag', 'name', 'locked'];
    protected $casts = ['locked' => 'boolean', 'year' => 'integer'];

    public static function forCode(string $code): self
    {
        $p = PeriodCode::parse($code);

        return static::firstOrCreate(['code' => $p['code']], ['year' => $p['year'], 'tag' => $p['tag']]);
    }

    public function values()
    {
        return $this->hasMany(ReportValue::class, 'period_id');
    }

    public function getLabelAttribute(): string
    {
        return $this->name ?: PeriodCode::label($this->code);
    }
}
