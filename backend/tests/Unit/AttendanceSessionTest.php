<?php

namespace Tests\Unit;

use Carbon\Carbon;
use Illuminate\Support\Fluent;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Services\AttendanceSessionService;
use PHPUnit\Framework\TestCase;

class AttendanceSessionTest extends TestCase
{
    private function evaluate(array $punches, array $shift = []): array
    {
        $schedule = new Shift($shift + ['start_time' => '08:00', 'morning_end_time' => '12:00',
            'afternoon_start_time' => '14:00', 'end_time' => '17:00', 'grace_late_minutes' => 10, 'grace_early_leave_minutes' => 5]);

        return (new AttendanceSessionService)->evaluate(collect($punches)->map(fn ($p) => new Fluent([
            'time' => strlen($p[0]) === 5 ? '2026-09-07 '.$p[0].':00' : $p[0], 'machine_state' => $p[1],
        ])), $schedule, Carbon::parse('2026-09-07'));
    }

    public function test_split_day_excludes_lunch_and_records_early_arrival(): void
    {
        $result = $this->evaluate([['07:40', 1], ['12:00', 2], ['13:50', 1], ['17:10', 2]]);
        $this->assertSame('Present', $result['attendance_status']);
        $this->assertSame(420, $result['worked_minutes']);
        $this->assertSame(30, $result['early_arrival_minutes']);
    }

    public function test_afternoon_lateness_and_early_departure_both_survive_daily_summary(): void
    {
        $result = $this->evaluate([['08:00', 1], ['12:00', 2], ['14:20', 1], ['16:40', 2]]);
        $this->assertSame('Late', $result['attendance_status']);
        $this->assertSame(20, $result['late_minutes']);
        $this->assertSame(20, $result['early_leave_minutes']);
        $this->assertSame(380, $result['worked_minutes']);
    }

    public function test_missing_midday_scans_do_not_count_as_a_complete_day(): void
    {
        $result = $this->evaluate([['08:00', 1], ['17:00', 2]]);
        $this->assertSame('Incomplete', $result['attendance_status']);
        $this->assertSame(0, $result['worked_minutes']);
    }

    public function test_even_number_of_duplicate_ins_is_incomplete(): void
    {
        $result = $this->evaluate([['08:00', 1], ['08:20', 1], ['14:00', 1], ['17:00', 2]]);
        $this->assertSame('Incomplete', $result['attendance_status']);
    }

    public function test_grace_boundary_is_inclusive_and_lunch_is_unpaid(): void
    {
        $result = $this->evaluate([['08:10', 1], ['11:55', 2], ['14:10', 1], ['16:55', 2]]);
        $this->assertSame('Present', $result['attendance_status']);
        $this->assertSame(390, $result['worked_minutes']);
        $this->assertSame(0, $result['late_minutes']);
    }

    public function test_overnight_duty_pairs_next_day_checkout_and_excludes_breaks(): void
    {
        $result = $this->evaluate([
            ['2026-09-07 19:50:00', 1], ['2026-09-08 00:00:00', 2],
            ['2026-09-08 01:00:00', 1], ['2026-09-08 08:05:00', 2],
        ], ['start_time' => '20:00', 'end_time' => '08:00', 'morning_end_time' => null,
            'afternoon_start_time' => null, 'is_duty' => true, 'is_cross_day' => true]);
        $this->assertSame('Present', $result['attendance_status']);
        $this->assertSame(660, $result['worked_minutes']);
        $this->assertSame('2026-09-08 08:05:00', $result['out_time']);
    }
}
