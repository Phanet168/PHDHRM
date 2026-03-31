<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\HumanResource\Entities\GovPayLevel;

class GovPayLevelController extends Controller
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
        return view('humanresource::master-data.pay-levels.index', [
            'pay_levels' => GovPayLevel::all(),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'level_name_km' => trim((string) $request->input('level_name_km')),
        ]);

        $validated = $request->validate([
            'level_code' => 'required|string|max:30|unique:gov_pay_levels,level_code',
            'level_name_km' => 'required|string|max:120',
            'budget_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'required|boolean',
        ]);

        GovPayLevel::create([
            'level_code' => $validated['level_code'],
            'level_name_km' => $validated['level_name_km'] ?? null,
            'budget_amount' => (float) ($validated['budget_amount'] ?? 0),
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'],
        ]);

        Toastr::success('បានបន្ថែមកាំប្រាក់ជោគជ័យ', 'ជោគជ័យ');
        return redirect()->route('pay-levels.index');
    }

    public function update(Request $request, string $uuid)
    {
        $payLevel = GovPayLevel::where('uuid', $uuid)->firstOrFail();

        $request->merge([
            'level_name_km' => trim((string) $request->input('level_name_km')),
        ]);

        $validated = $request->validate([
            'level_code' => 'required|string|max:30|unique:gov_pay_levels,level_code,' . $payLevel->id,
            'level_name_km' => 'required|string|max:120',
            'budget_amount' => 'nullable|numeric|min:0|max:999999999.99',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'required|boolean',
        ]);

        $payLevel->update([
            'level_code' => $validated['level_code'],
            'level_name_km' => $validated['level_name_km'] ?? null,
            'budget_amount' => (float) ($validated['budget_amount'] ?? 0),
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'],
        ]);

        Toastr::success('បានកែប្រែកាំប្រាក់ជោគជ័យ', 'ជោគជ័យ');
        return redirect()->route('pay-levels.index');
    }

    public function destroy(string $uuid)
    {
        GovPayLevel::where('uuid', $uuid)->firstOrFail()->delete();
        Toastr::success('បានលុបកាំប្រាក់ជោគជ័យ', 'ជោគជ័យ');
        return response()->json(['success' => true]);
    }
}
