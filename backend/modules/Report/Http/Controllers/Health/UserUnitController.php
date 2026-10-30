<?php

namespace Modules\Report\Http\Controllers\Health;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Report\Entities\ReportDepartment;
use Modules\Report\Entities\ReportUserUnit;

/** អ្នកប្រើប្រាស់ — which HRM users report for which units. */
class UserUnitController extends HealthController
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $assignments = ReportUserUnit::with(['user:id,full_name,user_name,email', 'unit.department'])
            ->when($q !== '', fn ($b) => $b->whereHas('user', fn ($u) => $u->where('full_name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%")))
            ->get()->groupBy('user_id');

        return view('report::health.users', [
            'assignments' => $assignments,
            'users' => User::query()->where('is_active', 1)->orderBy('full_name')->get(['id', 'full_name', 'user_name', 'email']),
            'departments' => ReportDepartment::with('units')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    /** Replaces the user's unit list with the submitted one. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'unit_ids' => ['required', 'array', 'min:1'],
            'unit_ids.*' => ['integer', 'exists:report_units,id'],
            'can_edit' => ['nullable', 'boolean'],
        ], [], ['unit_ids' => 'ផ្នែក']);

        DB::transaction(function () use ($data, $request) {
            ReportUserUnit::where('user_id', $data['user_id'])->whereNotIn('unit_id', $data['unit_ids'])->delete();
            foreach (array_unique($data['unit_ids']) as $unitId) {
                ReportUserUnit::updateOrCreate(
                    ['user_id' => $data['user_id'], 'unit_id' => $unitId],
                    ['can_edit' => $request->boolean('can_edit')]
                );
            }
        });

        return back()->with('success', 'បានរក្សាទុកសិទ្ធិអ្នកប្រើប្រាស់');
    }

    public function destroy(int $user)
    {
        ReportUserUnit::where('user_id', $user)->delete();

        return back()->with('success', 'បានដកសិទ្ធិអ្នកប្រើប្រាស់');
    }
}
