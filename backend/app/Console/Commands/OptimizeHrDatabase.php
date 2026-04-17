<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OptimizeHrDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Keep this command conservative: dry-run by default is recommended.
     */
    protected $signature = 'maintenance:optimize-hr-db
                            {--dry-run : Show affected rows only, do not delete}
                            {--activity-days=180 : Keep activity logs for N days}
                            {--notification-days=180 : Keep read notifications for N days}
                            {--failed-jobs-days=30 : Keep failed jobs for N days}
                            {--password-reset-hours=24 : Keep password reset tokens for N hours}
                            {--attendance-orphan-only : Remove attendance rows with missing employee only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize HR-related database size safely by pruning stale and unused records.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $this->info($dryRun
            ? 'Running in DRY-RUN mode. No rows will be deleted.'
            : 'Running in LIVE mode. Rows will be deleted.');

        $summary = [
            'activity_log' => 0,
            'notifications' => 0,
            'failed_jobs' => 0,
            'password_resets' => 0,
            'attendance_orphans' => 0,
        ];

        DB::beginTransaction();
        try {
            $summary['activity_log'] = $this->pruneActivityLog($dryRun);
            $summary['notifications'] = $this->pruneNotifications($dryRun);
            $summary['failed_jobs'] = $this->pruneFailedJobs($dryRun);
            $summary['password_resets'] = $this->prunePasswordResets($dryRun);
            $summary['attendance_orphans'] = $this->pruneAttendanceOrphans($dryRun);

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Optimization failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Optimization summary');
        foreach ($summary as $key => $count) {
            $this->line(str_pad($key, 20) . ': ' . $count);
        }

        if ($dryRun) {
            $this->comment('Tip: rerun without --dry-run to apply cleanup.');
        }

        return self::SUCCESS;
    }

    protected function pruneActivityLog(bool $dryRun): int
    {
        if (!Schema::hasTable('activity_log')) {
            return 0;
        }

        $days = max(1, (int) $this->option('activity-days'));
        $cutoff = Carbon::now()->subDays($days);

        $query = DB::table('activity_log')->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if (!$dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }

    protected function pruneNotifications(bool $dryRun): int
    {
        if (!Schema::hasTable('notifications')) {
            return 0;
        }

        $days = max(1, (int) $this->option('notification-days'));
        $cutoff = Carbon::now()->subDays($days);

        $query = DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('created_at', '<', $cutoff);

        $count = (clone $query)->count();

        if (!$dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }

    protected function pruneFailedJobs(bool $dryRun): int
    {
        if (!Schema::hasTable('failed_jobs')) {
            return 0;
        }

        $days = max(1, (int) $this->option('failed-jobs-days'));
        $cutoff = Carbon::now()->subDays($days);

        $query = DB::table('failed_jobs')->where('failed_at', '<', $cutoff);
        $count = (clone $query)->count();

        if (!$dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }

    protected function prunePasswordResets(bool $dryRun): int
    {
        if (!Schema::hasTable('password_resets')) {
            return 0;
        }

        $hours = max(1, (int) $this->option('password-reset-hours'));
        $cutoff = Carbon::now()->subHours($hours);

        $query = DB::table('password_resets')->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if (!$dryRun && $count > 0) {
            $query->delete();
        }

        return $count;
    }

    protected function pruneAttendanceOrphans(bool $dryRun): int
    {
        if (!Schema::hasTable('attendances') || !Schema::hasTable('employees')) {
            return 0;
        }

        // Only remove rows that are no longer linked to an employee.
        $query = DB::table('attendances')
            ->leftJoin('employees', 'employees.id', '=', 'attendances.employee_id')
            ->whereNull('employees.id')
            ->select('attendances.id');

        $ids = $query->pluck('id')->all();
        $count = count($ids);

        if (!$dryRun && $count > 0) {
            DB::table('attendances')->whereIn('id', $ids)->delete();
        }

        return $count;
    }
}
