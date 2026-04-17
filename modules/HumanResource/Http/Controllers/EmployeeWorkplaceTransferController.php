<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeeStatus;
use Modules\HumanResource\Entities\EmployeeUnitPosting;
use Modules\HumanResource\Entities\EmployeeWorkHistory;
use Modules\HumanResource\Support\EmployeeServiceHistoryService;
use Modules\HumanResource\Support\EmployeeStatusTransitionService;
use Modules\HumanResource\Support\OrgUnitRuleService;

class EmployeeWorkplaceTransferController extends Controller
{
    public function __construct()
    {
        $this->middleware(['permission:read_employee'])->only('index');
        $this->middleware(['permission:update_employee'])->only('store');
    }

    public function index(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $year = (int) $request->query('year', now()->year);
        if ($year < 1950 || $year > 2100) {
            $year = (int) now()->year;
        }

        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);

        $employees = Employee::query()
            ->where('is_active', true)
            ->with(['department', 'sub_department', 'position'])
            ->orderBy('last_name')
            ->orderBy('first_name');
        $this->applyManagedBranchScope($employees, $managedBranchIds);
        $employees = $employees->get();

        $orgUnitOptions = $orgUnitRuleService->hierarchyOptions();
        $orgUnitTree = $orgUnitRuleService->hierarchyTree();

        if (is_array($managedBranchIds)) {
            if (empty($managedBranchIds)) {
                $orgUnitOptions = collect();
                $orgUnitTree = [];
            } else {
                $orgUnitOptions = $orgUnitOptions
                    ->filter(function ($unit) use ($managedBranchIds) {
                        return in_array((int) $unit->id, $managedBranchIds, true);
                    })
                    ->values();
                $orgUnitTree = $this->filterHierarchyTreeByAllowedIds($orgUnitTree, $managedBranchIds);
            }
        }

        $transfers = EmployeeUnitPosting::query()
            ->with([
                'employee.department',
                'employee.sub_department',
                'department',
                'position',
            ])
            ->whereYear('start_date', $year)
            ->where('note', 'like', '[WORKPLACE_TRANSFER]%')
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if (is_array($managedBranchIds)) {
            if (empty($managedBranchIds)) {
                $transfers->whereRaw('1=0');
            } else {
                $transfers->whereIn('department_id', $managedBranchIds);
            }
        }

        $transfers = $transfers->get();

        return view('humanresource::employee.workplace-transfer.index', [
            'year' => $year,
            'employees' => $employees,
            'org_unit_options' => $orgUnitOptions,
            'org_unit_tree' => $orgUnitTree,
            'current_unit_labels' => $this->currentUnitLabels($employees),
            'previous_unit_labels' => $this->previousUnitLabelMap($transfers),
            'transfer_documents' => $this->transferDocumentMap($transfers, $year),
            'transfers' => $transfers,
        ]);
    }

    public function store(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'department_id' => 'required|exists:departments,id',
            'effective_date' => 'required|date',
            'document_reference' => 'nullable|string|max:191',
            'document_date' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        $employee = Employee::query()->where('id', (int) $validated['employee_id'])->firstOrFail();
        $this->assertCanManageEmployee($employee, $orgUnitRuleService);

        $targetDepartment = Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('id', (int) $validated['department_id'])
            ->firstOrFail();
        $this->assertCanManageTargetDepartment((int) $targetDepartment->id, $orgUnitRuleService);

        $effectiveDate = Carbon::parse($validated['effective_date'])->toDateString();
        $documentDate = !empty($validated['document_date'])
            ? Carbon::parse($validated['document_date'])->toDateString()
            : null;

        $activePrimaryPosting = EmployeeUnitPosting::query()
            ->where('employee_id', $employee->id)
            ->where('is_primary', true)
            ->whereNull('end_date')
            ->latest('id')
            ->first();

        $currentDepartmentId = (int) ($employee->sub_department_id ?: $employee->department_id);
        if ($currentDepartmentId <= 0 && $activePrimaryPosting) {
            $currentDepartmentId = (int) $activePrimaryPosting->department_id;
        }

        if ($currentDepartmentId <= 0) {
            return redirect()->back()
                ->withErrors(['employee_id' => 'មិនរកឃើញអង្គភាពបច្ចុប្បន្នរបស់មន្ត្រី។'])
                ->withInput();
        }

        if ($currentDepartmentId === (int) $targetDepartment->id) {
            Toastr::info('អង្គភាពថ្មីដូចអង្គភាពបច្ចុប្បន្ន (មិនមានការកែប្រែ)។', 'ព័ត៌មាន');
            return redirect()->route('employee-workplace-transfers.index', [
                'year' => Carbon::parse($effectiveDate)->year,
            ]);
        }

        $currentDepartment = Department::withoutGlobalScopes()->find($currentDepartmentId);
        $currentUnitLabel = trim((string) ($currentDepartment?->department_name ?: '-'));
        $targetUnitLabel = trim((string) ($targetDepartment->department_name ?: '-'));

        DB::beginTransaction();
        try {
            if ($activePrimaryPosting) {
                $activePrimaryPosting->is_primary = false;
                if (!$activePrimaryPosting->end_date || Carbon::parse($activePrimaryPosting->end_date)->gte($effectiveDate)) {
                    $candidateEndDate = Carbon::parse($effectiveDate)->subDay()->toDateString();
                    $startDate = optional($activePrimaryPosting->start_date)->toDateString();
                    $activePrimaryPosting->end_date = ($startDate && $candidateEndDate < $startDate)
                        ? $effectiveDate
                        : $candidateEndDate;
                }
                $activePrimaryPosting->save();
            }

            $normalizedNote = trim((string) ($validated['note'] ?? ''));
            $postingNoteParts = [
                '[WORKPLACE_TRANSFER]',
                'From: ' . $currentUnitLabel,
                'To: ' . $targetUnitLabel,
            ];
            if (!empty($validated['document_reference'])) {
                $postingNoteParts[] = 'Doc: ' . $validated['document_reference'];
            }
            if (!empty($documentDate)) {
                $postingNoteParts[] = 'DocDate: ' . $documentDate;
            }
            if ($normalizedNote !== '') {
                $postingNoteParts[] = $normalizedNote;
            }

            $newPosting = EmployeeUnitPosting::create([
                'employee_id' => $employee->id,
                'department_id' => (int) $targetDepartment->id,
                'position_id' => $employee->position_id ?: optional($activePrimaryPosting)->position_id,
                'start_date' => $effectiveDate,
                'end_date' => null,
                'is_primary' => true,
                'note' => implode(' | ', $postingNoteParts),
            ]);

            $employee->department_id = (int) $targetDepartment->id;
            $employee->sub_department_id = (int) $targetDepartment->id;
            $employee->save();

            $workHistoryNoteParts = [
                'អង្គភាពចាស់: ' . $currentUnitLabel,
                'អង្គភាពថ្មី: ' . $targetUnitLabel,
            ];
            if ($normalizedNote !== '') {
                $workHistoryNoteParts[] = $normalizedNote;
            }

            EmployeeWorkHistory::create([
                'employee_id' => $employee->id,
                'work_status_name' => 'ផ្លាស់ប្តូរកន្លែងការងារ',
                'start_date' => $effectiveDate,
                'document_reference' => $validated['document_reference'] ?: null,
                'document_date' => $documentDate,
                'note' => implode(' | ', $workHistoryNoteParts),
            ]);

            // Transfer remains an event in history; keep current status as active in service.
            $this->statusTransitionService()->apply($employee, [
                'to_work_status_name' => $this->defaultActiveWorkStatusName(),
                'effective_date' => $effectiveDate,
                'document_reference' => $validated['document_reference'] ?: null,
                'document_date' => $documentDate,
                'note' => 'Auto-set active after workplace transfer',
                'transition_type' => 'transfer_in',
                'transition_source' => 'employee_workplace_transfer',
                'metadata' => [
                    'from_department_id' => $currentDepartmentId,
                    'to_department_id' => (int) $targetDepartment->id,
                    'from_department_name' => $currentUnitLabel,
                    'to_department_name' => $targetUnitLabel,
                ],
            ]);

            $this->historyService()->log(
                $employee->id,
                'transfer',
                'Workplace transferred',
                "Transferred workplace from {$currentUnitLabel} to {$targetUnitLabel}",
                $effectiveDate,
                $currentUnitLabel,
                $targetUnitLabel,
                'employee_unit_posting',
                $newPosting->id,
                [
                    'document_reference' => $validated['document_reference'] ?: null,
                    'document_date' => $documentDate,
                ]
            );

            DB::commit();
            Toastr::success('បានផ្លាស់ប្តូរកន្លែងការងារ និងកត់ត្រាប្រវត្តិការងាររួចរាល់។', 'ជោគជ័យ');

            return redirect()->route('employee-workplace-transfers.index', [
                'year' => Carbon::parse($effectiveDate)->year,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            activity()
                ->causedBy(auth()->user())
                ->log('An error occurred: ' . $e->getMessage());

            Toastr::error('រក្សាទុកការផ្លាស់ប្តូរកន្លែងការងារមិនបានជោគជ័យ។', 'បរាជ័យ');
            return redirect()->back()->withInput();
        }
    }

    protected function currentUnitLabels($employees): array
    {
        $labels = [];
        foreach ($employees as $employee) {
            $labels[(int) $employee->id] = $employee->sub_department?->department_name
                ?: ($employee->department?->department_name ?: '-');
        }
        return $labels;
    }

    protected function previousUnitLabelMap($transfers): array
    {
        $result = [];
        $employeeIds = collect($transfers)->pluck('employee_id')->map(function ($id) {
            return (int) $id;
        })->unique()->values()->all();

        if (empty($employeeIds)) {
            return $result;
        }

        $postingsByEmployee = EmployeeUnitPosting::query()
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->groupBy('employee_id');

        foreach ($transfers as $transfer) {
            $rows = collect($postingsByEmployee->get((int) $transfer->employee_id, []))->values();
            $index = $rows->search(function ($row) use ($transfer) {
                return (int) $row->id === (int) $transfer->id;
            });

            if ($index === false || $index === 0) {
                $result[(int) $transfer->id] = '-';
                continue;
            }

            $previous = $rows->get($index - 1);
            $result[(int) $transfer->id] = $this->unitNameById((int) ($previous->department_id ?? 0)) ?: '-';
        }

        return $result;
    }

    protected function transferDocumentMap($transfers, int $year): array
    {
        $result = [];
        $employeeIds = collect($transfers)->pluck('employee_id')->map(function ($id) {
            return (int) $id;
        })->unique()->values()->all();

        if (empty($employeeIds)) {
            return $result;
        }

        $workHistoryByEmployeeDate = EmployeeWorkHistory::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('work_status_name', 'ផ្លាស់ប្តូរកន្លែងការងារ')
            ->whereYear('start_date', $year)
            ->orderByDesc('id')
            ->get()
            ->groupBy(function ($row) {
                return (int) $row->employee_id . '|' . (string) optional($row->start_date)->format('Y-m-d');
            });

        foreach ($transfers as $transfer) {
            $key = (int) $transfer->employee_id . '|' . (string) optional($transfer->start_date)->format('Y-m-d');
            $history = collect($workHistoryByEmployeeDate->get($key, []))->first();
            $result[(int) $transfer->id] = [
                'document_reference' => $history?->document_reference ?: null,
                'document_date' => optional($history?->document_date)->format('Y-m-d') ?: null,
            ];
        }

        return $result;
    }

    protected function unitNameById(?int $id): ?string
    {
        if (!$id) {
            return null;
        }

        return Department::withoutGlobalScopes()->find($id)?->department_name;
    }

    protected function historyService(): EmployeeServiceHistoryService
    {
        return app(EmployeeServiceHistoryService::class);
    }

    protected function statusTransitionService(): EmployeeStatusTransitionService
    {
        return app(EmployeeStatusTransitionService::class);
    }

    protected function defaultActiveWorkStatusName(): string
    {
        $status = EmployeeStatus::query()
            ->where('is_active', true)
            ->where('transition_group', 'active')
            ->orderByRaw("CASE WHEN LOWER(COALESCE(code, '')) IN ('active','in_service','working') THEN 0 ELSE 1 END ASC")
            ->orderByRaw("CASE WHEN COALESCE(name_km, '') LIKE '%កំពុងបម្រើការងារ%' THEN 0 ELSE 1 END ASC")
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if (!$status) {
            return 'កំពុងបម្រើការងារ';
        }

        $nameKm = trim((string) ($status->name_km ?? ''));
        $nameEn = trim((string) ($status->name_en ?? ''));

        return $nameKm !== '' ? $nameKm : ($nameEn !== '' ? $nameEn : 'កំពុងបម្រើការងារ');
    }

    protected function managedBranchIds(OrgUnitRuleService $orgUnitRuleService): ?array
    {
        if ($this->isSystemAdmin()) {
            return null;
        }

        $rootUnitId = $this->currentUserRootUnitId();
        if (!$rootUnitId) {
            return [];
        }

        return $orgUnitRuleService->branchIdsIncludingSelf($rootUnitId);
    }

    protected function applyManagedBranchScope($query, ?array $managedBranchIds): void
    {
        if (!is_array($managedBranchIds)) {
            return;
        }

        if (empty($managedBranchIds)) {
            $query->whereRaw('1=0');
            return;
        }

        $query->where(function ($q) use ($managedBranchIds) {
            $q->whereIn('department_id', $managedBranchIds)
                ->orWhereIn('sub_department_id', $managedBranchIds);
        });
    }

    protected function assertCanManageEmployee(Employee $employee, OrgUnitRuleService $orgUnitRuleService): void
    {
        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);
        if (!is_array($managedBranchIds)) {
            return;
        }

        $employeeUnitId = $this->employeeAssignedUnitId($employee);
        if (!$employeeUnitId || !in_array($employeeUnitId, $managedBranchIds, true)) {
            abort(403, 'អ្នកអាចគ្រប់គ្រងបានតែមន្ត្រីក្នុងអង្គភាពរបស់ខ្លួនប៉ុណ្ណោះ។');
        }
    }

    protected function assertCanManageTargetDepartment(int $departmentId, OrgUnitRuleService $orgUnitRuleService): void
    {
        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);
        if (!is_array($managedBranchIds)) {
            return;
        }

        if (!in_array($departmentId, $managedBranchIds, true)) {
            abort(403, 'អ្នកអាចផ្លាស់ប្តូរបានតែទៅអង្គភាពក្នុងសាខាដែលអ្នកគ្រប់គ្រងប៉ុណ្ណោះ។');
        }
    }

    protected function isSystemAdmin(): bool
    {
        $user = auth()->user();
        return $user && (int) $user->user_type_id === 1;
    }

    protected function currentUserRootUnitId(): ?int
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        $employee = $user->employee()->with('primaryUnitPosting')->first();
        if (!$employee) {
            return null;
        }

        $rootUnitId = $this->employeeAssignedUnitId($employee);
        if ($rootUnitId) {
            return $rootUnitId;
        }

        $postedUnitId = (int) optional($employee->primaryUnitPosting)->department_id;
        return $postedUnitId > 0 ? $postedUnitId : null;
    }

    protected function employeeAssignedUnitId(Employee $employee): ?int
    {
        $unitId = (int) ($employee->sub_department_id ?: $employee->department_id);
        if ($unitId > 0) {
            return $unitId;
        }

        $postedUnitId = (int) optional($employee->primaryUnitPosting)->department_id;
        return $postedUnitId > 0 ? $postedUnitId : null;
    }

    protected function filterHierarchyTreeByAllowedIds(array $nodes, array $allowedIds): array
    {
        $allowedMap = [];
        foreach ($allowedIds as $id) {
            $allowedMap[(int) $id] = true;
        }

        return $this->filterHierarchyTreeNodes($nodes, $allowedMap);
    }

    protected function filterHierarchyTreeNodes(array $nodes, array $allowedMap): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $children = $this->filterHierarchyTreeNodes((array) ($node['children'] ?? []), $allowedMap);
            $nodeId = (int) ($node['id'] ?? 0);

            if (isset($allowedMap[$nodeId])) {
                $node['children'] = $children;
                $result[] = $node;
                continue;
            }

            if (!empty($children)) {
                foreach ($children as $childNode) {
                    $result[] = $childNode;
                }
            }
        }

        return $result;
    }
}
