<?php

namespace Modules\Pharmaceutical\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\HumanResource\Entities\Department;
use Modules\Pharmaceutical\Entities\PharmMedicine;
use Modules\Pharmaceutical\Entities\PharmReport;
use Modules\Pharmaceutical\Entities\PharmReportItem;
use Modules\Pharmaceutical\Services\PharmReportService;
use Modules\Pharmaceutical\Traits\PharmScope;

class PharmReportController extends Controller
{
    use PharmScope;

    public function index(Request $request)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        $search = trim((string) $request->query('search', ''));
        $type = trim((string) $request->query('type', ''));
        $status = trim((string) $request->query('status', ''));
        $deptFilter = (int) $request->query('dept', 0);

        $reports = PharmReport::query()
            ->with(['department', 'parentDepartment'])
            ->when($deptIds !== null, fn ($q) => $q->where(fn ($qq) => $qq->whereIn('department_id', $deptIds)->orWhereIn('parent_department_id', $deptIds)))
            ->when($deptFilter > 0, fn ($q) => $q->where('department_id', $deptFilter))
            ->when($type !== '', fn ($q) => $q->where('report_type', $type))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('reference_no', 'like', "%{$search}%")
                        ->orWhere('period_label', 'like', "%{$search}%")
                        ->orWhereHas('department', fn ($d) => $d->where('department_name', 'like', "%{$search}%"));
                });
            })
            ->latest('period_start')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $facilityTypes = array_keys(\Modules\HumanResource\Support\OrgScopeService::LEVEL_MAP);
        $departments = Department::withoutGlobalScopes()
            ->where('is_active', 1)
            ->whereIn('unit_type_id', $facilityTypes)
            ->when($deptIds !== null, fn ($q) => $q->whereIn('id', $deptIds))
            ->orderBy('department_name')
            ->get(['id', 'department_name']);

        $level = $this->pharmLevel();
        $canReview = $this->pharmCanReview();
        return view('pharmaceutical::reports.index', compact('reports', 'search', 'type', 'status', 'deptFilter', 'departments', 'level', 'canReview'));
    }

    public function create()
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        $facilityTypes = array_keys(\Modules\HumanResource\Support\OrgScopeService::LEVEL_MAP);
        $departments = Department::withoutGlobalScopes()
            ->where('is_active', 1)
            ->whereIn('unit_type_id', $facilityTypes)
            ->when($deptIds !== null, fn ($q) => $q->whereIn('id', $deptIds))
            ->orderBy('department_name')
            ->get(['id', 'department_name', 'parent_id', 'unit_type_id']);
        $medicines = PharmMedicine::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'name_kh', 'unit']);

        // Build parent map: for each department find parent facility
        $allDepts = Department::withoutGlobalScopes()
            ->where('is_active', 1)
            ->get(['id', 'department_name', 'parent_id', 'unit_type_id']);
        $deptMap = $allDepts->keyBy('id');
        $parentMap = [];
        foreach ($departments as $dept) {
            $parent = $this->findParentFacility($dept, $deptMap, $facilityTypes);
            if ($parent) {
                $parentMap[$dept->id] = ['id' => $parent->id, 'name' => $parent->department_name];
            }
        }

        return view('pharmaceutical::reports.create', compact('departments', 'medicines', 'parentMap'));
    }

    public function store(Request $request)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        if ($deptIds !== null) {
            abort_unless(in_array((int) $request->input('department_id'), $deptIds, true), 403);
        }

        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'parent_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'report_type' => ['required', Rule::in([PharmReport::TYPE_MONTHLY, PharmReport::TYPE_QUARTERLY, PharmReport::TYPE_ANNUAL, PharmReport::TYPE_ADHOC])],
            'period_label' => ['nullable', 'string', 'max:50'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:pharm_medicines,id'],
            'items.*.opening_stock' => ['required', 'numeric', 'min:0'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0'],
            'items.*.dispensed_qty' => ['required', 'numeric', 'min:0'],
            'items.*.adjustment_qty' => ['nullable', 'numeric'],
            'items.*.expired_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.damaged_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.closing_stock' => ['required', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $report = PharmReport::create([
            'reference_no' => $this->generateReferenceNo(),
            'department_id' => $validated['department_id'],
            'parent_department_id' => $validated['parent_department_id'] ?? null,
            'report_type' => $validated['report_type'],
            'period_label' => $validated['period_label'] ?? null,
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'status' => PharmReport::STATUS_DRAFT,
            'note' => $validated['note'] ?? null,
            'created_by' => Auth::id(),
        ]);

        foreach ($validated['items'] as $item) {
            PharmReportItem::create([
                'report_id' => $report->id,
                'medicine_id' => $item['medicine_id'],
                'opening_stock' => $item['opening_stock'],
                'received_qty' => $item['received_qty'],
                'dispensed_qty' => $item['dispensed_qty'],
                'adjustment_qty' => $item['adjustment_qty'] ?? 0,
                'expired_qty' => $item['expired_qty'] ?? 0,
                'damaged_qty' => $item['damaged_qty'] ?? 0,
                'closing_stock' => $item['closing_stock'],
                'note' => $item['note'] ?? null,
            ]);
        }

        return redirect()->route('pharmaceutical.reports.show', $report->id)
            ->with('success', localize('data_save', 'Saved successfully.'));
    }

    public function show(PharmReport $report)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        if ($deptIds !== null) {
            abort_unless(
                in_array((int) $report->department_id, $deptIds, true)
                || ($report->parent_department_id && in_array((int) $report->parent_department_id, $deptIds, true)),
                403
            );
        }

        $report->load(['department', 'parentDepartment', 'items.medicine', 'submitter', 'reviewer']);

        $canReview = $this->pharmCanReview();

        // Comparison with previous period
        $svc = new PharmReportService();
        $prevData = $svc->getPreviousPeriodData((int) $report->department_id, $report->period_start->toDateString());

        return view('pharmaceutical::reports.show', compact('report', 'canReview', 'prevData'));
    }

    public function submit(PharmReport $report)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        if ($deptIds !== null) {
            abort_unless(in_array((int) $report->department_id, $deptIds, true), 403);
        }

        if ($report->status !== PharmReport::STATUS_DRAFT) {
            return back()->with('error', localize('already_submitted', 'This report has already been submitted.'));
        }

        $report->update([
            'status' => PharmReport::STATUS_SUBMITTED,
            'submitted_by' => Auth::id(),
            'submitted_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('pharmaceutical.reports.show', $report->id)
            ->with('success', localize('report_submitted', 'Report submitted successfully.'));
    }

    public function review(Request $request, PharmReport $report)
    {
        abort_unless($this->pharmCanReview(), 403);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['review', 'approve'])],
            'reviewer_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $action = $validated['action'];
        $newStatus = $action === 'approve' ? PharmReport::STATUS_APPROVED : PharmReport::STATUS_REVIEWED;

        $report->update([
            'status' => $newStatus,
            'reviewer_note' => $validated['reviewer_note'] ?? null,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'updated_by' => Auth::id(),
        ]);

        return redirect()->route('pharmaceutical.reports.show', $report->id)
            ->with('success', localize('data_update', 'Updated successfully.'));
    }

    private function generateReferenceNo(): string
    {
        $prefix = 'RPT-' . now()->format('Ymd') . '-';
        $last = PharmReport::where('reference_no', 'like', $prefix . '%')->max('reference_no');
        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Walk up the parent chain to find the nearest facility-type ancestor.
     */
    private function findParentFacility(Department $dept, $deptMap, array $facilityTypes): ?Department
    {
        $current = $dept;
        $guard = 0;
        while ($current->parent_id && $guard < 10) {
            $parent = $deptMap[$current->parent_id] ?? null;
            if (!$parent) {
                break;
            }
            if (in_array((int) $parent->unit_type_id, $facilityTypes, true) && $parent->id !== $dept->id) {
                return $parent;
            }
            $current = $parent;
            $guard++;
        }
        return null;
    }

    public function edit(PharmReport $report)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        if ($deptIds !== null) {
            abort_unless(in_array((int) $report->department_id, $deptIds, true), 403);
        }
        abort_unless($report->status === PharmReport::STATUS_DRAFT, 403);

        $report->load('items');
        $facilityTypes = array_keys(\Modules\HumanResource\Support\OrgScopeService::LEVEL_MAP);
        $departments = Department::withoutGlobalScopes()
            ->where('is_active', 1)
            ->whereIn('unit_type_id', $facilityTypes)
            ->when($deptIds !== null, fn ($q) => $q->whereIn('id', $deptIds))
            ->orderBy('department_name')
            ->get(['id', 'department_name', 'parent_id', 'unit_type_id']);
        $medicines = PharmMedicine::where('is_active', true)->orderBy('name')->get(['id', 'code', 'name', 'name_kh', 'unit']);

        $allDepts = Department::withoutGlobalScopes()->where('is_active', 1)->get(['id', 'department_name', 'parent_id', 'unit_type_id']);
        $deptMap = $allDepts->keyBy('id');
        $parentMap = [];
        foreach ($departments as $dept) {
            $parent = $this->findParentFacility($dept, $deptMap, $facilityTypes);
            if ($parent) {
                $parentMap[$dept->id] = ['id' => $parent->id, 'name' => $parent->department_name];
            }
        }

        return view('pharmaceutical::reports.edit', compact('report', 'departments', 'medicines', 'parentMap'));
    }

    public function update(Request $request, PharmReport $report)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        if ($deptIds !== null) {
            abort_unless(in_array((int) $report->department_id, $deptIds, true), 403);
        }
        abort_unless($report->status === PharmReport::STATUS_DRAFT, 403);

        if ($deptIds !== null) {
            abort_unless(in_array((int) $request->input('department_id'), $deptIds, true), 403);
        }

        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'parent_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'report_type' => ['required', Rule::in([PharmReport::TYPE_MONTHLY, PharmReport::TYPE_QUARTERLY, PharmReport::TYPE_ANNUAL, PharmReport::TYPE_ADHOC])],
            'period_label' => ['nullable', 'string', 'max:50'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.medicine_id' => ['required', 'integer', 'exists:pharm_medicines,id'],
            'items.*.opening_stock' => ['required', 'numeric', 'min:0'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0'],
            'items.*.dispensed_qty' => ['required', 'numeric', 'min:0'],
            'items.*.adjustment_qty' => ['nullable', 'numeric'],
            'items.*.expired_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.damaged_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.closing_stock' => ['required', 'numeric', 'min:0'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $report->update([
            'department_id' => $validated['department_id'],
            'parent_department_id' => $validated['parent_department_id'] ?? null,
            'report_type' => $validated['report_type'],
            'period_label' => $validated['period_label'] ?? null,
            'period_start' => $validated['period_start'],
            'period_end' => $validated['period_end'],
            'note' => $validated['note'] ?? null,
            'updated_by' => Auth::id(),
        ]);

        // Replace items
        $report->items()->delete();
        foreach ($validated['items'] as $item) {
            PharmReportItem::create([
                'report_id' => $report->id,
                'medicine_id' => $item['medicine_id'],
                'opening_stock' => $item['opening_stock'],
                'received_qty' => $item['received_qty'],
                'dispensed_qty' => $item['dispensed_qty'],
                'adjustment_qty' => $item['adjustment_qty'] ?? 0,
                'expired_qty' => $item['expired_qty'] ?? 0,
                'damaged_qty' => $item['damaged_qty'] ?? 0,
                'closing_stock' => $item['closing_stock'],
                'note' => $item['note'] ?? null,
            ]);
        }

        return redirect()->route('pharmaceutical.reports.show', $report->id)
            ->with('success', localize('data_update', 'Updated successfully.'));
    }

    public function destroy(PharmReport $report)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        if ($deptIds !== null) {
            abort_unless(in_array((int) $report->department_id, $deptIds, true), 403);
        }
        abort_unless($report->status === PharmReport::STATUS_DRAFT, 403);

        $report->items()->delete();
        $report->delete();

        return redirect()->route('pharmaceutical.reports.index')
            ->with('success', localize('data_delete', 'Deleted successfully.'));
    }

    public function print(PharmReport $report)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        if ($deptIds !== null) {
            abort_unless(
                in_array((int) $report->department_id, $deptIds, true)
                || ($report->parent_department_id && in_array((int) $report->parent_department_id, $deptIds, true)),
                403
            );
        }

        $report->load(['department', 'parentDepartment', 'items.medicine', 'submitter', 'reviewer']);

        $svc = new PharmReportService();
        $prevData = $svc->getPreviousPeriodData((int) $report->department_id, $report->period_start->toDateString());

        return view('pharmaceutical::reports.print', compact('report', 'prevData'));
    }

    /**
     * AJAX: auto-generate report data from actual transactions.
     */
    public function generateData(Request $request)
    {
        $deptIds = $this->pharmAccessibleDepartmentIds();
        $deptId = (int) $request->input('department_id');

        if ($deptIds !== null) {
            abort_unless(in_array($deptId, $deptIds, true), 403);
        }

        $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
        ]);

        $svc = new PharmReportService();
        $items = $svc->generateReportData($deptId, $request->input('period_start'), $request->input('period_end'));

        // Attach medicine info
        $medIds = array_keys($items);
        $medicines = PharmMedicine::whereIn('id', $medIds)->get(['id', 'code', 'name', 'name_kh', 'unit'])->keyBy('id');

        $result = [];
        foreach ($items as $medId => $row) {
            $med = $medicines[$medId] ?? null;
            $row['medicine_code'] = $med?->code;
            $row['medicine_name'] = $med?->name;
            $row['medicine_name_kh'] = $med?->name_kh;
            $row['medicine_unit'] = $med?->unit;
            $result[] = $row;
        }

        return response()->json(['success' => true, 'items' => $result]);
    }
}
