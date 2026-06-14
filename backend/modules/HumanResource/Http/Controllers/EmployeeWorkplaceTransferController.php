<?php

namespace Modules\HumanResource\Http\Controllers;

use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeeServiceHistory;
use Modules\HumanResource\Entities\EmployeeStatus;
use Modules\HumanResource\Entities\EmployeeStatusTransition;
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
        $this->middleware(['isAdmin', 'permission:update_employee'])->only('update');
        $this->middleware(['isAdmin', 'permission:delete_employee'])->only('destroy');
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

        $transferOutTransitions = EmployeeStatusTransition::query()
            ->with(['employee.department', 'employee.sub_department', 'employee.position'])
            ->where('transition_type', 'transfer_out')
            ->where('transition_source', 'employee_workplace_transfer')
            ->whereYear('effective_date', $year)
            ->orderByDesc('effective_date')
            ->orderByDesc('id');

        if (is_array($managedBranchIds)) {
            if (empty($managedBranchIds)) {
                $transferOutTransitions->whereRaw('1=0');
            } else {
                $transferOutTransitions->whereHas('employee', function ($query) use ($managedBranchIds) {
                    $query->where(function ($q) use ($managedBranchIds) {
                        $q->whereIn('department_id', $managedBranchIds)
                            ->orWhereIn('sub_department_id', $managedBranchIds);
                    });
                });
            }
        }

        $transferOutTransitions = $transferOutTransitions->get();

        return view('humanresource::employee.workplace-transfer.index', [
            'year' => $year,
            'employees' => $employees,
            'org_unit_options' => $orgUnitOptions,
            'org_unit_tree' => $orgUnitTree,
            'current_unit_labels' => $this->currentUnitLabels($employees),
            'previous_unit_labels' => $this->previousUnitLabelMap($transfers),
            'transfer_documents' => $this->transferDocumentMap($transfers, $year),
            'transfers' => $transfers,
            'transfer_rows' => $this->buildTransferRows($transfers, $transferOutTransitions, $year),
            'transfer_out_statuses' => $this->transferOutStatuses(),
        ]);
    }

    public function store(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'transfer_scope' => 'required|in:internal,external',
            'department_id' => 'nullable|exists:departments,id',
            'out_status_id' => 'nullable|exists:employee_statuses,id',
            'target_location' => 'nullable|string|max:191',
            'effective_date' => 'required|date',
            'document_reference' => 'nullable|string|max:191',
            'document_date' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        $employee = Employee::query()->where('id', (int) $validated['employee_id'])->firstOrFail();
        $this->assertCanManageEmployee($employee, $orgUnitRuleService);

        $transferScope = (string) $validated['transfer_scope'];
        $targetDepartment = null;
        $selectedTransferOutStatus = null;

        if ($transferScope === 'internal') {
            if (empty($validated['department_id'])) {
                return redirect()->back()->withErrors([
                    'department_id' => 'Please select the target department.',
                ])->withInput();
            }

            $targetDepartment = Department::withoutGlobalScopes()
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('id', (int) $validated['department_id'])
                ->firstOrFail();
            $this->assertCanManageTargetDepartment((int) $targetDepartment->id, $orgUnitRuleService);
        } else {
            if (empty($validated['out_status_id'])) {
                return redirect()->back()->withErrors([
                    'out_status_id' => 'Please select the transfer-out status.',
                ])->withInput();
            }

            $selectedTransferOutStatus = $this->transferOutStatuses()->firstWhere('id', (int) $validated['out_status_id']);
            if (!$selectedTransferOutStatus) {
                return redirect()->back()->withErrors([
                    'out_status_id' => 'The selected transfer-out status is invalid.',
                ])->withInput();
            }
        }

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

        if ($transferScope === 'internal' && $currentDepartmentId <= 0) {
            return redirect()->back()
                ->withErrors(['employee_id' => 'មិនរកឃើញអង្គភាពបច្ចុប្បន្នរបស់មន្ត្រី។'])
                ->withInput();
        }

        if ($transferScope === 'internal' && $currentDepartmentId === (int) $targetDepartment->id) {
            Toastr::info('អង្គភាពថ្មីដូចអង្គភាពបច្ចុប្បន្ន (មិនមានការកែប្រែ)។', 'ព័ត៌មាន');
            return redirect()->route('employee-workplace-transfers.index', [
                'year' => Carbon::parse($effectiveDate)->year,
            ]);
        }

        $currentDepartment = Department::withoutGlobalScopes()->find($currentDepartmentId);
        $currentUnitLabel = trim((string) ($currentDepartment?->department_name ?: '-'));
        $targetUnitLabel = trim((string) ($targetDepartment?->department_name ?: ($validated['target_location'] ?? '-')));

        DB::beginTransaction();
        try {
            $normalizedNote = trim((string) ($validated['note'] ?? ''));

            if ($transferScope === 'external') {
                $targetLocation = trim((string) ($validated['target_location'] ?? ''));
                $statusLabel = $selectedTransferOutStatus?->display_name ?: trim((string) ($selectedTransferOutStatus?->name_en ?? ''));

                EmployeeWorkHistory::create([
                    'employee_id' => $employee->id,
                    'work_status_name' => $statusLabel,
                    'start_date' => $effectiveDate,
                    'document_reference' => $validated['document_reference'] ?: null,
                    'document_date' => $documentDate,
                    'note' => $this->buildTransferOutNote(
                        $currentUnitLabel,
                        $targetLocation !== '' ? $targetLocation : '-',
                        $statusLabel,
                        $normalizedNote
                    ),
                ]);

                $transition = $this->statusTransitionService()->apply($employee, [
                    'to_work_status_name' => $statusLabel,
                    'effective_date' => $effectiveDate,
                    'document_reference' => $validated['document_reference'] ?: null,
                    'document_date' => $documentDate,
                    'note' => 'Transfer out from organization',
                    'transition_type' => 'transfer_out',
                    'transition_source' => 'employee_workplace_transfer',
                    'metadata' => [
                        'from_department_id' => $currentDepartmentId > 0 ? $currentDepartmentId : null,
                        'from_department_name' => $currentUnitLabel,
                        'target_location' => $targetLocation !== '' ? $targetLocation : null,
                        'transfer_scope' => 'external',
                    ],
                    'skip_work_history' => true,
                    'force_service_state' => 'inactive',
                    'force_is_active' => false,
                    'force_is_left' => false,
                ]);

                $this->historyService()->log(
                    $employee->id,
                    'transfer_out',
                    'Transferred out',
                    "Transferred out from {$currentUnitLabel} to {$targetUnitLabel}",
                    $effectiveDate,
                    $currentUnitLabel,
                    $targetUnitLabel,
                    'employee_status_transition',
                    (int) optional($transition)->id,
                    [
                        'document_reference' => $validated['document_reference'] ?: null,
                        'document_date' => $documentDate,
                        'transfer_scope' => 'external',
                        'status_name' => $statusLabel,
                    ]
                );

                DB::commit();
                Toastr::success('Transfer-out has been recorded successfully.', 'Success');

                return redirect()->route('employee-workplace-transfers.index', [
                    'year' => Carbon::parse($effectiveDate)->year,
                ]);
            }

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

    public function update(
        Request $request,
        EmployeeUnitPosting $employee_unit_posting,
        OrgUnitRuleService $orgUnitRuleService
    ) {
        if (!$this->isWorkplaceTransferPosting($employee_unit_posting)) {
            abort(404);
        }

        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'effective_date' => 'required|date',
            'document_reference' => 'nullable|string|max:191',
            'document_date' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        $employee = Employee::query()
            ->with('primaryUnitPosting')
            ->where('id', (int) $employee_unit_posting->employee_id)
            ->firstOrFail();
        $this->assertCanManageEmployee($employee, $orgUnitRuleService);

        $targetDepartment = Department::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->where('id', (int) $validated['department_id'])
            ->firstOrFail();
        $this->assertCanManageTargetDepartment((int) $targetDepartment->id, $orgUnitRuleService);

        $meta = $this->extractTaggedNoteValues((string) $employee_unit_posting->note, ['From', 'To']);
        $relatedTransferHistory = $this->findTransferWorkHistory($employee_unit_posting, $meta);
        $relatedActiveHistory = $this->findTransferActiveWorkHistory($employee_unit_posting);
        $relatedTransition = $this->findTransferStatusTransition($employee_unit_posting);
        $effectiveDate = Carbon::parse($validated['effective_date'])->toDateString();
        $documentDate = !empty($validated['document_date'])
            ? Carbon::parse($validated['document_date'])->toDateString()
            : null;
        $normalizedNote = trim((string) ($validated['note'] ?? ''));

        DB::beginTransaction();
        try {
            $employee_unit_posting->department_id = (int) $targetDepartment->id;
            $employee_unit_posting->start_date = $effectiveDate;
            $employee_unit_posting->save();

            $this->rebuildEmployeePostingTimeline($employee);
            $employee->refresh();
            $employee_unit_posting->refresh();

            $oldUnitLabel = $this->previousUnitLabelForPosting($employee_unit_posting) ?: ($meta['From'] ?? '-');
            $newUnitLabel = trim((string) ($targetDepartment->department_name ?: '-'));

            $employee_unit_posting->note = $this->buildTransferNote(
                $oldUnitLabel,
                $newUnitLabel,
                $validated['document_reference'] ?? null,
                $documentDate,
                $normalizedNote
            );
            $employee_unit_posting->save();

            $this->syncTransferWorkHistory(
                $employee,
                $employee_unit_posting,
                $relatedTransferHistory,
                $oldUnitLabel,
                $newUnitLabel,
                $validated['document_reference'] ?? null,
                $documentDate,
                $normalizedNote
            );
            $this->syncTransferActiveWorkHistory(
                $employee,
                $employee_unit_posting,
                $relatedActiveHistory,
                $validated['document_reference'] ?? null,
                $documentDate
            );
            $this->syncTransferStatusTransition(
                $employee,
                $employee_unit_posting,
                $relatedTransition,
                $validated['document_reference'] ?? null,
                $documentDate,
                $oldUnitLabel,
                $newUnitLabel
            );

            $this->syncPostingServiceHistory(
                $employee_unit_posting,
                $employee->id,
                'transfer',
                'Workplace transferred',
                "Transferred workplace from {$oldUnitLabel} to {$newUnitLabel}",
                $effectiveDate,
                $oldUnitLabel,
                $newUnitLabel,
                [
                    'document_reference' => $validated['document_reference'] ?? null,
                    'document_date' => $documentDate,
                ]
            );

            DB::commit();
            Toastr::success('Workplace transfer updated successfully.', 'Success');

            return redirect()->route('employee-workplace-transfers.index', [
                'year' => Carbon::parse($effectiveDate)->year,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            activity()->causedBy(auth()->user())->log('An error occurred: ' . $e->getMessage());
            Toastr::error('Failed to update workplace transfer.', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function destroy(
        EmployeeUnitPosting $employee_unit_posting,
        OrgUnitRuleService $orgUnitRuleService
    ) {
        if (!$this->isWorkplaceTransferPosting($employee_unit_posting)) {
            abort(404);
        }

        $employee = Employee::query()
            ->with('primaryUnitPosting')
            ->where('id', (int) $employee_unit_posting->employee_id)
            ->firstOrFail();
        $this->assertCanManageEmployee($employee, $orgUnitRuleService);

        $meta = $this->extractTaggedNoteValues((string) $employee_unit_posting->note, ['From', 'To']);
        $relatedTransferHistory = $this->findTransferWorkHistory($employee_unit_posting, $meta);
        $relatedActiveHistory = $this->findTransferActiveWorkHistory($employee_unit_posting);
        $relatedTransition = $this->findTransferStatusTransition($employee_unit_posting);
        $fallbackDepartmentId = $this->resolveDepartmentIdByLabel($meta['From'] ?? null);
        $year = optional($employee_unit_posting->start_date)->year ?: now()->year;

        DB::beginTransaction();
        try {
            if ($relatedTransferHistory) {
                $relatedTransferHistory->delete();
            }

            if ($relatedActiveHistory) {
                $relatedActiveHistory->delete();
            }

            if ($relatedTransition) {
                $this->deleteTransitionServiceHistory($relatedTransition);
                $relatedTransition->delete();
            }

            $this->deletePostingServiceHistory($employee_unit_posting);
            $employee_unit_posting->delete();

            $this->rebuildEmployeePostingTimeline($employee, $fallbackDepartmentId);
            $this->statusTransitionService()->syncFromLatestWorkHistory($employee, 'employee_workplace_transfer_delete');

            DB::commit();
            Toastr::success('Workplace transfer deleted successfully.', 'Success');

            return redirect()->route('employee-workplace-transfers.index', [
                'year' => $year,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            activity()->causedBy(auth()->user())->log('An error occurred: ' . $e->getMessage());
            Toastr::error('Failed to delete workplace transfer.', 'Error');
            return redirect()->back();
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

    protected function isWorkplaceTransferPosting(EmployeeUnitPosting $posting): bool
    {
        return str_starts_with(trim((string) $posting->note), '[WORKPLACE_TRANSFER]');
    }

    protected function buildTransferNote(
        string $fromUnitLabel,
        string $toUnitLabel,
        ?string $documentReference,
        ?string $documentDate,
        ?string $note
    ): string {
        $parts = [
            '[WORKPLACE_TRANSFER]',
            'From: ' . (trim($fromUnitLabel) !== '' ? trim($fromUnitLabel) : '-'),
            'To: ' . (trim($toUnitLabel) !== '' ? trim($toUnitLabel) : '-'),
        ];

        $documentReference = trim((string) $documentReference);
        if ($documentReference !== '') {
            $parts[] = 'Doc: ' . $documentReference;
        }

        $documentDate = trim((string) $documentDate);
        if ($documentDate !== '') {
            $parts[] = 'DocDate: ' . $documentDate;
        }

        $note = trim((string) $note);
        if ($note !== '') {
            $parts[] = $note;
        }

        return implode(' | ', $parts);
    }

    protected function extractTaggedNoteValues(string $note, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = null;
        }

        $segments = array_map('trim', explode('|', $note));
        foreach ($segments as $segment) {
            foreach ($keys as $key) {
                $prefix = $key . ':';
                if (!str_starts_with($segment, $prefix)) {
                    continue;
                }

                $result[$key] = trim(substr($segment, strlen($prefix)));
            }
        }

        return $result;
    }

    protected function findTransferWorkHistory(EmployeeUnitPosting $posting, array $meta = []): ?EmployeeWorkHistory
    {
        $query = EmployeeWorkHistory::query()
            ->where('employee_id', (int) $posting->employee_id)
            ->whereDate('start_date', optional($posting->start_date)->toDateString())
            ->where('work_status_name', 'ផ្លាស់ប្តូរកន្លែងការងារ')
            ->orderByDesc('id');

        $oldLabel = trim((string) ($meta['From'] ?? ''));
        $newLabel = trim((string) ($meta['To'] ?? ''));
        if ($oldLabel !== '') {
            $query->where('note', 'like', '%' . $oldLabel . '%');
        }
        if ($newLabel !== '') {
            $query->where('note', 'like', '%' . $newLabel . '%');
        }

        return $query->first();
    }

    protected function findTransferActiveWorkHistory(EmployeeUnitPosting $posting): ?EmployeeWorkHistory
    {
        return EmployeeWorkHistory::query()
            ->where('employee_id', (int) $posting->employee_id)
            ->whereDate('start_date', optional($posting->start_date)->toDateString())
            ->where('note', 'Auto-set active after workplace transfer')
            ->orderByDesc('id')
            ->first();
    }

    protected function findTransferStatusTransition(EmployeeUnitPosting $posting): ?EmployeeStatusTransition
    {
        return EmployeeStatusTransition::query()
            ->where('employee_id', (int) $posting->employee_id)
            ->where('transition_type', 'transfer_in')
            ->where('transition_source', 'employee_workplace_transfer')
            ->whereDate('effective_date', optional($posting->start_date)->toDateString())
            ->orderByDesc('id')
            ->first();
    }

    protected function syncTransferWorkHistory(
        Employee $employee,
        EmployeeUnitPosting $posting,
        ?EmployeeWorkHistory $history,
        string $oldUnitLabel,
        string $newUnitLabel,
        ?string $documentReference,
        ?string $documentDate,
        ?string $note
    ): void {
        $noteParts = [
            'អង្គភាពចាស់: ' . ($oldUnitLabel !== '' ? $oldUnitLabel : '-'),
            'អង្គភាពថ្មី: ' . ($newUnitLabel !== '' ? $newUnitLabel : '-'),
        ];

        $note = trim((string) $note);
        if ($note !== '') {
            $noteParts[] = $note;
        }

        if (!$history) {
            $history = new EmployeeWorkHistory();
            $history->employee_id = $employee->id;
            $history->work_status_name = 'ផ្លាស់ប្តូរកន្លែងការងារ';
        }

        $history->start_date = optional($posting->start_date)->toDateString();
        $history->document_reference = trim((string) $documentReference) !== '' ? trim((string) $documentReference) : null;
        $history->document_date = trim((string) $documentDate) !== '' ? trim((string) $documentDate) : null;
        $history->note = implode(' | ', $noteParts);
        $history->save();
    }

    protected function syncTransferActiveWorkHistory(
        Employee $employee,
        EmployeeUnitPosting $posting,
        ?EmployeeWorkHistory $history,
        ?string $documentReference,
        ?string $documentDate
    ): void {
        if (!$history) {
            $history = new EmployeeWorkHistory();
            $history->employee_id = $employee->id;
            $history->note = 'Auto-set active after workplace transfer';
        }

        $history->work_status_name = $this->defaultActiveWorkStatusName();
        $history->start_date = optional($posting->start_date)->toDateString();
        $history->document_reference = trim((string) $documentReference) !== '' ? trim((string) $documentReference) : null;
        $history->document_date = trim((string) $documentDate) !== '' ? trim((string) $documentDate) : null;
        $history->note = 'Auto-set active after workplace transfer';
        $history->save();
    }

    protected function syncTransferStatusTransition(
        Employee $employee,
        EmployeeUnitPosting $posting,
        ?EmployeeStatusTransition $transition,
        ?string $documentReference,
        ?string $documentDate,
        string $oldUnitLabel,
        string $newUnitLabel
    ): void {
        $metadata = [
            'from_department_id' => $this->resolveDepartmentIdByLabel($oldUnitLabel),
            'to_department_id' => (int) $posting->department_id,
            'from_department_name' => $oldUnitLabel,
            'to_department_name' => $newUnitLabel,
        ];

        if (!$transition) {
            $transition = EmployeeStatusTransition::create([
                'employee_id' => $employee->id,
                'transition_type' => 'transfer_in',
                'transition_source' => 'employee_workplace_transfer',
                'from_work_status_name' => $employee->work_status_name,
                'to_work_status_name' => $this->defaultActiveWorkStatusName(),
                'from_service_state' => $employee->service_state,
                'to_service_state' => 'active',
                'from_is_active' => (bool) $employee->is_active,
                'to_is_active' => true,
                'from_is_left' => (bool) $employee->is_left,
                'to_is_left' => false,
                'effective_date' => optional($posting->start_date)->toDateString(),
                'document_date' => trim((string) $documentDate) !== '' ? trim((string) $documentDate) : null,
                'document_reference' => trim((string) $documentReference) !== '' ? trim((string) $documentReference) : null,
                'note' => 'Auto-set active after workplace transfer',
                'metadata' => $metadata,
            ]);
        } else {
            $transition->to_work_status_name = $this->defaultActiveWorkStatusName();
            $transition->to_service_state = 'active';
            $transition->to_is_active = true;
            $transition->to_is_left = false;
            $transition->effective_date = optional($posting->start_date)->toDateString();
            $transition->document_date = trim((string) $documentDate) !== '' ? trim((string) $documentDate) : null;
            $transition->document_reference = trim((string) $documentReference) !== '' ? trim((string) $documentReference) : null;
            $transition->note = 'Auto-set active after workplace transfer';
            $transition->metadata = $metadata;
            $transition->save();
        }

        $this->syncTransitionServiceHistory($transition);
    }

    protected function syncPostingServiceHistory(
        EmployeeUnitPosting $posting,
        int $employeeId,
        string $eventType,
        string $title,
        string $details,
        string $eventDate,
        string $fromValue,
        string $toValue,
        array $metadata = []
    ): void {
        $history = EmployeeServiceHistory::query()
            ->where('reference_type', 'employee_unit_posting')
            ->where('reference_id', (int) $posting->id)
            ->latest('id')
            ->first();

        if (!$history) {
            app(EmployeeServiceHistoryService::class)->log(
                $employeeId,
                $eventType,
                $title,
                $details,
                $eventDate,
                $fromValue,
                $toValue,
                'employee_unit_posting',
                (int) $posting->id,
                $metadata
            );

            return;
        }

        $history->employee_id = $employeeId;
        $history->event_type = $eventType;
        $history->event_date = $eventDate;
        $history->title = $title;
        $history->details = $details;
        $history->from_value = $fromValue;
        $history->to_value = $toValue;
        $history->metadata = $metadata;
        $history->save();
    }

    protected function deletePostingServiceHistory(EmployeeUnitPosting $posting): void
    {
        EmployeeServiceHistory::query()
            ->where('reference_type', 'employee_unit_posting')
            ->where('reference_id', (int) $posting->id)
            ->delete();
    }

    protected function syncTransitionServiceHistory(EmployeeStatusTransition $transition): void
    {
        $history = EmployeeServiceHistory::query()
            ->where('reference_type', 'employee_status_transition')
            ->where('reference_id', (int) $transition->id)
            ->latest('id')
            ->first();

        $details = 'Updated via employee status management model';
        if (!$history) {
            app(EmployeeServiceHistoryService::class)->log(
                (int) $transition->employee_id,
                'status_change',
                'Employee status changed',
                $details,
                optional($transition->effective_date)->toDateString() ?: now()->toDateString(),
                trim((string) ($transition->from_work_status_name ?: $transition->from_service_state)),
                trim((string) ($transition->to_work_status_name ?: $transition->to_service_state)),
                'employee_status_transition',
                (int) $transition->id,
                [
                    'transition_type' => $transition->transition_type,
                    'transition_source' => $transition->transition_source,
                    'metadata' => $transition->metadata,
                ]
            );
            return;
        }

        $history->event_date = optional($transition->effective_date)->toDateString() ?: now()->toDateString();
        $history->from_value = trim((string) ($transition->from_work_status_name ?: $transition->from_service_state));
        $history->to_value = trim((string) ($transition->to_work_status_name ?: $transition->to_service_state));
        $history->metadata = [
            'transition_type' => $transition->transition_type,
            'transition_source' => $transition->transition_source,
            'metadata' => $transition->metadata,
        ];
        $history->save();
    }

    protected function deleteTransitionServiceHistory(EmployeeStatusTransition $transition): void
    {
        EmployeeServiceHistory::query()
            ->where('reference_type', 'employee_status_transition')
            ->where('reference_id', (int) $transition->id)
            ->delete();
    }

    protected function rebuildEmployeePostingTimeline(Employee $employee, ?int $fallbackDepartmentId = null): void
    {
        $postings = EmployeeUnitPosting::query()
            ->where('employee_id', (int) $employee->id)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->values();

        $lastPosting = null;

        foreach ($postings as $index => $posting) {
            $nextPosting = $postings->get($index + 1);
            $newEndDate = null;

            if ($nextPosting) {
                $candidateEndDate = Carbon::parse($nextPosting->start_date)->subDay()->toDateString();
                $startDate = optional($posting->start_date)->toDateString();
                $newEndDate = ($startDate && $candidateEndDate < $startDate)
                    ? $startDate
                    : $candidateEndDate;
            }

            $shouldBePrimary = $index === ($postings->count() - 1);
            $dirty = false;

            if ((bool) $posting->is_primary !== $shouldBePrimary) {
                $posting->is_primary = $shouldBePrimary;
                $dirty = true;
            }

            $normalizedCurrentEnd = optional($posting->end_date)->toDateString();
            if ($normalizedCurrentEnd !== $newEndDate) {
                $posting->end_date = $newEndDate;
                $dirty = true;
            }

            if ($dirty) {
                $posting->save();
            }

            if ($shouldBePrimary) {
                $lastPosting = $posting;
            }
        }

        $employee->refresh();

        if ($lastPosting) {
            if ((int) ($lastPosting->department_id ?? 0) > 0) {
                $employee->department_id = (int) $lastPosting->department_id;
                $employee->sub_department_id = (int) $lastPosting->department_id;
            } elseif ($fallbackDepartmentId && (int) $fallbackDepartmentId > 0) {
                $employee->department_id = (int) $fallbackDepartmentId;
                $employee->sub_department_id = (int) $fallbackDepartmentId;
            }

            if ((int) ($lastPosting->position_id ?? 0) > 0) {
                $employee->position_id = (int) $lastPosting->position_id;
            }

            $employee->save();
            return;
        }

        if ($fallbackDepartmentId && (int) $fallbackDepartmentId > 0) {
            $employee->department_id = (int) $fallbackDepartmentId;
            $employee->sub_department_id = (int) $fallbackDepartmentId;
            $employee->save();
        }
    }

    protected function previousUnitLabelForPosting(EmployeeUnitPosting $posting): ?string
    {
        $rows = EmployeeUnitPosting::query()
            ->where('employee_id', (int) $posting->employee_id)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $rows->search(fn ($row) => (int) $row->id === (int) $posting->id);
        if ($index === false || $index === 0) {
            return null;
        }

        $previous = $rows->get($index - 1);
        return $this->unitNameById((int) ($previous->department_id ?? 0));
    }

    protected function resolveDepartmentIdByLabel(?string $label): ?int
    {
        $label = trim((string) $label);
        if ($label === '' || $label === '-') {
            return null;
        }

        $department = Department::withoutGlobalScopes()
            ->where('department_name', $label)
            ->orderByDesc('id')
            ->first();

        return $department ? (int) $department->id : null;
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

    protected function transferOutStatuses()
    {
        return EmployeeStatus::query()
            ->where('is_active', false)
            ->where(function ($query) {
                $query->where('name_km', 'like', 'ផ្ទេរបុគ្គលិកចេញ%')
                    ->orWhere('name_en', 'like', 'ផ្ទេរបុគ្គលិកចេញ%');
            })
            ->get();
    }

    protected function buildTransferRows($transfers, $transferOutTransitions, int $year)
    {
        $rows = collect();
        $transferDocuments = $this->transferDocumentMap($transfers, $year);
        $previousLabels = $this->previousUnitLabelMap($transfers);

        foreach ($transfers as $transfer) {
            $displayNote = trim(str_replace('[WORKPLACE_TRANSFER] |', '', (string) $transfer->note));
            $rows->push([
                'kind' => 'internal',
                'record_id' => (int) $transfer->id,
                'employee_label' => trim((string) (($transfer->employee?->employee_id ?: '-') . ' - ' . ($transfer->employee?->full_name ?? '-'))),
                'transfer_type' => 'ផ្ទេរក្នុងអង្គភាព/ខេត្ត',
                'from_unit' => $previousLabels[(int) $transfer->id] ?? '-',
                'to_unit' => $transfer->department?->department_name ?: '-',
                'effective_date' => optional($transfer->start_date)->toDateString(),
                'document_reference' => $transferDocuments[(int) $transfer->id]['document_reference'] ?? null,
                'document_date' => $transferDocuments[(int) $transfer->id]['document_date'] ?? null,
                'note' => $displayNote !== '' ? $displayNote : '-',
                'editable' => true,
                'source_model' => $transfer,
            ]);
        }

        foreach ($transferOutTransitions as $transition) {
            $metadata = is_array($transition->metadata) ? $transition->metadata : [];
            $rows->push([
                'kind' => 'external',
                'record_id' => (int) $transition->id,
                'employee_label' => trim((string) (($transition->employee?->employee_id ?: '-') . ' - ' . ($transition->employee?->full_name ?? '-'))),
                'transfer_type' => trim((string) ($transition->to_work_status_name ?: 'ផ្ទេរចេញ')),
                'from_unit' => trim((string) ($metadata['from_department_name'] ?? ($transition->employee?->sub_department?->department_name ?: ($transition->employee?->department?->department_name ?: '-')))),
                'to_unit' => trim((string) ($metadata['target_location'] ?? '-')),
                'effective_date' => optional($transition->effective_date)->toDateString(),
                'document_reference' => $transition->document_reference,
                'document_date' => optional($transition->document_date)->toDateString(),
                'note' => trim((string) ($transition->note ?: '-')),
                'editable' => false,
                'source_model' => $transition,
            ]);
        }

        return $rows
            ->sortByDesc(function ($row) {
                return ($row['effective_date'] ?? '') . '-' . str_pad((string) ($row['record_id'] ?? 0), 10, '0', STR_PAD_LEFT);
            })
            ->values();
    }

    protected function buildTransferOutNote(string $fromUnitLabel, string $targetLocation, string $statusLabel, ?string $note): string
    {
        $parts = [
            '[WORKPLACE_TRANSFER_OUT]',
            'From: ' . (trim($fromUnitLabel) !== '' ? trim($fromUnitLabel) : '-'),
            'To: ' . (trim($targetLocation) !== '' ? trim($targetLocation) : '-'),
            'Status: ' . (trim($statusLabel) !== '' ? trim($statusLabel) : '-'),
        ];

        $note = trim((string) $note);
        if ($note !== '') {
            $parts[] = $note;
        }

        return implode(' | ', $parts);
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
        if (!$user) {
            return false;
        }

        return method_exists($user, 'admin')
            ? (bool) $user->admin()
            : (int) ($user->user_type_id ?? 0) === 1;
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
