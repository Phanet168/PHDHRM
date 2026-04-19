<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftRoster;

class ShiftRosterController extends Controller
{
    public function index(Request $request): mixed
    {
        $query = ShiftRoster::query()->with(['employee', 'shift']);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', (int) $request->input('employee_id'));
        }
        if ($request->filled('date')) {
            $query->whereDate('roster_date', $request->input('date'));
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'data' => $query->orderBy('roster_date')->orderBy('employee_id')->get(),
            ]);
        }

        $selectedYear  = (int) $request->input('year', now()->year);
        $selectedMonth = (int) $request->input('month', now()->month);
        $selectedEmployeeId = $request->input('employee_id');

        $startDate   = \Carbon\Carbon::create($selectedYear, $selectedMonth, 1)->startOfDay();
        $endDate     = \Carbon\Carbon::create($selectedYear, $selectedMonth)->endOfMonth()->endOfDay();
        $daysInMonth = $startDate->daysInMonth;
        $monthDays   = range(1, $daysInMonth);

        $employees = Employee::query()->where('is_active', 1)->orderBy('full_name')->get(['id', 'full_name', 'employee_id']);
        $shifts    = Shift::query()->where('is_active', 1)->orderBy('name')->get(['id', 'name', 'code', 'start_time', 'end_time']);

        $rosterQuery = ShiftRoster::query()
            ->with('shift')
            ->whereBetween('roster_date', [$startDate->toDateString(), $endDate->toDateString()]);

        if ($selectedEmployeeId) {
            $rosterQuery->where('employee_id', (int) $selectedEmployeeId);
        }

        $rosterMap = $rosterQuery->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->keyBy(fn ($r) => (int) \Carbon\Carbon::parse($r->roster_date)->day));

        $displayEmployees = $selectedEmployeeId
            ? $employees->where('id', (int) $selectedEmployeeId)
            : $employees;

        return view('humanresource::attendance.shift-rosters.index', compact(
            'employees', 'shifts', 'displayEmployees', 'rosterMap',
            'selectedYear', 'selectedMonth', 'selectedEmployeeId',
            'daysInMonth', 'monthDays'
        ));
    }

    public function store(Request $request): mixed
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'roster_date' => ['required', 'date'],
            'shift_id'    => ['nullable', 'integer', 'exists:shifts,id'],
            'is_day_off'  => ['nullable', 'boolean'],
            'is_holiday'  => ['nullable', 'boolean'],
            'note'        => ['nullable', 'string', 'max:500'],
        ]);

        $roster = ShiftRoster::query()->updateOrCreate(
            [
                'employee_id' => (int) $validated['employee_id'],
                'roster_date' => $validated['roster_date'],
            ],
            [
                'uuid'       => (string) Str::uuid(),
                'shift_id'   => $validated['shift_id'] ?? null,
                'is_day_off' => (bool) ($validated['is_day_off'] ?? false),
                'is_holiday' => (bool) ($validated['is_holiday'] ?? false),
                'created_by' => auth()->id(),
                'note'       => $validated['note'] ?? null,
            ]
        );

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'message' => 'Shift roster saved successfully.', 'data' => $roster], 201);
        }

        return redirect()->route('shift-rosters.index', [
            'year'  => \Carbon\Carbon::parse($validated['roster_date'])->year,
            'month' => \Carbon\Carbon::parse($validated['roster_date'])->month,
        ])->with('success', localize('roster_saved', 'Roster បានរក្សាទុក'));
    }

    public function destroy(Request $request, int $id): mixed
    {
        $roster = ShiftRoster::query()->findOrFail($id);
        $roster->delete();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'message' => 'Shift roster deleted successfully.']);
        }

        return redirect()->route('shift-rosters.index')
            ->with('success', localize('roster_deleted', 'Roster បានលុបរួចរាល់'));
    }
}
