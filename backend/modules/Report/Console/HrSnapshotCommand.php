<?php

namespace Modules\Report\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use Modules\Report\Entities\ReportPeriod;
use Modules\Report\Services\Hr\HrStatistics;
use Modules\Report\Services\PeriodCode;

/** Take the monthly HR statistics snapshot (scheduled on the 1st for the month that just ended). */
class HrSnapshotCommand extends Command
{
    protected $signature = 'report:hr-snapshot {period? : month code, e.g. 2026-M09 (default: last month)} {--force : replace an existing snapshot}';

    protected $description = 'Store the month-end staff statistics from the HRM records for the HR indicators';

    public function handle(HrStatistics $hr): int
    {
        $last = now()->subMonthNoOverflow();
        $code = $this->argument('period') ?: PeriodCode::make($last->year, 'M' . $last->month);
        try {
            [$code] = HrStatistics::month($code);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $period = ReportPeriod::where('code', $code)->first();
        if ($period && !$this->option('force') && \DB::table('report_hr_snapshots')->where('period_id', $period->id)->exists()) {
            $this->info("{$code}: already taken — use --force to replace it");

            return self::SUCCESS;
        }
        try {
            $n = $hr->save($code);
        } catch (InvalidArgumentException $e) {
            $this->warn("{$code}: " . $e->getMessage());

            return self::SUCCESS;
        }
        $this->info(sprintf('%s: %d staff, %d events', $code, $n['staff'], $n['events']));

        return self::SUCCESS;
    }
}
