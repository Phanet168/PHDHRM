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
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftRoster;
use Modules\HumanResource\Entities\ShiftTeam;
use Modules\HumanResource\Services\ShiftResolverService;
use Modules\HumanResource\Services\ShiftRosterGeneratorService;
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
        $rows = DB::transaction(fn () => $this->assignOneEmployee($employee, $shift, $start, $end, $off, $holiday, $data['note'] ?? null));
        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'data' => $rows->first(), 'saved_records' => $rows->count()], 201);
        }

        return redirect()->route('shift-rosters.index', ['department_id' => $unitId, 'year' => $start->year, 'month' => $start->month])
            ->with('success', 'បានរក្សាទុកតារាងវេន។');
    }

    /**
     * Assigns one employee's roster rows for [$start, $end] -- the exact
     * per-day overlap-check + upsert logic store() already used, extracted
     * so storeForTeam() (Phase C) and the roster generator (Phase D) share
     * the identical validation rather than duplicating it. Caller must wrap
     * this in its own DB::transaction() -- kept out of here so a team/bulk
     * caller can wrap MULTIPLE employees in one all-or-nothing transaction.
     *
     * @return \Illuminate\Support\Collection<int, ShiftRoster>
     */
    private function assignOneEmployee(
        Employee $employee,
        ?Shift $shift,
        Carbon $start,
        Carbon $end,
        bool $off,
        bool $holiday,
        ?string $note
    ): \Illuminate\Support\Collection {
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
                        throw ValidationException::withMessages(['roster_date' => 'វេននេះជាន់ម៉ោងជាមួយវេននៅថ្ងៃជាប់គ្នា។ (' . $employee->full_name . ')']);
                    }
                }
            }
            $row = ShiftRoster::firstOrNew(['employee_id' => $employee->id, 'roster_date' => $day->toDateString()]);
            if (! $row->exists) {
                $row->uuid = (string) Str::uuid();
                $row->created_by = auth()->id();
            }
            $row->fill(['shift_id' => $shift?->id, 'is_day_off' => $off, 'is_holiday' => $holiday, 'note' => $note])->save();
            $rows->push($row);
        }
        AttendanceDailySnapshot::where('employee_id', $employee->id)
            ->whereBetween('snapshot_date', [$start->copy()->subDay()->toDateString(), $end->copy()->addDay()->toDateString()])->delete();

        return $rows;
    }

    /** Same as store(), but assigns every active member of a pre-defined duty team in one all-or-nothing action. */
    public function storeForTeam(Request $request): mixed
    {
        $this->scope->authorize('create_shift_roster');
        $data = $request->validate([
            'shift_team_id' => ['required', 'integer'],
            'roster_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:roster_date'],
            'shift_id' => ['nullable', 'integer'],
            'is_day_off' => ['nullable', 'boolean'],
            'is_holiday' => ['nullable', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $team = ShiftTeam::whereIn('department_id', $this->scope->departments()->pluck('id'))
            ->with(['activeEmployees'])
            ->findOrFail($data['shift_team_id']);
        $off = $request->boolean('is_day_off');
        $holiday = $request->boolean('is_holiday');
        if ((int) $off + (int) $holiday + (int) ! empty($data['shift_id']) !== 1) {
            throw ValidationException::withMessages(['shift_id' => 'សូមជ្រើសរើសតែមួយ៖ វេនធ្វើការ ថ្ងៃឈប់ ឬថ្ងៃបុណ្យ។']);
        }
        $shift = empty($data['shift_id']) ? null : Shift::where('department_id', $team->department_id)->where('is_active', 1)->findOrFail($data['shift_id']);
        $start = Carbon::parse($data['roster_date']);
        $end = Carbon::parse($data['end_date'] ?? $data['roster_date']);
        if ($start->diffInDays($end) > 30) {
            throw ValidationException::withMessages(['end_date' => 'អាចរៀបចំបានអតិបរមា ៣១ ថ្ងៃក្នុងមួយលើក។']);
        }
        $members = $team->activeEmployees;
        if ($members->isEmpty()) {
            throw ValidationException::withMessages(['shift_team_id' => 'ក្រុមនេះមិនទាន់មានសមាជិកសកម្មទេ។']);
        }

        // One transaction for the WHOLE team -- if any single member has a
        // conflict on any day, nothing is saved for anyone in the batch.
        $rows = DB::transaction(function () use ($members, $shift, $start, $end, $off, $holiday, $data) {
            $collected = collect();
            foreach ($members as $employee) {
                $collected = $collected->merge($this->assignOneEmployee($employee, $shift, $start, $end, $off, $holiday, $data['note'] ?? null));
            }

            return $collected;
        });

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'saved_records' => $rows->count(), 'member_count' => $members->count()], 201);
        }

        return redirect()->route('shift-rosters.index', ['department_id' => $team->department_id, 'year' => $start->year, 'month' => $start->month])
            ->with('success', 'បានរក្សាទុកតារាងវេនសម្រាប់ក្រុមទាំងមូល។');
    }

    /** Phase D: preview an automatically-generated, fairly-rotated roster without saving anything. */
    public function generatePreview(Request $request, ShiftRosterGeneratorService $generator): mixed
    {
        $this->scope->authorize('create_shift_roster');
        [$department, $shift, $team, $start, $end] = $this->parseGenerateRequest($request);

        $preview = $generator->generate($department, $shift, $start, $end, $team);

        return response()->json(['status' => 'ok', 'data' => $preview]);
    }

    /** Phase D: re-run the same generation and persist it, via the same assignOneEmployee() every other path uses. */
    public function generateCommit(Request $request, ShiftRosterGeneratorService $generator): mixed
    {
        $this->scope->authorize('create_shift_roster');
        [$department, $shift, $team, $start, $end] = $this->parseGenerateRequest($request);

        $preview = $generator->generate($department, $shift, $start, $end, $team);

        $rows = DB::transaction(function () use ($preview, $shift) {
            $collected = collect();
            foreach ($preview as $day) {
                if (! $day['employee_id']) {
                    continue;
                }
                $employee = Employee::findOrFail($day['employee_id']);
                $collected = $collected->merge($this->assignOneEmployee(
                    $employee,
                    $shift,
                    \Carbon\Carbon::parse($day['date']),
                    \Carbon\Carbon::parse($day['date']),
                    false,
                    false,
                    'ស្វ័យប្រវត្តិ (Auto-generated)'
                ));
            }

            return $collected;
        });

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'saved_records' => $rows->count()], 201);
        }

        return redirect()->back()->with('success', 'បានបង្កើតតារាងវេនស្វ័យប្រវត្តិដោយជោគជ័យ។');
    }

    /** @return array{0: \Modules\HumanResource\Entities\Department, 1: Shift, 2: ?ShiftTeam, 3: Carbon, 4: Carbon} */
    private function parseGenerateRequest(Request $request): array
    {
        $data = $request->validate([
            'department_id' => ['required', 'integer'],
            'shift_id' => ['required', 'integer'],
            'shift_team_id' => ['nullable', 'integer'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $department = $this->scope->departments()->findOrFail($data['department_id']);
        $shift = Shift::where('department_id', $department->id)->where('is_active', 1)->where('is_duty', true)->findOrFail($data['shift_id']);
        $team = empty($data['shift_team_id'])
            ? null
            : ShiftTeam::where('department_id', $department->id)->findOrFail($data['shift_team_id']);
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        if ($start->diffInDays($end) > 60) {
            throw ValidationException::withMessages(['end_date' => 'អាចបង្កើតបានអតិបរមា ៦០ ថ្ងៃក្នុងមួយលើក។']);
        }

        return [$department, $shift, $team, $start, $end];
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
