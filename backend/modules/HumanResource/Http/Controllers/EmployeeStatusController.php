<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\EmployeeStatus;

class EmployeeStatusController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_setup_rules', ['only' => ['index']]);
        $this->middleware('permission:create_setup_rules', ['only' => ['store']]);
        $this->middleware('permission:update_setup_rules', ['only' => ['update']]);
        $this->middleware('permission:delete_setup_rules', ['only' => ['destroy']]);
    }

    public function index()
    {
        return view('humanresource::master-data.employee-statuses.index', [
            'employee_statuses' => EmployeeStatus::query()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|max:50|regex:/^[a-z0-9_\-]+$/|unique:employee_statuses,code',
            'name_km' => 'nullable|string|max:191',
            'name_en' => 'required|string|max:191|unique:employee_statuses,name_en',
            'transition_group' => 'required|in:active,suspended,inactive',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'required|boolean',
        ]);

        EmployeeStatus::create([
            'code' => $this->strOrNull($validated['code'] ?? null),
            'name_km' => $this->strOrNull($validated['name_km'] ?? null),
            'name_en' => trim((string) $validated['name_en']),
            'transition_group' => trim((string) $validated['transition_group']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) $validated['is_active'],
        ]);

        Toastr::success('Employee status added successfully', 'Success');
        return redirect()->route('employee-statuses.index');
    }

    public function update(Request $request, string $uuid)
    {
        $status = EmployeeStatus::query()->where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'code' => 'nullable|string|max:50|regex:/^[a-z0-9_\-]+$/|unique:employee_statuses,code,' . $status->id,
            'name_km' => 'nullable|string|max:191',
            'name_en' => 'required|string|max:191|unique:employee_statuses,name_en,' . $status->id,
            'transition_group' => 'required|in:active,suspended,inactive',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'required|boolean',
        ]);

        $status->update([
            'code' => $this->strOrNull($validated['code'] ?? null),
            'name_km' => $this->strOrNull($validated['name_km'] ?? null),
            'name_en' => trim((string) $validated['name_en']),
            'transition_group' => trim((string) $validated['transition_group']),
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => (bool) $validated['is_active'],
        ]);

        Toastr::success('Employee status updated successfully', 'Success');
        return redirect()->route('employee-statuses.index');
    }

    public function destroy(string $uuid)
    {
        $status = EmployeeStatus::query()->where('uuid', $uuid)->firstOrFail();

        $isUsedInEmployee = \Modules\HumanResource\Entities\Employee::query()
            ->where(function ($query) use ($status) {
                $query->where('work_status_name', $status->name_en);

                if (!empty($status->name_km)) {
                    $query->orWhere('work_status_name', $status->name_km);
                }
            })
            ->exists();

        if ($isUsedInEmployee) {
            return response()->json([
                'success' => false,
                'message' => 'Status is being used by employee records and cannot be deleted.',
            ], 422);
        }

        $status->delete();

        Toastr::success('Employee status deleted successfully', 'Success');
        return response()->json(['success' => true]);
    }

    protected function strOrNull($value): ?string
    {
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
