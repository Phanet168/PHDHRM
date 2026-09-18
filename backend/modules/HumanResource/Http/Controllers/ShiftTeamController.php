<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\HumanResource\Entities\ShiftTeam;
use Modules\HumanResource\Entities\ShiftTeamMember;
use Modules\HumanResource\Support\AttendanceUnitScope;

/**
 * Attendance Management Phase C: a reusable, pre-defined duty-shift team
 * (ក្រុមវេន) an admin sets up once per facility, so a duty roster can later
 * be assigned to the whole team in one action (ShiftRosterController::
 * storeForTeam()) instead of one employee at a time. Reuses the SAME
 * AttendanceUnitScope authorization/scoping already protecting shifts and
 * rosters -- no new permission was created.
 */
class ShiftTeamController extends Controller
{
    public function __construct(private readonly AttendanceUnitScope $scope)
    {
    }

    private function query(?int $unit = null)
    {
        return ShiftTeam::query()->whereIn(
            'department_id',
            $unit !== null ? [$unit] : $this->scope->departments()->pluck('id')
        );
    }

    public function index(Request $request): mixed
    {
        $this->scope->authorize('read_shift_roster');
        $selectedDepartmentId = $this->scope->selected($request);
        $query = $this->query($selectedDepartmentId)->with(['activeEmployees'])->orderBy('name');

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'data' => $query->get()]);
        }

        $departments = $this->scope->departments()->get();
        $teams = $query->paginate(20)->withQueryString();
        $employees = $this->scope->employees($selectedDepartmentId)->where('is_active', 1)->orderBy('first_name')->get();

        return view('humanresource::attendance.shift-teams.index', compact('teams', 'departments', 'selectedDepartmentId', 'employees'));
    }

    public function store(Request $request): mixed
    {
        return $this->save($request);
    }

    public function update(Request $request, int $id): mixed
    {
        return $this->save($request, $this->query()->findOrFail($id));
    }

    private function save(Request $request, ?ShiftTeam $team = null): mixed
    {
        $this->scope->authorize('create_shift_roster');
        $data = $request->validate([
            'department_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:30'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $unit = $this->scope->departments()->findOrFail($data['department_id']);
        $data['is_active'] = $request->boolean('is_active', true);

        $created = ! $team;
        $team = DB::transaction(function () use ($data, $unit, $team) {
            $data['department_id'] = $unit->id;
            $data['updated_by'] = auth()->id();
            if ($team) {
                $team->update($data);

                return $team->refresh();
            }

            return ShiftTeam::create($data + ['uuid' => (string) Str::uuid(), 'created_by' => auth()->id()]);
        });

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'data' => $team->load('activeEmployees')], $created ? 201 : 200);
        }

        return redirect()->route('shift-teams.index', ['department_id' => $unit->id])->with('success', 'បានរក្សាទុកក្រុមវេន។');
    }

    public function destroy(Request $request, int $id): mixed
    {
        $this->scope->authorize('create_shift_roster');
        $team = $this->query()->findOrFail($id);
        $team->delete();

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return redirect()->route('shift-teams.index', ['department_id' => $team->department_id])->with('success', 'បានលុបក្រុមវេន។');
    }

    /** Replace the team's full membership list in one call -- add/remove in a single, predictable action. */
    public function syncMembers(Request $request, int $id): mixed
    {
        $this->scope->authorize('create_shift_roster');
        $team = $this->query()->findOrFail($id);
        $data = $request->validate([
            'employee_ids' => ['array'],
            'employee_ids.*' => ['integer'],
        ]);

        // Every member must belong to the team's own unit scope -- never
        // let this endpoint attach an employee from an unrelated facility.
        $validEmployeeIds = $this->scope->employees($team->department_id)
            ->whereIn('employees.id', $data['employee_ids'] ?? [])
            ->pluck('employees.id')
            ->all();

        DB::transaction(function () use ($team, $validEmployeeIds) {
            ShiftTeamMember::where('shift_team_id', $team->id)
                ->whereNotIn('employee_id', $validEmployeeIds)
                ->update(['is_active' => false, 'left_at' => now()->toDateString()]);

            foreach ($validEmployeeIds as $employeeId) {
                $member = ShiftTeamMember::firstOrNew(['shift_team_id' => $team->id, 'employee_id' => $employeeId]);
                if (! $member->exists) {
                    $member->joined_at = now()->toDateString();
                }
                $member->is_active = true;
                $member->left_at = null;
                $member->save();
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'data' => $team->load('activeEmployees')]);
        }

        return redirect()->route('shift-teams.index', ['department_id' => $team->department_id])->with('success', 'បានធ្វើបច្ចុប្បន្នភាពសមាជិកក្រុម។');
    }
}
