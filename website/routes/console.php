<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

// Keep only the daily identifiers needed for current-month unique counts.
Schedule::call(fn () => DB::table('visitor_days')
    ->where('date', '<', now('Asia/Phnom_Penh')->subDays(35)->toDateString())->delete()
)->daily()->name('prune-visitor-days')->withoutOverlapping();

// DB-IP publishes a new country database at the start of each month. A failed run keeps the current file.
Schedule::command('geoip:update')
    ->monthlyOn(2, '03:00')->timezone('Asia/Phnom_Penh')
    ->withoutOverlapping()->name('update-geoip-database')
    ->onFailure(fn () => Log::warning('geoip:update failed; the previous country database is still in use.'));

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
