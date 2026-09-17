<?php

namespace Modules\HumanResource\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Services\AttendanceDashboardService;
use Modules\HumanResource\Support\AttendanceUnitScope;

class AttendanceDashboardController extends Controller
{
    public function __construct(private readonly AttendanceUnitScope $scope, private readonly AttendanceDashboardService $dashboard)
    {
    }

    public function units(Request $request)
    {
        $this->scope->authorize('read_attendance');

        return response()->json(['response' => ['status' => 'ok', 'data' => $this->scope->departments()->get(['id', 'department_name'])]]);
    }

    public function index(Request $request)
    {
        $this->scope->authorize('read_attendance');
        $this->scope->authorize('attendance_management');
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'department_id' => [$request->expectsJson() ? 'required' : 'nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:all,recorded,attention,late,early_leave,absent,incomplete,duty,waiting,unscheduled,leave,mission,off'],
        ]);
        $selectedDepartmentId = $this->scope->selected($request);
        $departments = $this->scope->departments()->get();
        $department = $departments->firstWhere('id', $selectedDepartmentId);
        $selectedDate = $request->input('date') ?: today()->toDateString();
        $filter = $request->input('status') ?: 'all';
        $search = trim($request->input('q', ''));
        $employees = $this->scope->employees($selectedDepartmentId)->where('is_active', 1)->orderBy('first_name')->get();
        $data = $this->dashboard->build($employees, Carbon::parse($selectedDate));
        $summary = $data['summary'];
        $sessionSummary = $data['sessions'];
        $rows = $data['rows']->filter(function ($r) use ($filter, $search) {
            $matches = match ($filter) {
                'recorded' => $r['recorded'], 'attention' => $r['needs_attention'], 'duty' => $r['is_duty'],
                'late' => $r['late_minutes'] > 0, 'early_leave' => $r['early_leave_minutes'] > 0,
                'absent' => $r['status'] === 'Absent', 'incomplete' => $r['status'] === 'Incomplete',
                'waiting' => in_array($r['status'], ['Waiting', 'In Progress'], true), 'unscheduled' => $r['status'] === 'Unscheduled',
                'leave' => $r['status'] === 'On Leave', 'mission' => $r['status'] === 'On Mission',
                'off' => in_array($r['status'], ['Holiday', 'Day Off'], true), default => true,
            };

            return $matches && ($search === '' || mb_stripos($r['name'].' '.$r['employee_number'], $search) !== false);
        })->sortByDesc('needs_attention')->values();
        $records = new LengthAwarePaginator($rows->forPage($request->integer('page', 1), 20)->values(), $rows->count(), 20,
            $request->integer('page', 1), ['path' => $request->url(), 'query' => $request->except('page')]);
        $defaultShift = Shift::where('department_id', $selectedDepartmentId)->where('is_default', true)->where('is_active', true)->first();
        if ($request->expectsJson()) {
            return response()->json(['response' => ['status' => 'ok', 'data' => [
                'date' => $selectedDate, 'unit' => $department ? ['id' => $department->id, 'name' => $department->department_name] : null,
                'summary' => $summary, 'sessions' => $sessionSummary, 'records' => $records,
            ], 'meta' => ['timezone' => config('app.timezone'), 'computed_at' => now()->toIso8601String()]]]);
        }

        return view('humanresource::attendance.dashboard', compact('departments', 'department', 'selectedDepartmentId', 'selectedDate', 'summary', 'sessionSummary', 'records', 'filter', 'search', 'defaultShift'));
    }
}
