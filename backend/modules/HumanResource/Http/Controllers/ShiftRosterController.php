<?php

namespace Modules\HumanResource\Http\Controllers;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\HumanResource\Entities\AttendanceDailySnapshot;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftRoster;
use Modules\HumanResource\Services\ShiftResolverService;
use Modules\HumanResource\Support\AttendanceUnitScope;

class ShiftRosterController extends Controller
{
    public function __construct(private readonly AttendanceUnitScope $scope, private readonly ShiftResolverService $resolver)
    {
    }

    public function index(Request $request): mixed
    {
        $this->scope->authorize('read_shift_roster');
        $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'employee_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $selectedDepartmentId = $this->scope->selected($request);
        $selectedYear = (int) $request->input('year', now()->year);
        $selectedMonth = (int) $request->input('month', now()->month);
        $selectedEmployeeId = $request->input('employee_id');
        $employeeQuery = $this->scope->employees($selectedDepartmentId);
        if ($selectedEmployeeId) {
            (clone $employeeQuery)->findOrFail($selectedEmployeeId);
        }
        $employees = $employeeQuery->where('is_active', 1)->orderBy('first_name')->get();
        $startDate = Carbon::create($selectedYear, $selectedMonth, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();
        $monthDays = range(1, $startDate->daysInMonth);
        $daysInMonth = $startDate->daysInMonth;
        $query = ShiftRoster::query()->with(['employee', 'shift'])
            ->whereIn('employee_id', $this->scope->employees($selectedDepartmentId)->select('employees.id'));
        if ($selectedEmployeeId) {
            $query->where('employee_id', $selectedEmployeeId);
        }
        if ($request->filled('date')) {
            $query->whereDate('roster_date', $request->input('date'));
        } else {
            $query->whereBetween('roster_date', [$startDate->toDateString(), $endDate->toDateString()]);
        }
        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'data' => $query->orderBy('roster_date')->get()]);
        }
        $departments = $this->scope->departments()->get();
        $shifts = Shift::where('department_id', $selectedDepartmentId)->where('is_active', 1)->orderBy('name')->get();
        $rosterMap = $query->get()->groupBy('employee_id')->map(fn ($rows) => $rows->keyBy(fn ($r) => $r->roster_date->day));
        $displayEmployees = $selectedEmployeeId ? $employees->where('id', (int) $selectedEmployeeId) : $employees;

        return view('humanresource::attendance.shift-rosters.index', compact(
            'employees', 'shifts', 'displayEmployees', 'rosterMap', 'selectedYear', 'selectedMonth',
            'selectedEmployeeId', 'daysInMonth', 'monthDays', 'departments', 'selectedDepartmentId'
        ));
    }

    public function store(Request $request): mixed
    {
        $this->scope->authorize('create_shift_roster');
        $data = $request->validate([
            'department_id' => ['nullable', 'integer'],
            'employee_id' => ['required', 'integer'],
            'roster_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:roster_date'],
            'shift_id' => ['nullable', 'integer'],
            'is_day_off' => ['nullable', 'boolean'],
            'is_holiday' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $unitId = $this->scope->selected($request);
        // API clients may infer the unit from the authorized employee.
        $employee = $this->scope->employees($unitId)->where('is_active', 1)->findOrFail($data['employee_id']);
        $unitId = AttendanceUnitScope::employeeUnit($employee);
        $off = $request->boolean('is_day_off');
        $holiday = $request->boolean('is_holiday');
        if ((int) $off + (int) $holiday + (int) ! empty($data['shift_id']) !== 1) {
            throw ValidationException::withMessages(['shift_id' => 'សូមជ្រើសរើសតែមួយ៖ វេនធ្វើការ ថ្ងៃឈប់ ឬថ្ងៃបុណ្យ។']);
        }
        $shift = empty($data['shift_id']) ? null : Shift::where('department_id', $unitId)->where('is_active', 1)->findOrFail($data['shift_id']);
        $start = Carbon::parse($data['roster_date']);
        $end = Carbon::parse($data['end_date'] ?? $data['roster_date']);
        if ($start->diffInDays($end) > 30) {
            throw ValidationException::withMessages(['end_date' => 'អាចរៀបចំបានអតិបរមា ៣១ ថ្ងៃក្នុងមួយលើក។']);
        }
        $rows = DB::transaction(function () use ($employee, $shift, $start, $end, $off, $holiday, $data) {
            $employee->newQuery()->whereKey($employee->id)->lockForUpdate()->first();
            $rows = collect();
            foreach (CarbonPeriod::create($start, $end) as $day) {
                if ($shift) {
                    [$from, $to] = $this->resolver->shiftBounds($shift, $day);
                    foreach ([$day->copy()->subDay(), $day->copy()->addDay()] as $adjacentDay) {
                        $adjacent = $adjacentDay->betweenIncluded($start, $end) ? $shift : ($this->resolver->resolveForDate($employee->id, $adjacentDay)['shift'] ?? null);
                        if (! $adjacent) {
                            continue;
                        }
                        [$otherFrom, $otherTo] = $this->resolver->shiftBounds($adjacent, $adjacentDay);
                        if ($from->lt($otherTo) && $to->gt($otherFrom)) {
                            throw ValidationException::withMessages(['roster_date' => 'វេននេះជាន់ម៉ោងជាមួយវេននៅថ្ងៃជាប់គ្នា។']);
                        }
                    }
                }
                $row = ShiftRoster::firstOrNew(['employee_id' => $employee->id, 'roster_date' => $day->toDateString()]);
                if (! $row->exists) {
                    $row->uuid = (string) Str::uuid();
                    $row->created_by = auth()->id();
                }
                $row->fill(['shift_id' => $shift?->id, 'is_day_off' => $off, 'is_holiday' => $holiday, 'note' => $data['note'] ?? null])->save();
                $rows->push($row);
            }
            AttendanceDailySnapshot::where('employee_id', $employee->id)
                ->whereBetween('snapshot_date', [$start->copy()->subDay()->toDateString(), $end->copy()->addDay()->toDateString()])->delete();

            return $rows;
        });
        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'data' => $rows->first(), 'saved_records' => $rows->count()], 201);
        }

        return redirect()->route('shift-rosters.index', ['department_id' => $unitId, 'year' => $start->year, 'month' => $start->month])
            ->with('success', 'បានរក្សាទុកតារាងវេន។');
    }

    public function destroy(Request $request, int $id): mixed
    {
        $this->scope->authorize('create_shift_roster');
        $row = ShiftRoster::whereIn('employee_id', $this->scope->employees()->select('employees.id'))->findOrFail($id);
        $unitId = AttendanceUnitScope::employeeUnit($row->employee);
        $day = $row->roster_date;
        DB::transaction(function () use ($row, $day) {
            $row->delete();
            AttendanceDailySnapshot::where('employee_id', $row->employee_id)
                ->whereBetween('snapshot_date', [$day->copy()->subDay()->toDateString(), $day->copy()->addDay()->toDateString()])->delete();
        });
        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return redirect()->route('shift-rosters.index', ['department_id' => $unitId, 'year' => $day->year, 'month' => $day->month])
            ->with('success', 'បានលុបវេន។');
    }
}
