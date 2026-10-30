<?php

namespace Modules\Report\Http\Controllers\Health;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Report\Entities\ReportDepartment;
use Modules\Report\Entities\ReportIndicator;
use Modules\Report\Entities\ReportUnit;
use Modules\Report\Services\CodeGenerator;

/** ផ្នែក និងជំពូក — departments (ជំពូក) and their units (ផ្នែក). */
class StructureController extends HealthController
{
    public function index()
    {
        return view('report::health.structure', [
            'nextDepartmentCode' => CodeGenerator::next('report_departments'),
            'nextUnitCode' => CodeGenerator::next('report_units'),
            'departments' => ReportDepartment::withCount('indicators')
                ->with(['units' => fn ($q) => $q->withCount('indicators')])
                ->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function saveDepartment(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:report_departments,id'],
            'code' => ['nullable', 'string', 'max:64', Rule::unique('report_departments', 'code')->ignore($request->input('id'))],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ], ['code.unique' => 'លេខកូដនេះមានរួចហើយ — ទុកទទេដើម្បីបង្កើតដោយស្វ័យប្រវត្តិ']);
        ReportDepartment::updateOrCreate(['id' => $data['id'] ?? null], [
            'code' => trim((string) ($data['code'] ?? '')) ?: CodeGenerator::next('report_departments'),
            'name' => $data['name'],
            'sort_order' => $data['sort_order'] ?? 0,
            'active' => $request->boolean('active', true),
        ]);

        return back()->with('success', 'បានរក្សាទុកជំពូក');
    }

    public function destroyDepartment(ReportDepartment $department)
    {
        if ($department->indicators()->exists()) {
            return back()->withErrors(['department' => 'មិនអាចលុបបានទេ ព្រោះជំពូកនេះនៅមានសូចនាករ']);
        }
        $department->delete();

        return back()->with('success', 'បានលុបជំពូក');
    }

    public function saveUnit(Request $request)
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer', 'exists:report_units,id'],
            'department_id' => ['required', 'integer', 'exists:report_departments,id'],
            'code' => ['nullable', 'string', 'max:64', Rule::unique('report_units', 'code')->ignore($request->input('id'))],
            'name' => ['required', 'string', 'max:255'],
            'unit_type' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ], ['code.unique' => 'លេខកូដនេះមានរួចហើយ — ទុកទទេដើម្បីបង្កើតដោយស្វ័យប្រវត្តិ']);
        $unit = ReportUnit::updateOrCreate(['id' => $data['id'] ?? null], [
            'department_id' => $data['department_id'],
            'code' => trim((string) ($data['code'] ?? '')) ?: CodeGenerator::next('report_units'),
            'name' => $data['name'],
            'unit_type' => $data['unit_type'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'active' => $request->boolean('active', true),
        ]);
        // Indicators follow their unit when it moves to another department.
        ReportIndicator::where('unit_id', $unit->id)->update(['department_id' => $unit->department_id]);

        return back()->with('success', 'បានរក្សាទុកផ្នែក');
    }

    public function destroyUnit(ReportUnit $unit)
    {
        if ($unit->indicators()->exists()) {
            return back()->withErrors(['unit' => 'មិនអាចលុបបានទេ ព្រោះផ្នែកនេះនៅមានសូចនាករ']);
        }
        $unit->delete();

        return back()->with('success', 'បានលុបផ្នែក');
    }
}
