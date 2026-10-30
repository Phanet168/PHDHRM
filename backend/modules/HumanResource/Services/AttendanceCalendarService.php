<?php

namespace Modules\HumanResource\Services;

use Carbon\CarbonInterface;
use Modules\HumanResource\Entities\Holiday;
use Modules\HumanResource\Entities\WeekHoliday;

/**
 * The organisation-wide work calendar: weekly days off and public holidays.
 * Shared by the shift resolver (a duty roster only adds the regular hours on
 * a working day) and the daily status service, memoized per instance so an
 * N-day request doesn't re-query the same rows N times.
 */
class AttendanceCalendarService
{
    /** @var string[]|null */
    private ?array $weeklyDaysOff = null;

    /** @var array<string, string|null> */
    private array $holidayNames = [];

    public function isWorkingDay(CarbonInterface $date): bool
    {
        return ! $this->isWeeklyDayOff($date) && $this->publicHolidayName($date->toDateString()) === null;
    }

    public function isWeeklyDayOff(CarbonInterface $date): bool
    {
        if ($this->weeklyDaysOff === null) {
            $dayname = (string) WeekHoliday::query()->value('dayname');
            $this->weeklyDaysOff = array_filter(array_map(static fn (string $day): string => strtoupper(trim($day)), explode(',', $dayname)));
        }

        return in_array(strtoupper($date->format('l')), $this->weeklyDaysOff, true);
    }

    public function publicHolidayName(string $day): ?string
    {
        if (! array_key_exists($day, $this->holidayNames)) {
            $this->holidayNames[$day] = Holiday::query()
                ->whereDate('start_date', '<=', $day)
                ->whereDate('end_date', '>=', $day)
                ->value('holiday_name');
        }

        return $this->holidayNames[$day];
    }
}
