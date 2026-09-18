<?php

namespace Modules\HumanResource\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Shift;
use Modules\HumanResource\Entities\ShiftAssignment;
use Modules\HumanResource\Entities\ShiftRoster;
use Modules\HumanResource\Support\AttendanceUnitScope;

class ShiftController extends Controller
{
    public function __construct(private readonly AttendanceUnitScope $scope)
    {
    }

    private function query(?int $unit = null)
    {
        return Shift::query()->where(function ($q) use ($unit) {
            $q->whereIn('department_id', $unit !== null ? [$unit] : $this->scope->departments()->pluck('id'));
            if ((int) auth()->user()?->user_type_id === 1) {
                $q->orWhereNull('department_id');
            }
        });
    }

    public function index(Request $request): mixed
    {
        $this->scope->authorize('read_shift');
        $selectedDepartmentId = $this->scope->selected($request);
        $query = $this->query($selectedDepartmentId)->with('department')->orderBy('name');
        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'data' => $query->get()]);
        }
        $departments = $this->scope->departments()->get();
        $shifts = $query->paginate(20)->withQueryString();
        $editingShift = $request->filled('edit') ? $this->query()->findOrFail($request->integer('edit')) : null;

        return view('humanresource::attendance.shifts.index', compact('shifts', 'departments', 'selectedDepartmentId', 'editingShift'));
    }

    public function store(Request $request): mixed
    {
        return $this->save($request);
    }

    public function update(Request $request, int $id): mixed
    {
        $this->scope->authorize('create_shift');

        return $this->save($request, $this->query()->findOrFail($id));
    }

    private function save(Request $request, ?Shift $shift = null): mixed
    {
        $this->scope->authorize('create_shift');
        $data = $request->validate([
            'department_id' => ['required', 'integer'],
            'code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'morning_end_time' => ['nullable', 'required_with:afternoon_start_time', 'date_format:H:i'],
            'afternoon_start_time' => ['nullable', 'required_with:morning_end_time', 'date_format:H:i'],
            'is_cross_day' => ['nullable', 'boolean'],
            'is_duty' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'grace_late_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'grace_early_leave_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
        ]);
        $unit = $this->scope->departments()->findOrFail($data['department_id']);
        foreach (['is_cross_day', 'is_duty', 'is_default', 'is_active'] as $field) {
            $data[$field] = $request->boolean($field, $field === 'is_active');
        }
        $data['grace_late_minutes'] = (int) ($data['grace_late_minutes'] ?? 0);
        $data['grace_early_leave_minutes'] = (int) ($data['grace_early_leave_minutes'] ?? 0);
        $data['morning_end_time'] = $data['morning_end_time'] ?? null;
        $data['afternoon_start_time'] = $data['afternoon_start_time'] ?? null;
        // Attendance Phase B: duty/on-call shifts (វេនយាម) only make sense
        // for facilities that serve patients around the clock (hospitals,
        // health centers) -- the PHD provincial office and OD administrative
        // offices run fixed hours and must never be given a duty shift.
        // Existing duty shifts already saved on an ineligible unit are left
        // untouched; this only gates new creates/edits.
        if ($data['is_duty'] && ! in_array($unit->unitType?->code, AttendanceUnitScope::DUTY_ELIGIBLE_UNIT_TYPES, true)) {
            throw ValidationException::withMessages(['is_duty' => 'វេនយាមអាចកំណត់បានតែសម្រាប់អង្គភាពដែលផ្តល់សេវាដល់អ្នកជំងឺ (មន្ទីរពេទ្យ/មណ្ឌលសុខភាព) ប៉ុណ្ណោះ។']);
        }
        if ((! $data['is_cross_day'] && $data['end_time'] <= $data['start_time']) ||
            ($data['is_cross_day'] && $data['end_time'] > $data['start_time'])) {
            throw ValidationException::withMessages(['end_time' => 'ម៉ោងចេញត្រូវក្រោយម៉ោងចូល។ វេនឆ្លងថ្ងៃត្រូវមានរយៈពេលមិនលើស ២៤ ម៉ោង។']);
        }
        if ($data['morning_end_time'] && ($data['is_cross_day'] ||
            ! ($data['start_time'] < $data['morning_end_time'] && $data['morning_end_time'] < $data['afternoon_start_time'] && $data['afternoon_start_time'] < $data['end_time']))) {
            throw ValidationException::withMessages(['morning_end_time' => 'លំដាប់ម៉ោង៖ ចូលព្រឹក < ចេញព្រឹក < ចូលល្ងាច < ចេញល្ងាច។']);
        }
        if ($data['is_default'] && ($data['is_duty'] || ! $data['is_active'])) {
            throw ValidationException::withMessages(['is_default' => 'ម៉ោងគោលរបស់អង្គភាពត្រូវជាវេនធម្មតាដែលកំពុងប្រើប្រាស់។']);
        }
        // A used schedule belongs permanently to its unit. Edit times by creating a new schedule.
        if ($shift && $this->isUsed($shift) && collect($data)->except(['name', 'code', 'is_default'])->contains(function ($value, $key) use ($shift) {
            $old = $shift->$key;
            if (str_ends_with($key, '_time') && $old) {
                $old = substr($old, 0, 5);
            }

            return $old != $value;
        })) {
            throw ValidationException::withMessages(['name' => 'វេននេះកំពុងត្រូវបានប្រើ។ សូមបង្កើតវេនថ្មី ដើម្បីរក្សាតារាង និងប្រវត្តិវត្តមានចាស់។']);
        }
        $created = ! $shift;
        $shift = DB::transaction(function () use ($data, $unit, $shift) {
            // Serialize default changes within a unit.
            $unit->newQuery()->whereKey($unit->id)->lockForUpdate()->first();
            if ($data['is_default']) {
                Shift::query()->where('department_id', $unit->id)->update(['is_default' => false]);
            }
            if ($shift) {
                $shift->update($data);

                return $shift->refresh();
            }

            return Shift::create($data + ['uuid' => (string) Str::uuid(), 'created_by' => auth()->id()]);
        });
        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok', 'data' => $shift], $created ? 201 : 200);
        }

        return redirect()->route('shifts.index', ['department_id' => $unit->id])->with('success', 'បានរក្សាទុកម៉ោងធ្វើការ។');
    }

    private function isUsed(Shift $shift): bool
    {
        return ShiftRoster::where('shift_id', $shift->id)->exists()
            || ShiftAssignment::where('shift_id', $shift->id)->exists()
            || Employee::where('default_shift_id', $shift->id)->exists();
    }

    public function destroy(Request $request, int $id): mixed
    {
        $this->scope->authorize('create_shift');
        $shift = $this->query()->findOrFail($id);
        if ($shift->is_default || $this->isUsed($shift)) {
            throw ValidationException::withMessages(['shift_id' => 'មិនអាចលុបវេនគោល ឬវេនដែលមានក្នុងតារាងបានទេ។']);
        }
        $shift->delete();
        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return redirect()->route('shifts.index', ['department_id' => $shift->department_id])->with('success', 'បានលុបវេន។');
    }
}
