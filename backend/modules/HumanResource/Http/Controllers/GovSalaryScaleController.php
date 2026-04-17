<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\GovSalaryScale;
use Modules\HumanResource\Entities\GovSalaryScaleValue;
use Modules\HumanResource\Entities\ProfessionalSkill;

class GovSalaryScaleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_setup_rules', ['only' => ['index']]);
        $this->middleware('permission:create_setup_rules', ['only' => ['store']]);
        $this->middleware('permission:update_setup_rules', ['only' => ['update', 'updateValues']]);
        $this->middleware('permission:delete_setup_rules', ['only' => ['destroy']]);
    }

    public function index(Request $request)
    {
        $scales = GovSalaryScale::withCount('values')->get();
        $skills = ProfessionalSkill::where('is_active', true)->get();

        $selectedScale = null;
        if ($request->filled('scale')) {
            $selectedScale = GovSalaryScale::where('uuid', $request->query('scale'))
                ->with('values')
                ->first();
        }

        if (!$selectedScale) {
            $selectedScale = GovSalaryScale::with('values')->first();
        }

        $valueMap = $selectedScale
            ? $selectedScale->values->pluck('value', 'professional_skill_id')->toArray()
            : [];

        return view('humanresource::master-data.salary-scales.index', [
            'scales' => $scales,
            'skills' => $skills,
            'selected_scale' => $selectedScale,
            'value_map' => $valueMap,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name_en' => 'required|string|max:120|unique:gov_salary_scales,name_en',
            'name_km' => 'nullable|string|max:120',
            'is_active' => 'required|boolean',
        ]);

        GovSalaryScale::create($validated);
        Toastr::success('Salary scale added successfully :)', 'Success');
        return redirect()->route('salary-scales.index');
    }

    public function update(Request $request, string $uuid)
    {
        $scale = GovSalaryScale::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'name_en' => 'required|string|max:120|unique:gov_salary_scales,name_en,' . $scale->id,
            'name_km' => 'nullable|string|max:120',
            'is_active' => 'required|boolean',
        ]);

        $scale->update($validated);
        Toastr::success('Salary scale updated successfully :)', 'Success');
        return redirect()->route('salary-scales.index', ['scale' => $scale->uuid]);
    }

    public function destroy(string $uuid)
    {
        GovSalaryScale::where('uuid', $uuid)->firstOrFail()->delete();
        Toastr::success('Salary scale deleted successfully :)', 'Success');
        return response()->json(['success' => true]);
    }

    public function updateValues(Request $request, string $uuid)
    {
        $scale = GovSalaryScale::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'values' => 'nullable|array',
            'values.*' => 'nullable|numeric|min:0|max:999999',
        ]);

        $values = $validated['values'] ?? [];
        $skillIds = ProfessionalSkill::where('is_active', true)->pluck('id');

        foreach ($skillIds as $skillId) {
            $rawValue = $values[$skillId] ?? null;

            if ($rawValue === null || $rawValue === '') {
                GovSalaryScaleValue::where('gov_salary_scale_id', $scale->id)
                    ->where('professional_skill_id', $skillId)
                    ->delete();
                continue;
            }

            GovSalaryScaleValue::updateOrCreate(
                [
                    'gov_salary_scale_id' => $scale->id,
                    'professional_skill_id' => $skillId,
                ],
                [
                    'value' => $rawValue,
                ]
            );
        }

        Toastr::success('Salary scale values updated successfully :)', 'Success');
        return redirect()->route('salary-scales.index', ['scale' => $scale->uuid]);
    }
}
