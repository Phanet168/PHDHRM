<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\ImportAttnData::class,
        Commands\OptimizeHrDatabase::class,
        Commands\DispatchScheduledNotices::class,
    ];
    
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Deliver approved/scheduled notices to all targeted recipients.
        $schedule->command('notice:dispatch-scheduled --limit=100')
            ->everyMinute()
            ->withoutOverlapping();

        // Weekly DB housekeeping to keep HR-related tables compact and responsive.
        $schedule->command('maintenance:optimize-hr-db --activity-days=180 --notification-days=180 --failed-jobs-days=30 --password-reset-hours=24')
            ->weeklyOn(0, '02:15')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
