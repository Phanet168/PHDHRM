<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\ProfessionalSkill;

class ProfessionalSkillController extends Controller
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
        return view('humanresource::master-data.professional-skills.index', [
            'skills' => ProfessionalSkill::all(),
        ]);
    }

    public function store(Request $request)
    {
        $nameKm = trim((string) $request->input('name_km'));
        $nameEn = trim((string) $request->input('name_en'));
        if ($nameEn === '') {
            $nameEn = $nameKm;
        }

        $request->merge([
            'name_km' => $nameKm,
            'name_en' => $nameEn,
            'shortcut_en' => trim((string) $request->input('shortcut_en')) ?: null,
            'shortcut_km' => trim((string) $request->input('shortcut_km')) ?: null,
        ]);

        $validated = $request->validate([
            'code' => 'nullable|string|max:20|unique:professional_skills,code',
            'name_en' => 'required|string|max:120|unique:professional_skills,name_en',
            'name_km' => 'required|string|max:120',
            'shortcut_en' => 'nullable|string|max:20',
            'shortcut_km' => 'nullable|string|max:20',
            'retire_age' => 'nullable|integer|min:45|max:80',
            'budget_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'is_active' => 'required|boolean',
        ]);

        $validated['budget_amount'] = (float) ($validated['budget_amount'] ?? 0);
        ProfessionalSkill::create($validated);
        Toastr::success('បានបន្ថែមជំនាញជោគជ័យ', 'ជោគជ័យ');
        return redirect()->route('professional-skills.index');
    }

    public function update(Request $request, string $uuid)
    {
        $skill = ProfessionalSkill::where('uuid', $uuid)->firstOrFail();

        $nameKm = trim((string) $request->input('name_km'));
        $nameEn = trim((string) $request->input('name_en'));
        if ($nameEn === '') {
            $nameEn = $nameKm;
        }

        $request->merge([
            'name_km' => $nameKm,
            'name_en' => $nameEn,
            'shortcut_en' => trim((string) $request->input('shortcut_en')) ?: null,
            'shortcut_km' => trim((string) $request->input('shortcut_km')) ?: null,
        ]);

        $validated = $request->validate([
            'code' => 'nullable|string|max:20|unique:professional_skills,code,' . $skill->id,
            'name_en' => 'required|string|max:120|unique:professional_skills,name_en,' . $skill->id,
            'name_km' => 'required|string|max:120',
            'shortcut_en' => 'nullable|string|max:20',
            'shortcut_km' => 'nullable|string|max:20',
            'retire_age' => 'nullable|integer|min:45|max:80',
            'budget_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'is_active' => 'required|boolean',
        ]);

        $validated['budget_amount'] = (float) ($validated['budget_amount'] ?? 0);
        $skill->update($validated);
        Toastr::success('បានកែប្រែជំនាញជោគជ័យ', 'ជោគជ័យ');
        return redirect()->route('professional-skills.index');
    }

    public function destroy(string $uuid)
    {
        $skill = ProfessionalSkill::where('uuid', $uuid)->firstOrFail();

        if ($skill->salaryScaleValues()->exists()) {
            Toastr::error('ជំនាញនេះកំពុងប្រើក្នុងការកំណត់សន្ទស្សន៍ប្រាក់បៀវត្ស', 'បរាជ័យ');
            return response()->json(['success' => false], 422);
        }

        $skill->delete();
        Toastr::success('បានលុបជំនាញជោគជ័យ', 'ជោគជ័យ');
        return response()->json(['success' => true]);
    }
}
