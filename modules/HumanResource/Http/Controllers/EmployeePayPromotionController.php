<?php

namespace Modules\HumanResource\Http\Controllers;

use App\Models\User;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\EmployeeType;
use Modules\HumanResource\Entities\EmployeePayGradeHistory;
use Modules\HumanResource\Entities\EmployeeStatusTransition;
use Modules\HumanResource\Entities\EmployeeWorkHistory;
use Modules\HumanResource\Entities\GovPayLevel;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Modules\HumanResource\Support\OrgUnitRuleService;

class EmployeePayPromotionController extends Controller
{
    protected const STATUS_ACTIVE = 'active';
    protected const STATUS_PROPOSED = 'proposed';
    protected const STATUS_RECOMMENDED = 'recommended';
    protected const STATUS_APPROVED = 'approved';
    protected const STATUS_REJECTED = 'rejected';
    protected const STATUS_CANCELLED = 'cancelled';

    /**
     * Employee type classification cache keyed by employee_type_id.
     *
     * @var array<int, string>|null
     */
    protected ?array $employeeTypeCategoryCache = null;

    public function __construct()
    {
        $this->middleware(['permission:read_employee'])->only(['index', 'review']);
        $this->middleware(['permission:update_employee'])->only(['store', 'batchAction']);
    }

    public function index(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $year = (int) $request->query('year', now()->year);
        if ($year < 1950 || $year > 2100) {
            $year = (int) now()->year;
        }
        $selectedUnitId = (int) $request->query('unit_id', 0);
        $selectedEmployeeTypeId = (int) $request->query('employee_type_id', 0);
        $selectedServiceState = trim((string) $request->query('service_state', ''));

        $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);
        $orgUnitTree = $orgUnitRuleService->hierarchyTree();

        $unitOptionsQuery = Department::query()->select(['id', 'department_name']);
        if (is_array($managedBranchIds)) {
            if (empty($managedBranchIds)) {
                $unitOptionsQuery->whereRaw('1=0');
                $orgUnitTree = [];
            } else {
                $unitOptionsQuery->whereIn('id', $managedBranchIds);
                $orgUnitTree = $this->filterHierarchyTreeByAllowedIds($orgUnitTree, $managedBranchIds);
            }
        }
        $unitOptions = $unitOptionsQuery
            ->orderBy('department_name')
            ->get();

        $employeeTypeOrderColumn = 'id';
        if (Schema::hasColumn('employee_types', 'employee_type_name')) {
            $employeeTypeOrderColumn = 'employee_type_name';
        } elseif (Schema::hasColumn('employee_types', 'name_km')) {
            $employeeTypeOrderColumn = 'name_km';
        } elseif (Schema::hasColumn('employee_types', 'name')) {
            $employeeTypeOrderColumn = 'name';
        }

        $employeeTypeOptions = EmployeeType::query()
            ->where('is_active', true)
            ->orderBy($employeeTypeOrderColumn)
            ->get();

        // For grade/rank workflow, prefer DB-backed core categories:
        // state cadre, contract, and agreement.
        $categoryOrder = ['state_cadre' => 1, 'contract' => 2, 'agreement' => 3];
        $typeCategoryMap = $this->employeeTypeCategoryMap();
        $coreTypeOptions = $employeeTypeOptions
            ->filter(function ($type) use ($typeCategoryMap, $categoryOrder) {
                $category = (string) ($typeCategoryMap[(int) ($type->id ?? 0)] ?? 'other');
                return isset($categoryOrder[$category]);
            })
            ->sortBy(function ($type) use ($typeCategoryMap, $categoryOrder) {
                $category = (string) ($typeCategoryMap[(int) ($type->id ?? 0)] ?? 'other');
                return $categoryOrder[$category] ?? 999;
            })
            ->values();

        if ($coreTypeOptions->isNotEmpty()) {
            $employeeTypeOptions = $coreTypeOptions;
        }

        $employees = Employee::query()
            ->where('is_active', true)
            ->with(['employee_type', 'gender', 'department.unitType', 'sub_department.unitType'])
            ->orderBy('last_name')
            ->orderBy('first_name');
        $this->applyManagedBranchScope($employees, $managedBranchIds);
        if ($selectedUnitId > 0) {
            $employees->where(function ($q) use ($selectedUnitId) {
                $q->where('department_id', $selectedUnitId)
                    ->orWhere('sub_department_id', $selectedUnitId);
            });
        }
        if ($selectedEmployeeTypeId > 0) {
            $employees->where('employee_type_id', $selectedEmployeeTypeId);
        }
        if ($selectedServiceState !== '') {
            $employees->where('service_state', $selectedServiceState);
        }
        $employees = $employees->get();

        $employeeUnitPaths = [];
        foreach ($employees as $employee) {
            $employeeUnitPaths[(int) $employee->id] = $this->resolveEmployeeDisplayUnitPath($employee);
        }

        $employeesForStaff = $employees
            ->sortBy(function (Employee $employee) use ($employeeUnitPaths): string {
                $path = (string) ($employeeUnitPaths[(int) $employee->id] ?? '-');
                $fullName = mb_strtolower((string) ($employee->full_name ?? ''), 'UTF-8');
                return $path . '|' . $fullName . '|' . str_pad((string) $employee->id, 10, '0', STR_PAD_LEFT);
            })
            ->values();

        $cutoffDate = Carbon::create($year, 4, 1)->endOfDay();
        $employeeSnapshots = $this->buildPromotionSnapshots($employees, $cutoffDate);
        $eligibleEmployeeIds = collect($employeeSnapshots)
            ->filter(fn ($snapshot) => (bool) ($snapshot['is_due_regular'] ?? false))
            ->keys()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $overdueReminders = collect($employeeSnapshots)
            ->filter(fn ($snapshot) => (bool) ($snapshot['is_overdue_3y'] ?? false))
            ->values();

        $promotions = EmployeePayGradeHistory::query()
            ->with([
                'employee.department',
                'employee.sub_department',
                'payLevel',
            ])
            ->whereYear('start_date', $year)
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if (is_array($managedBranchIds)) {
            $promotions->whereHas('employee', function ($q) use ($managedBranchIds) {
                $q->whereIn('department_id', $managedBranchIds)
                    ->orWhereIn('sub_department_id', $managedBranchIds);
            });
        }

        $pendingProposalsQuery = EmployeePayGradeHistory::query()
            ->with([
                'employee.department',
                'employee.sub_department',
                'payLevel',
            ])
            ->whereIn('status', $this->pendingPromotionStatuses())
            ->whereYear('start_date', $year)
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if (is_array($managedBranchIds)) {
            $pendingProposalsQuery->whereHas('employee', function ($q) use ($managedBranchIds) {
                $q->whereIn('department_id', $managedBranchIds)
                    ->orWhereIn('sub_department_id', $managedBranchIds);
            });
        }

        $pendingProposals = $pendingProposalsQuery
            ->get()
            ->sortByDesc(function ($row) {
                $startDate = (string) ($row->start_date ?? '');
                return ($startDate !== '' ? $startDate : '0000-00-00') . '|' . str_pad((string) ((int) ($row->id ?? 0)), 12, '0', STR_PAD_LEFT);
            })
            ->unique(fn ($row) => (int) ($row->employee_id ?? 0))
            ->values();
        $pendingProposalEmployeeIds = $pendingProposals
            ->pluck('employee_id')
            ->filter(static fn ($id) => !empty($id))
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        [$proposalActionPermissions, $proposalActionSummary] = $this->buildPendingProposalPermissionMap(
            $pendingProposals,
            $orgUnitRuleService
        );

        $payPromotionChart = $this->buildPayPromotionChartData(
            $year,
            $employees,
            $employeeSnapshots,
            $managedBranchIds
        );

        $promotionRows = $promotions->get();
        $promotionPreviousLevelLabels = $this->buildPromotionPreviousLevelLabels($promotionRows);

        $lastUpdatedAtQuery = EmployeePayGradeHistory::query();
        $this->applyManagedBranchScopeToPayHistoryQuery($lastUpdatedAtQuery, $managedBranchIds);
        $lastUpdatedAt = $lastUpdatedAtQuery->max('updated_at');

        $payLevels = GovPayLevel::query()->where('is_active', true)->get();
        $currentPayLevelState = $this->currentPayLevelState($employees, $payLevels);
        $currentPayLevelLabels = collect($currentPayLevelState)
            ->mapWithKeys(function (array $row, int $employeeId) {
                return [(int) $employeeId => (string) ($row['current_label'] ?? '-')];
            })
            ->all();

        $autoPromotionCandidates = $this->buildAutoPromotionCandidates(
            $employeesForStaff,
            $employeeSnapshots,
            $currentPayLevelState,
            $employeeUnitPaths,
            $pendingProposalEmployeeIds
        );

        return view('humanresource::employee.pay-promotion.index', [
            'year' => $year,
            'cutoff_date' => $cutoffDate->toDateString(),
            'employees' => $employees,
            'employees_for_staff' => $employeesForStaff,
            'employee_unit_paths' => $employeeUnitPaths,
            'current_pay_level_labels' => $currentPayLevelLabels,
            'current_pay_level_state' => $currentPayLevelState,
            'auto_promotion_candidates' => $autoPromotionCandidates,
            'pay_levels' => $payLevels,
            'promotions' => $promotionRows,
            'promotion_previous_level_labels' => $promotionPreviousLevelLabels,
            'employee_snapshots' => $employeeSnapshots,
            'eligible_employee_ids' => $eligibleEmployeeIds,
            'overdue_reminders' => $overdueReminders,
            'pending_proposals' => $pendingProposals,
            'pending_proposal_employee_ids' => $pendingProposalEmployeeIds,
            'proposal_action_permissions' => $proposalActionPermissions,
            'proposal_action_summary' => $proposalActionSummary,
            'pay_promotion_chart' => $payPromotionChart,
            'unit_options' => $unitOptions,
            'org_unit_tree' => $orgUnitTree,
            'employee_type_options' => $employeeTypeOptions,
            'selected_unit_id' => $selectedUnitId,
            'selected_employee_type_id' => $selectedEmployeeTypeId,
            'selected_service_state' => $selectedServiceState,
            'last_updated_at' => $lastUpdatedAt,
        ]);
    }

    public function review(Request $request, int $proposal, OrgUnitRuleService $orgUnitRuleService)
    {
        $pendingProposal = EmployeePayGradeHistory::query()
            ->with([
                'employee.department',
                'employee.sub_department',
                'employee.employee_type',
                'payLevel',
            ])
            ->whereKey($proposal)
            ->whereIn('status', $this->pendingPromotionStatuses())
            ->firstOrFail();

        $employee = $pendingProposal->employee;
        if (!$employee) {
            abort(404);
        }

        $this->assertCanManageEmployee($employee, $orgUnitRuleService);

        $year = (int) $request->query('year', Carbon::parse((string) ($pendingProposal->start_date ?? now()))->year);
        if ($year < 1950 || $year > 2100) {
            $year = (int) Carbon::parse((string) ($pendingProposal->start_date ?? now()))->year;
        }

        $payLevels = GovPayLevel::query()->where('is_active', true)->get();
        $currentState = $this->currentPayLevelState(collect([$employee]), $payLevels);
        $currentPayLevelLabel = (string) ($currentState[(int) $employee->id]['current_label'] ?? '-');

        $targetPayLevelLabel = '-';
        if ($pendingProposal->payLevel instanceof GovPayLevel) {
            $targetPayLevelLabel = $this->displayPayLevelLabel($pendingProposal->payLevel);
        }

        $normalizedPromotionType = $this->normalizePromotionType((string) ($pendingProposal->promotion_type ?? 'annual_grade'));
        $reviewStatus = (string) ($pendingProposal->status ?? self::STATUS_PROPOSED);
        $reviewCanRecommend = $this->proposalAllowsAction($pendingProposal, 'recommend')
            && $this->canRecommendEmployeeAction(
                auth()->user(),
                $employee,
                $orgUnitRuleService,
                $normalizedPromotionType,
                'recommend'
            );
        $reviewCanApprove = $this->proposalAllowsAction($pendingProposal, 'approve')
            && $this->canFinalApprovePromotionAction(
                auth()->user(),
                $employee,
                $normalizedPromotionType,
                $orgUnitRuleService
            );
        $reviewCanReject = $this->proposalAllowsAction($pendingProposal, 'reject')
            && $this->canRecommendEmployeeAction(
                auth()->user(),
                $employee,
                $orgUnitRuleService,
                $normalizedPromotionType,
                'reject'
            );

        return view('humanresource::employee.pay-promotion.review', [
            'year' => $year,
            'proposal' => $pendingProposal,
            'employee' => $employee,
            'pay_levels' => $payLevels,
            'current_pay_level_label' => $currentPayLevelLabel,
            'target_pay_level_label' => $targetPayLevelLabel,
            'normalized_promotion_type' => $normalizedPromotionType,
            'review_status' => $reviewStatus,
            'review_can_recommend' => $reviewCanRecommend,
            'review_can_approve' => $reviewCanApprove,
            'review_can_reject' => $reviewCanReject,
        ]);
    }

    protected function currentPayLevelState(Collection $employees, Collection $payLevels): array
    {
        $employeeIds = $employees->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($employeeIds)) {
            return [];
        }

        $historyByEmployee = EmployeePayGradeHistory::query()
            ->with('payLevel')
            ->whereIn('employee_id', $employeeIds)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('employee_id');

        $nextMap = $this->buildNextPayLevelMap($payLevels);

        $payLevelById = $payLevels->keyBy('id');
        $payLevelIdByCode = [];
        $payLevelIdByKhLabel = [];
        foreach ($payLevels as $payLevel) {
            $codeKey = strtoupper((string) preg_replace('/\s+/', '', (string) $payLevel->level_code));
            if ($codeKey !== '') {
                $payLevelIdByCode[$codeKey] = (int) $payLevel->id;
                $payLevelIdByCode[$this->normalizePayCodeToKhmer($codeKey)] = (int) $payLevel->id;
            }

            $khLabel = $this->sanitizePayLevelNameKm((string) $payLevel->level_name_km);
            if ($khLabel !== '') {
                $payLevelIdByKhLabel[$khLabel] = (int) $payLevel->id;
            }
        }

        $result = [];
        foreach ($employees as $employee) {
            $employeeId = (int) $employee->id;
            $rows = collect($historyByEmployee->get($employeeId, []))
                ->reject(function ($row) {
                    return in_array((string) ($row->status ?? ''), [self::STATUS_PROPOSED, self::STATUS_RECOMMENDED, self::STATUS_REJECTED, self::STATUS_CANCELLED], true);
                })
                ->values();

            $preferred = $rows->firstWhere('status', 'active') ?: $rows->first();
            $currentPayLevelId = null;
            $currentLabel = '-';

            if ($preferred && $preferred->payLevel) {
                $currentPayLevelId = (int) $preferred->payLevel->id;
                $currentLabel = $this->displayPayLevelLabel($preferred->payLevel);
            } else {
                $legacyRaw = $this->normalizeKhmerText((string) ($employee->employee_grade ?? ''));
                $legacyKey = strtoupper((string) preg_replace('/\s+/', '', $legacyRaw));
                if ($legacyKey !== '' && isset($payLevelIdByCode[$legacyKey])) {
                    $currentPayLevelId = (int) $payLevelIdByCode[$legacyKey];
                } elseif ($legacyRaw !== '' && isset($payLevelIdByKhLabel[$legacyRaw])) {
                    $currentPayLevelId = (int) $payLevelIdByKhLabel[$legacyRaw];
                }

                if ($currentPayLevelId && $payLevelById->has($currentPayLevelId)) {
                    $currentLabel = $this->displayPayLevelLabel($payLevelById->get($currentPayLevelId));
                } elseif ($legacyRaw !== '') {
                    $currentLabel = $this->resolveLegacyPayLevelLabel($legacyRaw);
                }
            }

            $nextPayLevelId = $currentPayLevelId ? ($nextMap[$currentPayLevelId] ?? null) : null;
            $nextLabel = '-';
            if (!empty($nextPayLevelId) && $payLevelById->has((int) $nextPayLevelId)) {
                $nextLabel = $this->displayPayLevelLabel($payLevelById->get((int) $nextPayLevelId));
            }

            $result[$employeeId] = [
                'current_id' => $currentPayLevelId ? (int) $currentPayLevelId : null,
                'current_label' => $currentLabel,
                'next_id' => $nextPayLevelId ? (int) $nextPayLevelId : null,
                'next_label' => $nextLabel,
            ];
        }

        return $result;
    }

    protected function buildNextPayLevelMap(Collection $payLevels): array
    {
        $map = [];
        $grouped = $payLevels->groupBy(function ($level) {
            return $this->payLevelFamilyFromDbRow(
                (string) ($level->level_code ?? ''),
                (string) ($level->level_name_km ?? '')
            );
        });

        foreach ($grouped as $levels) {
            $ordered = $levels
                ->sort(function ($a, $b) {
                    // DB-driven order: lower sort_order = higher level.
                    $sortCmp = ((int) ($a->sort_order ?? 0) <=> (int) ($b->sort_order ?? 0));
                    if ($sortCmp !== 0) {
                        return $sortCmp;
                    }

                    $budgetCmp = ((float) ($b->budget_amount ?? 0) <=> (float) ($a->budget_amount ?? 0));
                    if ($budgetCmp !== 0) {
                        return $budgetCmp;
                    }

                    return ((int) ($a->id ?? 0) <=> (int) ($b->id ?? 0));
                })
                ->values();

            $count = $ordered->count();
            for ($i = 0; $i < $count; $i++) {
                $current = $ordered[$i];
                // Promotion goes to previous item (closer to top 1.1 / 1).
                $next = $ordered[$i - 1] ?? null;
                $map[(int) $current->id] = $next ? (int) $next->id : null;
            }
        }

        return $map;
    }

    protected function buildAutoPromotionCandidates(
        Collection $employeesForStaff,
        array $employeeSnapshots,
        array $currentPayLevelState,
        array $employeeUnitPaths,
        array $pendingProposalEmployeeIds = []
    ): Collection {
        $pendingSet = array_flip(array_map(static fn ($id) => (int) $id, $pendingProposalEmployeeIds));
        $rows = [];
        foreach ($employeesForStaff as $employee) {
            $employeeId = (int) $employee->id;
            $snapshot = $employeeSnapshots[$employeeId] ?? [];

            if (isset($pendingSet[$employeeId])) {
                continue;
            }

            if (!(bool) ($snapshot['is_state_cadre'] ?? false)) {
                continue;
            }
            if (($snapshot['service_state'] ?? 'active') !== 'active') {
                continue;
            }

            $isHonorary = (bool) ($snapshot['is_due_honorary_pre_retirement'] ?? false);
            $isOverdue = (bool) ($snapshot['is_overdue_3y'] ?? false);
            $isDueRegular = (bool) ($snapshot['is_due_regular'] ?? false);
            if (!$isHonorary && !$isOverdue && !$isDueRegular) {
                continue;
            }

            $reasonCode = 'annual_grade';
            $reasonText = 'Due by annual cycle (2 years)';
            if ($isHonorary) {
                $reasonCode = 'honorary_pre_retirement';
                $reasonText = 'Honorary pre-retirement (within 1 year)';
            } elseif ($isOverdue) {
                $reasonCode = 'annual_grade';
                $reasonText = 'Overdue by annual cycle (over 3 years)';
            }

            $state = $currentPayLevelState[$employeeId] ?? [];
            $nextPayLevelId = (int) ($state['next_id'] ?? 0);

            $rows[] = [
                'employee_id' => $employeeId,
                'employee_code' => (string) ($employee->employee_id ?? ''),
                'full_name' => (string) ($employee->full_name ?? ''),
                'unit_path' => (string) ($employeeUnitPaths[$employeeId] ?? '-'),
                'reason_code' => $reasonCode,
                'reason_text' => $reasonText,
                'last_promotion_date' => (string) ($snapshot['last_promotion_date'] ?? ''),
                'countable_years' => (float) ($snapshot['countable_years'] ?? 0),
                'retirement_date' => (string) ($snapshot['retirement_date'] ?? ''),
                'days_to_retirement' => $snapshot['days_to_retirement'] ?? null,
                'current_pay_level_id' => (int) ($state['current_id'] ?? 0),
                'current_pay_level_label' => (string) ($state['current_label'] ?? '-'),
                'next_pay_level_id' => $nextPayLevelId > 0 ? $nextPayLevelId : null,
                'next_pay_level_label' => (string) ($state['next_label'] ?? '-'),
                'can_request' => $nextPayLevelId > 0,
            ];
        }

        return collect($rows)->values();
    }

    public function store(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $recordModeInput = trim((string) $request->input('record_mode', 'request'));
        if ($recordModeInput === 'request' && $request->filled('bulk_items')) {
            return $this->storeBulkPromotionRequests($request, $orgUnitRuleService);
        }

        $validated = $request->validate([
            'record_mode' => 'nullable|in:request,recommend,approve,reject',
            'year' => 'nullable|integer|min:1950|max:2100',
            'proposal_id' => 'nullable|integer|exists:employee_pay_grade_histories,id',
            'employee_id' => 'nullable|exists:employees,id',
            'employee_ids' => 'nullable|array',
            'employee_ids.*' => 'nullable|exists:employees,id',
            'pay_level_id' => 'required|exists:gov_pay_levels,id',
            'effective_date' => 'required|date',
            'promotion_type' => 'required|in:annual_grade,annual_rank,degree_based,honorary_pre_retirement,yearly_cycle,special_case,regular,special_request',
            'next_review_date' => 'nullable|date|after_or_equal:effective_date',
            'document_reference' => 'nullable|string|max:191',
            'document_date' => 'nullable|date',
            'request_reference' => 'nullable|string|max:191',
            'request_date' => 'nullable|date',
            'note' => 'nullable|string',
        ]);

        $employeeIds = collect($validated['employee_ids'] ?? [])
            ->filter(static fn ($id) => !empty($id))
            ->map(static fn ($id) => (int) $id);

        if (!empty($validated['employee_id'])) {
            $employeeIds->push((int) $validated['employee_id']);
        }

        $employeeIds = $employeeIds->unique()->values()->all();
        if (empty($employeeIds)) {
            return redirect()->back()
                ->withErrors(['employee_id' => localize('please_select_at_least_one_employee', 'Please select at least one employee.')])
                ->withInput();
        }

        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->with(['employee_type'])
            ->get()
            ->keyBy('id');

        foreach ($employeeIds as $employeeId) {
            $employee = $employees->get($employeeId);
            if ($employee) {
                $this->assertCanManageEmployee($employee, $orgUnitRuleService);
            }
        }

        $payLevel = GovPayLevel::query()
            ->where('id', (int) $validated['pay_level_id'])
            ->where('is_active', true)
            ->firstOrFail();

        $effectiveDate = Carbon::parse($validated['effective_date'])->toDateString();
        $promotionType = $this->normalizePromotionType((string) ($validated['promotion_type'] ?? 'annual_grade'));
        $isAnnualCycle = $this->isAnnualCyclePromotionType($promotionType);
        $isHonoraryPreRetirement = $this->isHonoraryPreRetirementPromotionType($promotionType);

        $nextReviewDate = !empty($validated['next_review_date'])
            ? Carbon::parse($validated['next_review_date'])->toDateString()
            : ($isAnnualCycle
                ? Carbon::parse($effectiveDate)->addYears(2)->toDateString()
                : null);

        $documentDate = !empty($validated['document_date'])
            ? Carbon::parse($validated['document_date'])->toDateString()
            : null;

        $requestDate = !empty($validated['request_date'])
            ? Carbon::parse($validated['request_date'])->toDateString()
            : null;

        $recordMode = $validated['record_mode'] ?? 'request';
        $proposalId = !empty($validated['proposal_id']) ? (int) $validated['proposal_id'] : null;
        $cutoffYear = (int) ($validated['year'] ?? Carbon::parse($effectiveDate)->year);
        $cutoffDate = Carbon::create($cutoffYear, 4, 1)->endOfDay();
        $employeeSnapshots = $this->buildPromotionSnapshots($employees->values(), $cutoffDate);
        $activePayLevels = GovPayLevel::query()->where('is_active', true)->get();
        $activePayLevelsById = $activePayLevels->keyBy('id');
        $currentPayLevelState = $this->currentPayLevelState($employees->values(), $activePayLevels);

        if (
            $recordMode === 'request'
            && !$isAnnualCycle
            && (
                empty(trim((string) ($validated['request_reference'] ?? '')))
                || empty($validated['request_date'])
            )
        ) {
            return redirect()->back()
                ->withErrors([
                    'request_reference' => localize(
                        'request_reference_required_for_non_annual',
                        'Request reference and request date are required for degree-based and honorary cases.'
                    ),
                ])
                ->withInput();
        }

        DB::beginTransaction();
        try {
            $updatedCount = 0;
            $skippedSameLevelCount = 0;
            $requestCount = 0;
            $rejectedCount = 0;
            $skippedNotCadreCount = 0;
            $skippedNotEligibleCount = 0;
            $skippedNoPendingRequestCount = 0;
            $skippedInvalidStageCount = 0;
            $skippedInvalidDirectionCount = 0;

            foreach ($employeeIds as $employeeId) {
                $employee = $employees->get($employeeId);
                if (!$employee) {
                    continue;
                }

                $snapshot = $employeeSnapshots[(int) $employeeId] ?? null;
                if ($recordMode === 'request' && !($snapshot['is_state_cadre'] ?? false)) {
                    $skippedNotCadreCount++;
                    continue;
                }

                if ($recordMode === 'request' && $isAnnualCycle && !($snapshot['is_due_regular'] ?? false)) {
                    $skippedNotEligibleCount++;
                    continue;
                }

                if ($recordMode === 'request' && $isHonoraryPreRetirement && !($snapshot['is_due_honorary_pre_retirement'] ?? false)) {
                    $skippedNotEligibleCount++;
                    continue;
                }

                $matchedProposal = null;
                $actionPayLevel = $payLevel;
                $actionEffectiveDate = $effectiveDate;
                $actionPromotionType = $promotionType;

                if ($recordMode !== 'request') {
                    $matchedProposal = $this->resolvePendingProposalForAction(
                        $employee,
                        (int) $payLevel->id,
                        $effectiveDate,
                        $proposalId
                    );

                    if (!$matchedProposal) {
                        $skippedNoPendingRequestCount++;
                        continue;
                    }

                    $proposalPayLevel = GovPayLevel::query()
                        ->where('id', (int) $matchedProposal->pay_level_id)
                        ->where('is_active', true)
                        ->first();

                    if (!$proposalPayLevel) {
                        $skippedSameLevelCount++;
                        continue;
                    }

                    $actionPayLevel = $proposalPayLevel;
                    $actionEffectiveDate = Carbon::parse((string) $matchedProposal->start_date)->toDateString();
                    $actionPromotionType = $this->normalizePromotionType((string) ($matchedProposal->promotion_type ?? $promotionType));

                    if (!$this->proposalAllowsAction($matchedProposal, $recordMode)) {
                        $skippedInvalidStageCount++;
                        continue;
                    }
                }

                if (in_array($recordMode, ['approve', 'reject', 'recommend'], true)) {
                    $this->assertCanApproveEmployee(
                        $employee,
                        $orgUnitRuleService,
                        $actionPromotionType,
                        $recordMode
                    );
                }

                if ($recordMode !== 'reject') {
                    $currentPayLevelId = (int) (($currentPayLevelState[(int) $employee->id]['current_id'] ?? 0));
                    $currentPayLevel = $currentPayLevelId > 0
                        ? $activePayLevelsById->get($currentPayLevelId)
                        : null;

                    if ($currentPayLevel instanceof GovPayLevel && !$this->isHigherPayLevelThanCurrent($currentPayLevel, $actionPayLevel)) {
                        $skippedInvalidDirectionCount++;
                        continue;
                    }
                }

                $payload = [
                    'effective_date' => $actionEffectiveDate,
                    'promotion_type' => $actionPromotionType,
                    'next_review_date' => $nextReviewDate,
                    'document_reference' => $validated['document_reference'] ?: null,
                    'document_date' => $documentDate,
                    'request_reference' => $validated['request_reference'] ?: null,
                    'request_date' => $requestDate,
                    'note' => $validated['note'] ?: null,
                ];

                if ($matchedProposal) {
                    if (empty($payload['request_reference'])) {
                        $payload['request_reference'] = $matchedProposal->document_reference ?: null;
                    }
                    if (empty($payload['request_date']) && !empty($matchedProposal->document_date)) {
                        $payload['request_date'] = Carbon::parse((string) $matchedProposal->document_date)->toDateString();
                    }
                    if (empty($payload['promotion_type'])) {
                        $payload['promotion_type'] = $this->normalizePromotionType((string) $matchedProposal->promotion_type);
                    }
                }

                if ($recordMode === 'request') {
                    $updated = $this->storePromotionRequestForEmployee($employee, $actionPayLevel, $payload);
                    if ($updated) {
                        $requestCount++;
                    } else {
                        $skippedSameLevelCount++;
                    }
                    continue;
                }

                if ($recordMode === 'reject') {
                    $rejected = $this->rejectPendingPromotionRequests(
                        $employee,
                        $actionPayLevel,
                        $actionEffectiveDate,
                        (string) ($payload['note'] ?? ''),
                        $matchedProposal?->id
                    );
                    if ($rejected) {
                        $rejectedCount++;
                    } else {
                        $skippedSameLevelCount++;
                    }
                    continue;
                }

                if ($recordMode === 'recommend') {
                    $recommended = $this->markPendingPromotionRequestsAsRecommended(
                        $employee,
                        $actionPayLevel,
                        $actionEffectiveDate,
                        (string) ($payload['note'] ?? ''),
                        $matchedProposal?->id
                    );
                    if ($recommended) {
                        $requestCount++;
                    } else {
                        $skippedSameLevelCount++;
                    }
                    continue;
                }

                $updated = $this->storePromotionForEmployee($employee, $actionPayLevel, $payload);
                if ($updated) {
                    $updatedCount++;
                    $this->markPendingPromotionRequestsAsApproved(
                        $employee,
                        $actionPayLevel,
                        $actionEffectiveDate,
                        $matchedProposal?->id
                    );
                } else {
                    $skippedSameLevelCount++;
                }
            }

            DB::commit();

            if ($recordMode === 'request') {
                if ($requestCount > 0) {
                    Toastr::success(
                        $requestCount > 1
                            ? localize('promotion_request_created_for_n_employees', "Created promotion requests for {$requestCount} employees.")
                            : localize('promotion_request_created_successfully', 'Promotion request created successfully.'),
                        localize('success', 'Success')
                    );
                } else {
                    Toastr::info(
                        localize('no_new_promotion_request_created', 'No new promotion request was created.'),
                        localize('info', 'Info')
                    );
                }
            } elseif ($recordMode === 'approve') {
                if ($updatedCount > 0 && $skippedSameLevelCount > 0) {
                    Toastr::success(
                        localize('n_employees_promoted_and_m_skipped_same_level', "{$updatedCount} employees promoted. {$skippedSameLevelCount} skipped (same pay level)."),
                        localize('success', 'Success')
                    );
                } elseif ($updatedCount > 0) {
                    Toastr::success(
                        $updatedCount > 1
                            ? localize('promoted_n_employees_successfully', "Promoted {$updatedCount} employees successfully.")
                            : localize('promotion_saved_successfully', 'Promotion saved successfully.'),
                        localize('success', 'Success')
                    );
                } else {
                    Toastr::info(
                        localize('no_new_promotion_saved', 'No new promotion was saved.'),
                        localize('info', 'Info')
                    );
                }
            } elseif ($recordMode === 'recommend') {
                if ($requestCount > 0) {
                    Toastr::success(
                        $requestCount > 1
                            ? localize('recommended_n_promotion_requests', "Recommended {$requestCount} promotion requests.")
                            : localize('promotion_request_recommended_successfully', 'Promotion request recommended successfully.'),
                        localize('success', 'Success')
                    );
                } else {
                    Toastr::info(
                        localize('no_pending_request_to_recommend', 'No pending promotion request to recommend.'),
                        localize('info', 'Info')
                    );
                }
            } else {
                if ($rejectedCount > 0) {
                    Toastr::success(
                        $rejectedCount > 1
                            ? localize('rejected_n_promotion_requests', "Rejected {$rejectedCount} promotion requests.")
                            : localize('promotion_request_rejected_successfully', 'Promotion request rejected successfully.'),
                        localize('success', 'Success')
                    );
                } else {
                    Toastr::info(
                        localize('no_pending_request_to_reject', 'No pending promotion request to reject.'),
                        localize('info', 'Info')
                    );
                }
            }

            if ($skippedNotCadreCount > 0) {
                Toastr::warning(
                    localize('skipped_n_non_state_cadre_employees', "Skipped {$skippedNotCadreCount} non-state-cadre employees."),
                    localize('warning', 'Warning')
                );
            }

            if ($skippedNotEligibleCount > 0) {
                Toastr::warning(
                    localize('skipped_n_not_eligible_by_cutoff', "Skipped {$skippedNotEligibleCount} employees (not eligible by April 1 cutoff)."),
                    localize('warning', 'Warning')
                );
            }

            if ($skippedNoPendingRequestCount > 0) {
                Toastr::warning(
                    localize('skipped_n_without_pending_requests', "Skipped {$skippedNoPendingRequestCount} employees without pending requests."),
                    localize('warning', 'Warning')
                );
            }

            if ($skippedInvalidStageCount > 0) {
                Toastr::warning(
                    localize('skipped_n_requests_invalid_stage', "Skipped {$skippedInvalidStageCount} requests due to stage mismatch (request stage not allowed for selected action)."),
                    localize('warning', 'Warning')
                );
            }

            if ($skippedInvalidDirectionCount > 0) {
                Toastr::warning(
                    localize('skipped_n_invalid_promotion_direction', "Skipped {$skippedInvalidDirectionCount} employees (target pay level is not higher than current level)."),
                    localize('warning', 'Warning')
                );
            }

            return redirect()->route('employee-pay-promotions.index', [
                'year' => $cutoffYear,
                'tab' => $recordMode === 'request' ? 'form' : 'approvals',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            activity()
                ->causedBy(auth()->user())
                ->log('An error occurred: ' . $e->getMessage());

            Toastr::error(
                localize('failed_to_save_pay_promotion', 'Failed to save pay promotion.'),
                localize('failed', 'Failed')
            );

            return redirect()->back()->withInput();
        }
    }

    public function batchAction(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:1950|max:2100',
            'batch_action' => 'required|in:recommend,approve,reject',
            'proposal_ids' => 'required|array|min:1',
            'proposal_ids.*' => 'required|integer|exists:employee_pay_grade_histories,id',
            'note' => 'nullable|string',
            'batch_document_reference' => 'nullable|string|max:191',
            'batch_document_date' => 'nullable|date',
        ]);

        $year = (int) ($validated['year'] ?? now()->year);
        if ($year < 1950 || $year > 2100) {
            $year = (int) now()->year;
        }

        $batchAction = (string) $validated['batch_action'];
        $batchNote = trim((string) ($validated['note'] ?? ''));
        $batchDocumentReference = trim((string) ($validated['batch_document_reference'] ?? ''));
        $batchDocumentDate = !empty($validated['batch_document_date'])
            ? Carbon::parse((string) $validated['batch_document_date'])->toDateString()
            : null;

        if ($batchAction === 'approve' && $batchDocumentReference === '') {
            return redirect()->back()
                ->withErrors(['batch_document_reference' => localize('approval_document_reference_required', 'Please provide approval document reference for selected requests.')])
                ->withInput();
        }

        if ($batchAction === 'approve' && empty($batchDocumentDate)) {
            return redirect()->back()
                ->withErrors(['batch_document_date' => localize('approval_document_date_required', 'Please provide approval document date for selected requests.')])
                ->withInput();
        }

        if ($batchAction === 'reject' && $batchNote === '') {
            return redirect()->back()
                ->withErrors(['note' => localize('rejection_reason_required', 'Please provide rejection reason for selected requests.')])
                ->withInput();
        }

        $proposalIds = collect($validated['proposal_ids'] ?? [])
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($proposalIds)) {
            Toastr::warning(
                localize('no_pending_proposal_selected', 'Please select at least one pending proposal.'),
                localize('warning', 'Warning')
            );

            return redirect()->route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'approvals']);
        }

        $pendingProposals = EmployeePayGradeHistory::query()
            ->with([
                'employee.employee_type',
                'payLevel',
            ])
            ->whereIn('id', $proposalIds)
            ->whereIn('status', $this->pendingPromotionStatuses())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        if ($pendingProposals->isEmpty()) {
            Toastr::info(
                localize('no_pending_request_to_process', 'No pending requests were found to process.'),
                localize('info', 'Info')
            );

            return redirect()->route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'approvals']);
        }

        $employees = $pendingProposals
            ->map(fn ($proposal) => $proposal->employee)
            ->filter(fn ($employee) => $employee instanceof Employee)
            ->keyBy('id');

        $activePayLevels = GovPayLevel::query()
            ->where('is_active', true)
            ->get()
            ->keyBy('id');
        $currentPayLevelState = $this->currentPayLevelState($employees->values(), $activePayLevels->values());

        DB::beginTransaction();
        try {
            $approvedCount = 0;
            $recommendedCount = 0;
            $rejectedCount = 0;
            $skippedMissingCount = 0;
            $skippedNoChangeCount = 0;
            $skippedInvalidDirectionCount = 0;
            $skippedInvalidStageCount = 0;
            $skippedNoPermissionCount = 0;

            foreach ($pendingProposals as $proposal) {
                $employee = $proposal->employee;
                if (!$employee) {
                    $skippedMissingCount++;
                    continue;
                }

                $proposalType = $this->normalizePromotionType((string) ($proposal->promotion_type ?? 'annual_grade'));
                $user = auth()->user();
                if ($batchAction === 'approve') {
                    if (!$this->canFinalApprovePromotionAction($user, $employee, $proposalType, $orgUnitRuleService)) {
                        $skippedNoPermissionCount++;
                        continue;
                    }
                } else {
                    if (!$this->canRecommendEmployeeAction($user, $employee, $orgUnitRuleService, $proposalType, $batchAction)) {
                        $skippedNoPermissionCount++;
                        continue;
                    }
                }

                $proposalPayLevel = $activePayLevels->get((int) ($proposal->pay_level_id ?? 0));
                if (!$proposalPayLevel instanceof GovPayLevel) {
                    $skippedMissingCount++;
                    continue;
                }

                if (!$this->proposalAllowsAction($proposal, $batchAction)) {
                    $skippedInvalidStageCount++;
                    continue;
                }

                $effectiveDate = !empty($proposal->start_date)
                    ? Carbon::parse((string) $proposal->start_date)->toDateString()
                    : now()->toDateString();

                if ($batchAction === 'approve') {
                    $currentPayLevelId = (int) ($currentPayLevelState[(int) $employee->id]['current_id'] ?? 0);
                    $currentPayLevel = $currentPayLevelId > 0 ? $activePayLevels->get($currentPayLevelId) : null;

                    if (
                        $currentPayLevel instanceof GovPayLevel &&
                        !$this->isHigherPayLevelThanCurrent($currentPayLevel, $proposalPayLevel)
                    ) {
                        $skippedInvalidDirectionCount++;
                        continue;
                    }

                    $proposalNote = trim((string) ($proposal->note ?? ''));
                    $noteParts = array_filter([
                        $proposalNote,
                        $batchNote,
                    ], static fn ($value) => trim((string) $value) !== '');

                    $payload = [
                        'effective_date' => $effectiveDate,
                        'promotion_type' => $this->normalizePromotionType((string) ($proposal->promotion_type ?? 'annual_grade')),
                        'next_review_date' => !empty($proposal->next_review_date)
                            ? Carbon::parse((string) $proposal->next_review_date)->toDateString()
                            : null,
                        'document_reference' => $batchDocumentReference !== ''
                            ? $batchDocumentReference
                            : ($proposal->document_reference ?: null),
                        'document_date' => !empty($batchDocumentDate)
                            ? $batchDocumentDate
                            : (!empty($proposal->document_date)
                                ? Carbon::parse((string) $proposal->document_date)->toDateString()
                                : null),
                        'request_reference' => $proposal->document_reference ?: null,
                        'request_date' => !empty($proposal->document_date)
                            ? Carbon::parse((string) $proposal->document_date)->toDateString()
                            : null,
                        'note' => !empty($noteParts) ? implode(' | ', $noteParts) : null,
                    ];

                    $updated = $this->storePromotionForEmployee($employee, $proposalPayLevel, $payload);
                    if (!$updated) {
                        $skippedNoChangeCount++;
                        continue;
                    }

                    $approvedCount++;
                    $this->markPendingPromotionRequestsAsApproved(
                        $employee,
                        $proposalPayLevel,
                        $effectiveDate,
                        (int) $proposal->id,
                        $payload['document_reference'] ?? null,
                        $payload['document_date'] ?? null
                    );

                    $currentPayLevelState[(int) $employee->id]['current_id'] = (int) $proposalPayLevel->id;
                    $currentPayLevelState[(int) $employee->id]['current_label'] = $this->displayPayLevelLabel($proposalPayLevel);
                    continue;
                }

                if ($batchAction === 'recommend') {
                    $recommended = $this->markPendingPromotionRequestsAsRecommended(
                        $employee,
                        $proposalPayLevel,
                        $effectiveDate,
                        $batchNote !== '' ? $batchNote : null,
                        (int) $proposal->id
                    );
                    if ($recommended) {
                        $recommendedCount++;
                    } else {
                        $skippedNoChangeCount++;
                    }
                    continue;
                }

                $rejected = $this->rejectPendingPromotionRequests(
                    $employee,
                    $proposalPayLevel,
                    $effectiveDate,
                    $batchNote !== '' ? $batchNote : null,
                    (int) $proposal->id
                );
                if ($rejected) {
                    $rejectedCount++;
                } else {
                    $skippedNoChangeCount++;
                }
            }

            DB::commit();

            if ($batchAction === 'approve') {
                if ($approvedCount > 0) {
                    Toastr::success(
                        localize('batch_approved_n_requests', "Approved {$approvedCount} requests successfully."),
                        localize('success', 'Success')
                    );
                } else {
                    Toastr::info(
                        localize('no_pending_request_approved', 'No pending request was approved.'),
                        localize('info', 'Info')
                    );
                }
            } elseif ($batchAction === 'recommend') {
                if ($recommendedCount > 0) {
                    Toastr::success(
                        localize('batch_recommended_n_requests', "Recommended {$recommendedCount} requests successfully."),
                        localize('success', 'Success')
                    );
                } else {
                    Toastr::info(
                        localize('no_pending_request_recommended', 'No pending request was recommended.'),
                        localize('info', 'Info')
                    );
                }
            } else {
                if ($rejectedCount > 0) {
                    Toastr::success(
                        localize('batch_rejected_n_requests', "Rejected {$rejectedCount} requests successfully."),
                        localize('success', 'Success')
                    );
                } else {
                    Toastr::info(
                        localize('no_pending_request_rejected', 'No pending request was rejected.'),
                        localize('info', 'Info')
                    );
                }
            }

            if ($skippedInvalidDirectionCount > 0) {
                Toastr::warning(
                    localize('skipped_n_invalid_promotion_direction', "Skipped {$skippedInvalidDirectionCount} requests (target pay level is not higher than current level)."),
                    localize('warning', 'Warning')
                );
            }

            if ($skippedMissingCount > 0) {
                Toastr::warning(
                    localize('skipped_n_missing_or_inactive_records', "Skipped {$skippedMissingCount} requests (missing employee or inactive pay level)."),
                    localize('warning', 'Warning')
                );
            }

            if ($skippedNoChangeCount > 0) {
                Toastr::warning(
                    localize('skipped_n_requests_without_changes', "Skipped {$skippedNoChangeCount} requests without changes."),
                    localize('warning', 'Warning')
                );
            }

            if ($skippedNoPermissionCount > 0) {
                Toastr::warning(
                    localize('skipped_n_requests_without_permission', "Skipped {$skippedNoPermissionCount} requests due to role policy."),
                    localize('warning', 'Warning')
                );
            }

            if ($skippedInvalidStageCount > 0) {
                Toastr::warning(
                    localize('skipped_n_requests_invalid_stage', "Skipped {$skippedInvalidStageCount} requests due to stage mismatch (request stage not allowed for selected action)."),
                    localize('warning', 'Warning')
                );
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            activity()
                ->causedBy(auth()->user())
                ->log('Batch pay promotion approval/rejection error: ' . $e->getMessage());

            Toastr::error(
                localize('failed_to_process_batch_requests', 'Failed to process selected requests.'),
                localize('failed', 'Failed')
            );
        }

        return redirect()->route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'approvals']);
    }

    protected function storeBulkPromotionRequests(Request $request, OrgUnitRuleService $orgUnitRuleService)
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:1950|max:2100',
            'bulk_items' => 'required|string',
            'bulk_removed_items' => 'nullable|string',
        ]);

        $year = (int) ($validated['year'] ?? now()->year);
        if ($year < 1950 || $year > 2100) {
            $year = (int) now()->year;
        }

        $bulkItems = json_decode((string) $validated['bulk_items'], true);
        if (!is_array($bulkItems) || empty($bulkItems)) {
            Toastr::warning(
                localize('no_valid_bulk_items', 'No valid names to submit.'),
                localize('warning', 'Warning')
            );
            return redirect()->route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'form']);
        }

        $normalizedByEmployee = [];
        foreach ($bulkItems as $row) {
            if (!is_array($row)) {
                continue;
            }

            $employeeId = (int) ($row['employee_id'] ?? 0);
            $payLevelId = (int) ($row['pay_level_id'] ?? 0);
            if ($employeeId <= 0 || $payLevelId <= 0) {
                continue;
            }

            $promotionType = $this->normalizePromotionType((string) ($row['promotion_type'] ?? 'annual_grade'));
            $effectiveDate = null;
            if (!empty($row['effective_date'])) {
                try {
                    $effectiveDate = Carbon::parse((string) $row['effective_date'])->toDateString();
                } catch (\Throwable $th) {
                    $effectiveDate = null;
                }
            }
            if (empty($effectiveDate)) {
                $effectiveDate = Carbon::create($year, 4, 1)->toDateString();
            }

            $normalizedByEmployee[$employeeId] = [
                'employee_id' => $employeeId,
                'pay_level_id' => $payLevelId,
                'promotion_type' => $promotionType,
                'effective_date' => $effectiveDate,
                'note' => trim((string) ($row['note'] ?? '')),
            ];
        }

        $rows = collect($normalizedByEmployee)->values();
        if ($rows->isEmpty()) {
            Toastr::warning(
                localize('no_valid_bulk_items', 'No valid names to submit.'),
                localize('warning', 'Warning')
            );
            return redirect()->route('employee-pay-promotions.index', ['year' => $year, 'tab' => 'form']);
        }

        $employeeIds = $rows->pluck('employee_id')->map(fn ($id) => (int) $id)->values()->all();
        $payLevelIds = $rows->pluck('pay_level_id')->map(fn ($id) => (int) $id)->values()->unique()->all();

        $employees = Employee::query()
            ->whereIn('id', $employeeIds)
            ->with(['employee_type'])
            ->get()
            ->keyBy('id');

        foreach ($employees as $employee) {
            $this->assertCanManageEmployee($employee, $orgUnitRuleService);
        }

        $activePayLevels = GovPayLevel::query()
            ->where('is_active', true)
            ->whereIn('id', $payLevelIds)
            ->get()
            ->keyBy('id');

        $allActivePayLevels = GovPayLevel::query()->where('is_active', true)->get();
        $currentPayLevelState = $this->currentPayLevelState($employees->values(), $allActivePayLevels);
        $cutoffDate = Carbon::create($year, 4, 1)->endOfDay();
        $employeeSnapshots = $this->buildPromotionSnapshots($employees->values(), $cutoffDate);

        DB::beginTransaction();
        try {
            $createdCount = 0;
            $skippedNotFoundCount = 0;
            $skippedNotCadreCount = 0;
            $skippedNotEligibleCount = 0;
            $skippedInvalidDirectionCount = 0;
            $skippedDuplicateCount = 0;

            foreach ($rows as $row) {
                $employeeId = (int) ($row['employee_id'] ?? 0);
                $payLevelId = (int) ($row['pay_level_id'] ?? 0);
                $promotionType = (string) ($row['promotion_type'] ?? 'annual_grade');
                $effectiveDate = (string) ($row['effective_date'] ?? Carbon::create($year, 4, 1)->toDateString());
                $note = trim((string) ($row['note'] ?? ''));

                $employee = $employees->get($employeeId);
                $payLevel = $activePayLevels->get($payLevelId);
                if (!$employee || !$payLevel) {
                    $skippedNotFoundCount++;
                    continue;
                }

                $snapshot = $employeeSnapshots[$employeeId] ?? null;
                if (!($snapshot['is_state_cadre'] ?? false)) {
                    $skippedNotCadreCount++;
                    continue;
                }

                $isAnnualCycle = $this->isAnnualCyclePromotionType($promotionType);
                $isHonoraryPreRetirement = $this->isHonoraryPreRetirementPromotionType($promotionType);
                if ($isAnnualCycle && !($snapshot['is_due_regular'] ?? false)) {
                    $skippedNotEligibleCount++;
                    continue;
                }
                if ($isHonoraryPreRetirement && !($snapshot['is_due_honorary_pre_retirement'] ?? false)) {
                    $skippedNotEligibleCount++;
                    continue;
                }

                $currentPayLevelId = (int) (($currentPayLevelState[$employeeId]['current_id'] ?? 0));
                $currentPayLevel = $currentPayLevelId > 0
                    ? $allActivePayLevels->firstWhere('id', $currentPayLevelId)
                    : null;
                if ($currentPayLevel instanceof GovPayLevel && !$this->isHigherPayLevelThanCurrent($currentPayLevel, $payLevel)) {
                    $skippedInvalidDirectionCount++;
                    continue;
                }

                $payload = [
                    'effective_date' => $effectiveDate,
                    'promotion_type' => $promotionType,
                    'next_review_date' => $isAnnualCycle
                        ? Carbon::parse($effectiveDate)->addYears(2)->toDateString()
                        : null,
                    'document_reference' => null,
                    'document_date' => null,
                    'request_reference' => null,
                    'request_date' => null,
                    'note' => $note !== '' ? $note : null,
                ];

                $created = $this->storePromotionRequestForEmployee($employee, $payLevel, $payload);
                if ($created) {
                    $createdCount++;
                } else {
                    $skippedDuplicateCount++;
                }
            }

            DB::commit();

            if ($createdCount > 0) {
                Toastr::success(
                    localize('bulk_requests_created_n', "Created {$createdCount} promotion requests."),
                    localize('success', 'Success')
                );
            } else {
                Toastr::info(
                    localize('no_new_promotion_request_created', 'No new promotion request was created.'),
                    localize('info', 'Info')
                );
            }

            if ($skippedNotFoundCount > 0) {
                Toastr::warning(
                    localize('skipped_n_invalid_rows', "Skipped {$skippedNotFoundCount} invalid rows."),
                    localize('warning', 'Warning')
                );
            }

            if ($skippedNotCadreCount > 0) {
                Toastr::warning(
                    localize('skipped_n_non_state_cadre_employees', "Skipped {$skippedNotCadreCount} non-state-cadre employees."),
                    localize('warning', 'Warning')
                );
            }

            if ($skippedNotEligibleCount > 0) {
                Toastr::warning(
                    localize('skipped_n_not_eligible_by_cutoff', "Skipped {$skippedNotEligibleCount} employees (not eligible by April 1 cutoff)."),
                    localize('warning', 'Warning')
                );
            }

            if ($skippedInvalidDirectionCount > 0) {
                Toastr::warning(
                    localize('skipped_n_invalid_promotion_direction', "Skipped {$skippedInvalidDirectionCount} employees (target pay level is not higher than current level)."),
                    localize('warning', 'Warning')
                );
            }

            if ($skippedDuplicateCount > 0) {
                Toastr::warning(
                    localize('skipped_n_duplicate_requests', "Skipped {$skippedDuplicateCount} duplicate/same-level requests."),
                    localize('warning', 'Warning')
                );
            }

            $removedRowsRaw = trim((string) ($validated['bulk_removed_items'] ?? ''));
            if ($removedRowsRaw !== '') {
                $removedRows = json_decode($removedRowsRaw, true);
                if (is_array($removedRows) && !empty($removedRows)) {
                    activity()
                        ->causedBy(auth()->user())
                        ->withProperties(['bulk_removed_items' => $removedRows, 'year' => $year])
                        ->log('Pay promotion batch: removed names with reasons');
                }
            }

            return redirect()->route('employee-pay-promotions.index', [
                'year' => $year,
                'tab' => 'form',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            activity()
                ->causedBy(auth()->user())
                ->log('Bulk pay promotion request error: ' . $e->getMessage());

            Toastr::error(
                localize('failed_to_save_pay_promotion', 'Failed to save pay promotion.'),
                localize('failed', 'Failed')
            );

            return redirect()->back()->withInput();
        }
    }
    protected function storePromotionForEmployee(Employee $employee, GovPayLevel $payLevel, array $payload): bool
    {
        $effectiveDate = (string) $payload['effective_date'];
        $newPayLevelLabel = $this->displayPayLevelLabel($payLevel);

        $currentActive = EmployeePayGradeHistory::query()
            ->with('payLevel')
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where(function ($q) use ($effectiveDate) {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $effectiveDate);
            })
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if (!$currentActive) {
            $currentActive = EmployeePayGradeHistory::query()
                ->with('payLevel')
                ->where('employee_id', $employee->id)
                ->where('status', 'active')
                ->orderByDesc('start_date')
                ->orderByDesc('id')
                ->first();
        }

        if ($currentActive && (int) ($currentActive->pay_level_id ?? 0) === (int) $payLevel->id) {
            return false;
        }

        $latestHistory = EmployeePayGradeHistory::query()
            ->with('payLevel')
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        $oldPayLevelLabel = '-';
        if ($currentActive && $currentActive->payLevel) {
            $oldPayLevelLabel = $this->displayPayLevelLabel($currentActive->payLevel);
        } elseif ($latestHistory && $latestHistory->payLevel) {
            $oldPayLevelLabel = $this->displayPayLevelLabel($latestHistory->payLevel);
        } else {
            $legacyLabel = $this->normalizeKhmerText($employee->employee_grade ?? '');
            if ($legacyLabel !== '') {
                $oldPayLevelLabel = $legacyLabel;
            }
        }

        $previousActiveRows = EmployeePayGradeHistory::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where(function ($q) use ($effectiveDate) {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $effectiveDate);
            })
            ->get();

        foreach ($previousActiveRows as $row) {
            $row->status = 'inactive';
            if (!$row->end_date || Carbon::parse($row->end_date)->gte($effectiveDate)) {
                $row->end_date = Carbon::parse($effectiveDate)->subDay()->toDateString();
            }
            $row->save();
        }

        EmployeePayGradeHistory::create([
            'employee_id' => $employee->id,
            'pay_level_id' => $payLevel->id,
            'start_date' => $effectiveDate,
            'end_date' => null,
            'status' => 'active',
            'promotion_type' => $payload['promotion_type'],
            'document_reference' => $payload['document_reference'] ?: null,
            'document_date' => $payload['document_date'] ?: null,
            'next_review_date' => $payload['next_review_date'] ?: null,
            'note' => $payload['note'] ?: null,
        ]);

        $employee->employee_grade = $newPayLevelLabel;
        $employee->save();

        $noteParts = [
            'Old pay level: ' . $oldPayLevelLabel,
            'New pay level: ' . $newPayLevelLabel,
            'Effective date: ' . $effectiveDate,
        ];

        if (!$this->isAnnualCyclePromotionType((string) ($payload['promotion_type'] ?? ''))) {
            if (!empty($payload['request_reference'])) {
                $noteParts[] = 'Request No: ' . $payload['request_reference'];
            }
            if (!empty($payload['request_date'])) {
                $noteParts[] = 'Request date: ' . $payload['request_date'];
            }
        }

        $normalizedNote = $this->normalizeKhmerText($payload['note'] ?? '');
        if ($normalizedNote !== '') {
            $noteParts[] = $normalizedNote;
        }

        EmployeeWorkHistory::create([
            'employee_id' => $employee->id,
            'work_status_name' => 'Pay grade promotion',
            'start_date' => $effectiveDate,
            'document_reference' => $payload['document_reference'] ?: null,
            'document_date' => $payload['document_date'] ?: null,
            'note' => implode(' | ', $noteParts),
        ]);

        return true;
    }
    protected function storePromotionRequestForEmployee(Employee $employee, GovPayLevel $payLevel, array $payload): bool
    {
        $effectiveDate = (string) ($payload['effective_date'] ?? now()->toDateString());

        $currentActive = EmployeePayGradeHistory::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($currentActive && (int) ($currentActive->pay_level_id ?? 0) === (int) $payLevel->id) {
            return false;
        }

        $duplicateProposal = EmployeePayGradeHistory::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', $this->pendingPromotionStatuses())
            ->exists();

        if ($duplicateProposal) {
            return false;
        }

        $noteParts = [];
        if (!empty($payload['request_reference'])) {
            $noteParts[] = 'Request No: ' . (string) $payload['request_reference'];
        }
        if (!empty($payload['request_date'])) {
            $noteParts[] = 'Request date: ' . (string) $payload['request_date'];
        }
        if (!empty($payload['note'])) {
            $noteParts[] = (string) $payload['note'];
        }

        EmployeePayGradeHistory::create([
            'employee_id' => $employee->id,
            'pay_level_id' => $payLevel->id,
            'start_date' => $effectiveDate,
            'end_date' => null,
            'status' => self::STATUS_PROPOSED,
            'promotion_type' => $payload['promotion_type'] ?? 'annual_grade',
            'document_reference' => $payload['request_reference'] ?: ($payload['document_reference'] ?: null),
            'document_date' => $payload['request_date'] ?: ($payload['document_date'] ?: null),
            'next_review_date' => $payload['next_review_date'] ?: null,
            'note' => !empty($noteParts) ? implode(' | ', $noteParts) : null,
        ]);

        EmployeeWorkHistory::create([
            'employee_id' => $employee->id,
            'work_status_name' => 'Pay grade promotion request',
            'start_date' => $effectiveDate,
            'document_reference' => $payload['request_reference'] ?: ($payload['document_reference'] ?: null),
            'document_date' => $payload['request_date'] ?: ($payload['document_date'] ?: null),
            'note' => !empty($noteParts) ? implode(' | ', $noteParts) : null,
        ]);

        return true;
    }
    protected function markPendingPromotionRequestsAsApproved(
        Employee $employee,
        GovPayLevel $payLevel,
        string $effectiveDate,
        ?int $proposalId = null,
        ?string $approvalDocumentReference = null,
        ?string $approvalDocumentDate = null
    ): void
    {
        $pendingRowsQuery = EmployeePayGradeHistory::query()
            ->where('employee_id', $employee->id)
            ->where('pay_level_id', $payLevel->id)
            ->whereIn('status', $this->allowedProposalStatusesForAction('approve'));

        if (!empty($proposalId)) {
            $pendingRowsQuery->where('id', (int) $proposalId);
        } else {
            $pendingRowsQuery->whereDate('start_date', '<=', $effectiveDate);
        }

        $pendingRows = $pendingRowsQuery->get();

        foreach ($pendingRows as $row) {
            $row->status = self::STATUS_APPROVED;
            $row->end_date = $effectiveDate;
            if (!empty($approvalDocumentReference)) {
                $row->document_reference = trim((string) $approvalDocumentReference);
            }
            if (!empty($approvalDocumentDate)) {
                $row->document_date = $approvalDocumentDate;
            }
            $existingNote = trim((string) ($row->note ?? ''));
            $row->note = trim($existingNote . ($existingNote !== '' ? ' | ' : '') . 'Approved by promotion action');
            $row->save();
        }
    }

    protected function markPendingPromotionRequestsAsRecommended(
        Employee $employee,
        GovPayLevel $payLevel,
        string $effectiveDate,
        ?string $note = null,
        ?int $proposalId = null
    ): bool {
        $pendingRowsQuery = EmployeePayGradeHistory::query()
            ->where('employee_id', $employee->id)
            ->where('pay_level_id', $payLevel->id)
            ->whereIn('status', $this->allowedProposalStatusesForAction('recommend'));

        if (!empty($proposalId)) {
            $pendingRowsQuery->where('id', (int) $proposalId);
        } else {
            $pendingRowsQuery->whereDate('start_date', '<=', $effectiveDate);
        }

        $pendingRows = $pendingRowsQuery->get();
        if ($pendingRows->isEmpty()) {
            return false;
        }

        $recommendedBy = trim((string) (auth()->user()?->full_name ?? auth()->user()?->name ?? ''));
        $recommendedAt = now()->format('Y-m-d H:i:s');

        foreach ($pendingRows as $row) {
            $row->status = self::STATUS_RECOMMENDED;
            $existingNote = trim((string) ($row->note ?? ''));
            $parts = array_filter([
                $existingNote,
                'Recommended for final approval'
                    . ($recommendedBy !== '' ? (' by ' . $recommendedBy) : '')
                    . ' at ' . $recommendedAt,
                trim((string) $note),
            ], fn ($item) => trim((string) $item) !== '');
            $row->note = implode(' | ', $parts);
            $row->save();
        }

        EmployeeWorkHistory::create([
            'employee_id' => $employee->id,
            'work_status_name' => 'Pay grade promotion recommended',
            'start_date' => $effectiveDate,
            'note' => trim((string) $note) !== '' ? trim((string) $note) : 'Recommended for final approval',
        ]);

        return true;
    }

    protected function rejectPendingPromotionRequests(
        Employee $employee,
        GovPayLevel $payLevel,
        string $effectiveDate,
        ?string $note = null,
        ?int $proposalId = null
    ): bool
    {
        $pendingRowsQuery = EmployeePayGradeHistory::query()
            ->where('employee_id', $employee->id)
            ->where('pay_level_id', $payLevel->id)
            ->whereIn('status', $this->allowedProposalStatusesForAction('reject'));

        if (!empty($proposalId)) {
            $pendingRowsQuery->where('id', (int) $proposalId);
        } else {
            $pendingRowsQuery->whereDate('start_date', '<=', $effectiveDate);
        }

        $pendingRows = $pendingRowsQuery->get();

        if ($pendingRows->isEmpty()) {
            return false;
        }

        foreach ($pendingRows as $row) {
            $row->status = self::STATUS_REJECTED;
            $row->end_date = $effectiveDate;
            $existingNote = trim((string) ($row->note ?? ''));
            $parts = array_filter([
                $existingNote,
                'Rejected by approver',
                trim((string) $note),
            ], fn ($item) => trim((string) $item) !== '');
            $row->note = implode(' | ', $parts);
            $row->save();
        }

        EmployeeWorkHistory::create([
            'employee_id' => $employee->id,
            'work_status_name' => 'Pay grade promotion request rejected',
            'start_date' => $effectiveDate,
            'note' => trim((string) $note) !== '' ? trim((string) $note) : 'Rejected by approver',
        ]);

        return true;
    }

    protected function resolvePendingProposalForAction(
        Employee $employee,
        int $payLevelId,
        string $effectiveDate,
        ?int $proposalId = null
    ): ?EmployeePayGradeHistory {
        $query = EmployeePayGradeHistory::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', $this->pendingPromotionStatuses());

        if (!empty($proposalId)) {
            $query->where('id', (int) $proposalId);
        } else {
            $query
                ->where('pay_level_id', $payLevelId)
                ->whereDate('start_date', '<=', $effectiveDate)
                ->orderByDesc('start_date')
                ->orderByDesc('id');
        }

        return $query->first();
    }

    protected function isHigherPayLevelThanCurrent(GovPayLevel $current, GovPayLevel $target): bool
    {
        if ((int) $current->id === (int) $target->id) {
            return false;
        }

        $currentFamily = $this->payLevelFamilyFromDbRow(
            (string) ($current->level_code ?? ''),
            (string) ($current->level_name_km ?? '')
        );
        $targetFamily = $this->payLevelFamilyFromDbRow(
            (string) ($target->level_code ?? ''),
            (string) ($target->level_name_km ?? '')
        );
        if ($currentFamily === '' || $targetFamily === '' || $currentFamily !== $targetFamily) {
            // Do not allow crossing major level groups (A/B/C or ក/ខ/គ).
            return false;
        }

        $currentOrder = (int) ($current->sort_order ?? 0);
        $targetOrder = (int) ($target->sort_order ?? 0);
        if ($currentOrder > 0 && $targetOrder > 0 && $currentOrder !== $targetOrder) {
            // DB-driven direction: lower sort_order = higher class.
            return $targetOrder < $currentOrder;
        }

        $currentBudget = (float) ($current->budget_amount ?? 0);
        $targetBudget = (float) ($target->budget_amount ?? 0);
        if ($currentBudget > 0 && $targetBudget > 0 && abs($targetBudget - $currentBudget) > 0.00001) {
            return $targetBudget > $currentBudget;
        }

        // Fallback when sort_order/budget are not usable.
        return $this->comparePayLevelWithinFamily((string) $target->level_code, (string) $current->level_code) < 0;
    }

    protected function payLevelFamilyFromDbRow(string $code, string $nameKm = ''): string
    {
        $cleanName = trim((string) $nameKm);
        if ($cleanName !== '') {
            $prefix = mb_substr($cleanName, 0, 1, 'UTF-8');
            $mapped = $this->normalizePayLevelFamilyPrefix($prefix);
            if ($mapped !== '') {
                return $mapped;
            }
        }

        $clean = trim((string) $code);
        if ($clean === '') {
            return '';
        }

        $firstChar = mb_substr($clean, 0, 1, 'UTF-8');
        return $this->normalizePayLevelFamilyPrefix($firstChar);
    }

    protected function normalizePayLevelFamilyPrefix(string $prefix): string
    {
        $firstChar = mb_strtoupper(trim($prefix), 'UTF-8');
        if ($firstChar === '') {
            return '';
        }

        $khmerMap = [
            'ក' => 'A',
            'ខ' => 'B',
            'គ' => 'C',
            'ឃ' => 'D',
            'ង' => 'E',
            'ច' => 'F',
            'ឆ' => 'G',
            'ជ' => 'H',
        ];

        if (isset($khmerMap[$firstChar])) {
            return $khmerMap[$firstChar];
        }

        if (preg_match('/^[A-Z]$/', $firstChar) === 1) {
            return $firstChar;
        }

        return '';
    }

    protected function comparePayLevelWithinFamily(string $leftCode, string $rightCode): int
    {
        $leftParts = $this->payLevelCodeParts($leftCode);
        $rightParts = $this->payLevelCodeParts($rightCode);

        $max = max(count($leftParts), count($rightParts));
        for ($i = 0; $i < $max; $i++) {
            $a = $leftParts[$i] ?? 0;
            $b = $rightParts[$i] ?? 0;
            if ($a === $b) {
                continue;
            }
            return $a <=> $b;
        }

        return strnatcmp(strtoupper(trim($leftCode)), strtoupper(trim($rightCode)));
    }

    protected function payLevelCodeParts(string $code): array
    {
        $clean = preg_replace('/\s+/', '', strtoupper((string) $code)) ?? '';
        if ($clean === '') {
            return [PHP_INT_MAX];
        }

        $parts = [];
        preg_match_all('/\d+/', $clean, $matches);
        foreach (($matches[0] ?? []) as $digitText) {
            $parts[] = (int) $digitText;
        }

        return !empty($parts) ? $parts : [PHP_INT_MAX];
    }
    protected function buildPromotionSnapshots(Collection $employees, Carbon $cutoffDate): array
    {
        $employeeIds = $employees->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values()
            ->all();

        if (empty($employeeIds)) {
            return [];
        }

        $payHistoriesByEmployee = EmployeePayGradeHistory::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereNotIn('status', [self::STATUS_PROPOSED, self::STATUS_RECOMMENDED, self::STATUS_REJECTED, self::STATUS_CANCELLED])
            ->whereDate('start_date', '<=', $cutoffDate->toDateString())
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get(['id', 'employee_id', 'start_date', 'status'])
            ->groupBy('employee_id');

        $transitionsByEmployee = collect();
        if (Schema::hasTable('employee_status_transitions')) {
            $transitionsByEmployee = EmployeeStatusTransition::query()
                ->whereIn('employee_id', $employeeIds)
                ->whereDate('effective_date', '<=', $cutoffDate->toDateString())
                ->orderBy('effective_date')
                ->orderBy('id')
                ->get(['id', 'employee_id', 'effective_date', 'to_service_state'])
                ->groupBy('employee_id');
        }

        $snapshots = [];
        foreach ($employees as $employee) {
            $employeeId = (int) $employee->id;
            $snapshots[$employeeId] = $this->buildPromotionSnapshotForEmployee(
                $employee,
                $cutoffDate,
                collect($payHistoriesByEmployee->get($employeeId, [])),
                collect($transitionsByEmployee->get($employeeId, []))
            );
        }

        return $snapshots;
    }

    protected function buildPromotionSnapshotForEmployee(
        Employee $employee,
        Carbon $cutoffDate,
        Collection $payHistoryRows,
        Collection $transitions
    ): array {
        $employeeId = (int) $employee->id;
        $isStateCadre = $this->isStateCadreEmployee($employee);
        $currentServiceState = $this->normalizeServiceState((string) ($employee->service_state ?? 'active'));
        $lastPromotionDate = $payHistoryRows->first()->start_date ?? null;
        $anchorDate = $this->lastPromotionAnchorDate($employee, $payHistoryRows, $cutoffDate);

        $countableDays = $anchorDate
            ? $this->countCountableServiceDays($employee, $anchorDate, $cutoffDate, $transitions)
            : 0;

        $retirementDate = null;
        $daysToRetirement = null;
        if (!empty($employee->date_of_birth)) {
            try {
                $retirementDate = Carbon::parse((string) $employee->date_of_birth)
                    ->addYears(60)
                    ->startOfDay();
                $daysToRetirement = $cutoffDate->copy()->startOfDay()->diffInDays($retirementDate, false);
            } catch (\Throwable $th) {
                $retirementDate = null;
                $daysToRetirement = null;
            }
        }

        // countCountableServiceDays() uses inclusive day counting (+1 for each active segment),
        // so thresholds for exact anniversaries must use +1 day to avoid off-by-one early eligibility.
        // 2 years => >= 731 days, 3 years => >= 1096 days.
        $isDueRegular = $isStateCadre && $currentServiceState === 'active' && $countableDays >= 731;
        $isOverdue3y = $isStateCadre && $currentServiceState === 'active' && $countableDays >= 1096;
        $isDueHonoraryPreRetirement = $isStateCadre
            && $currentServiceState === 'active'
            && $retirementDate !== null
            && $daysToRetirement !== null
            && $daysToRetirement >= 0
            && $daysToRetirement <= 365;

        return [
            'employee_id' => $employeeId,
            'employee_code' => (string) ($employee->employee_id ?? ''),
            'full_name' => (string) ($employee->full_name ?? ''),
            'service_state' => $currentServiceState,
            'is_state_cadre' => $isStateCadre,
            'anchor_date' => $anchorDate ? $anchorDate->toDateString() : null,
            'last_promotion_date' => $lastPromotionDate ? Carbon::parse($lastPromotionDate)->toDateString() : null,
            'retirement_date' => $retirementDate?->toDateString(),
            'days_to_retirement' => $daysToRetirement,
            'countable_days' => (int) $countableDays,
            'countable_years' => round($countableDays / 365, 2),
            'is_due_regular' => $isDueRegular,
            'is_overdue_3y' => $isOverdue3y,
            'is_due_honorary_pre_retirement' => $isDueHonoraryPreRetirement,
        ];
    }

    protected function lastPromotionAnchorDate(Employee $employee, Collection $payHistoryRows, Carbon $cutoffDate): ?Carbon
    {
        $latestPayRow = $payHistoryRows->first();
        if ($latestPayRow && !empty($latestPayRow->start_date)) {
            try {
                return Carbon::parse($latestPayRow->start_date)->startOfDay();
            } catch (\Throwable $th) {
                // keep resolving fallback dates below
            }
        }

        $fallbackDates = [
            $employee->full_right_date ?? null,
            $employee->service_start_date ?? null,
            $employee->joining_date ?? null,
            $employee->hire_date ?? null,
            $employee->promotion_date ?? null,
        ];

        foreach ($fallbackDates as $rawDate) {
            if (empty($rawDate)) {
                continue;
            }
            try {
                $date = Carbon::parse($rawDate)->startOfDay();
                if ($date->lte($cutoffDate)) {
                    return $date;
                }
            } catch (\Throwable $th) {
                // ignore invalid date candidates
            }
        }

        return null;
    }

    protected function countCountableServiceDays(
        Employee $employee,
        Carbon $fromDate,
        Carbon $toDate,
        Collection $transitions
    ): int {
        $start = $fromDate->copy()->startOfDay();
        $end = $toDate->copy()->startOfDay();
        if ($start->gt($end)) {
            return 0;
        }

        $state = $this->serviceStateAtDate($employee, $start, $transitions);
        $cursor = $start->copy();
        $countableDays = 0;

        foreach ($transitions as $transition) {
            $effectiveRaw = data_get($transition, 'effective_date');
            if (empty($effectiveRaw)) {
                continue;
            }

            try {
                $effectiveDate = Carbon::parse($effectiveRaw)->startOfDay();
            } catch (\Throwable $th) {
                continue;
            }

            if ($effectiveDate->lt($start)) {
                $state = $this->normalizeServiceState((string) data_get($transition, 'to_service_state'), $state);
                continue;
            }

            if ($effectiveDate->gt($end)) {
                break;
            }

            $segmentEnd = $effectiveDate->copy()->subDay();
            if ($segmentEnd->gte($cursor) && $state === 'active') {
                $countableDays += $cursor->diffInDays($segmentEnd) + 1;
            }

            $state = $this->normalizeServiceState((string) data_get($transition, 'to_service_state'), $state);
            $cursor = $effectiveDate->copy();
        }

        if ($cursor->lte($end) && $state === 'active') {
            $countableDays += $cursor->diffInDays($end) + 1;
        }

        return max(0, (int) $countableDays);
    }

    protected function serviceStateAtDate(Employee $employee, Carbon $date, Collection $transitions): string
    {
        $defaultState = $this->normalizeServiceState((string) ($employee->service_state ?? 'active'));
        $latest = null;

        foreach ($transitions as $transition) {
            $effectiveRaw = data_get($transition, 'effective_date');
            if (empty($effectiveRaw)) {
                continue;
            }

            try {
                $effectiveDate = Carbon::parse($effectiveRaw)->startOfDay();
            } catch (\Throwable $th) {
                continue;
            }

            if ($effectiveDate->gt($date)) {
                break;
            }

            $latest = $transition;
        }

        if (!$latest) {
            return $defaultState;
        }

        return $this->normalizeServiceState((string) data_get($latest, 'to_service_state'), $defaultState);
    }

    protected function normalizeServiceState(?string $state, string $fallback = 'active'): string
    {
        $value = strtolower(trim((string) $state));
        if (in_array($value, ['active', 'suspended', 'inactive'], true)) {
            return $value;
        }

        if (
            str_contains($value, 'suspend')
            || str_contains($value, 'without pay')
            || str_contains($value, 'ទំនេរ')
            || str_contains($value, 'គ្មានបៀវត្ស')
        ) {
            return 'suspended';
        }

        if (
            str_contains($value, 'retire')
            || str_contains($value, 'inactive')
            || str_contains($value, 'ចូលនិវត្ត')
            || str_contains($value, 'ស្លាប់')
            || str_contains($value, 'អសកម្ម')
        ) {
            return 'inactive';
        }

        if (
            str_contains($value, 'active')
            || str_contains($value, 'កំពុងបម្រើ')
            || str_contains($value, 'សកម្ម')
        ) {
            return 'active';
        }

        return $fallback;
    }
    protected function isStateCadreEmployee(Employee $employee): bool
    {
        if ((int) ($employee->is_full_right_officer ?? 0) === 1) {
            return true;
        }

        $employeeTypeId = (int) ($employee->employee_type_id ?? 0);
        if ($employeeTypeId > 0) {
            $categoryMap = $this->employeeTypeCategoryMap();
            if (($categoryMap[$employeeTypeId] ?? null) === 'state_cadre') {
                return true;
            }
        }

        // Fallback for legacy rows where employee_type_id is empty.
        $labels = array_filter([
            (string) data_get($employee, 'employee_type.employee_type_name', ''),
            (string) data_get($employee, 'employee_type.name_km', ''),
            (string) data_get($employee, 'employee_type.name', ''),
        ]);

        foreach ($labels as $label) {
            if ($this->employeeTypeCategoryFromLabel($label) === 'state_cadre') {
                return true;
            }
        }

        return false;
    }

    /**
     * Classify employee types from DB.
     *
     * @return array<int, string>
     */
    protected function employeeTypeCategoryMap(): array
    {
        if (is_array($this->employeeTypeCategoryCache)) {
            return $this->employeeTypeCategoryCache;
        }

        $columns = ['id'];
        foreach (['employee_type_name', 'name_km', 'name'] as $column) {
            if (Schema::hasColumn('employee_types', $column)) {
                $columns[] = $column;
            }
        }

        $map = [];
        foreach (EmployeeType::query()->get($columns) as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id <= 0) {
                continue;
            }

            $labels = [
                (string) data_get($row, 'employee_type_name', ''),
                (string) data_get($row, 'name_km', ''),
                (string) data_get($row, 'name', ''),
            ];

            $category = 'other';
            foreach ($labels as $label) {
                $candidate = $this->employeeTypeCategoryFromLabel($label);
                if ($candidate !== 'other') {
                    $category = $candidate;
                    break;
                }
            }

            $map[$id] = $category;
        }

        $this->employeeTypeCategoryCache = $map;

        return $this->employeeTypeCategoryCache;
    }

    protected function employeeTypeCategoryFromLabel(?string $label): string
    {
        $value = mb_strtolower($this->normalizeKhmerText((string) $label), 'UTF-8');
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
        if ($value === '') {
            return 'other';
        }

        foreach ([
            'បុគ្គលិកក្របខណ្ឌរដ្ឋ',
            'បុគ្គលិកក្របខ័ណ្ឌរដ្ឋ',
            'ក្របខណ្ឌរដ្ឋ',
            'ក្របខ័ណ្ឌរដ្ឋ',
            'មន្ត្រីរាជការ',
            'មន្រ្តីរាជការ',
            'state cadre',
            'civil servant',
            'civil service',
            'government staff',
            'permanent',
            'full time',
        ] as $keyword) {
            if (str_contains($value, mb_strtolower($keyword, 'UTF-8'))) {
                return 'state_cadre';
            }
        }

        foreach ([
            'បុគ្គលិកកិច្ចសន្យា',
            'កិច្ចសន្យា',
            'contract',
            'contractual',
        ] as $keyword) {
            if (str_contains($value, mb_strtolower($keyword, 'UTF-8'))) {
                return 'contract';
            }
        }

        foreach ([
            'បុគ្គលិកកិច្ចព្រមព្រៀង',
            'កិច្ចព្រមព្រៀង',
            'agreement',
            'mou',
        ] as $keyword) {
            if (str_contains($value, mb_strtolower($keyword, 'UTF-8'))) {
                return 'agreement';
            }
        }

        return 'other';
    }

    protected function currentPayLevelLabels(Collection $employees): array
    {
        $employeeIds = $employees->pluck('id')->map(function ($id) {
            return (int) $id;
        })->values()->all();

        if (empty($employeeIds)) {
            return [];
        }

        $historyByEmployee = EmployeePayGradeHistory::query()
            ->with('payLevel')
            ->whereIn('employee_id', $employeeIds)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('employee_id');

        $result = [];

        foreach ($employees as $employee) {
            $employeeId = (int) $employee->id;
            $rows = collect($historyByEmployee->get($employeeId, []))
                ->reject(function ($row) {
                    return in_array((string) ($row->status ?? ''), [self::STATUS_PROPOSED, self::STATUS_RECOMMENDED, self::STATUS_REJECTED, self::STATUS_CANCELLED], true);
                })
                ->values();
            $preferred = $rows->firstWhere('status', 'active') ?: $rows->first();

            if ($preferred && $preferred->payLevel) {
                $result[$employeeId] = $this->displayPayLevelLabel($preferred->payLevel);
                continue;
            }

            $legacyLabel = $this->normalizeKhmerText($employee->employee_grade ?? '');
            $result[$employeeId] = $legacyLabel !== ''
                ? $this->resolveLegacyPayLevelLabel($legacyLabel)
                : '-';
        }

        return $result;
    }

    protected function buildPromotionPreviousLevelLabels(Collection $promotionRows): array
    {
        if ($promotionRows->isEmpty()) {
            return [];
        }

        $employeeIds = $promotionRows
            ->pluck('employee_id')
            ->filter(static fn ($id) => !empty($id))
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($employeeIds)) {
            return [];
        }

        $historyByEmployee = EmployeePayGradeHistory::query()
            ->with('payLevel')
            ->whereIn('employee_id', $employeeIds)
            ->orderBy('employee_id')
            ->orderBy('start_date')
            ->orderBy('id')
            ->get()
            ->groupBy(static fn ($row) => (int) ($row->employee_id ?? 0));

        $result = [];

        foreach ($promotionRows as $promotionRow) {
            $promotionId = (int) ($promotionRow->id ?? 0);
            if ($promotionId <= 0) {
                continue;
            }

            $employeeId = (int) ($promotionRow->employee_id ?? 0);
            $rows = collect($historyByEmployee->get($employeeId, []));
            if ($rows->isEmpty()) {
                $result[$promotionId] = '-';
                continue;
            }

            $currentStartDate = !empty($promotionRow->start_date)
                ? Carbon::parse((string) $promotionRow->start_date)->toDateString()
                : null;

            $previousRow = $rows
                ->filter(function ($row) use ($promotionRow, $currentStartDate) {
                    if ((int) ($row->id ?? 0) === (int) ($promotionRow->id ?? 0)) {
                        return false;
                    }

                    if (in_array((string) ($row->status ?? ''), [self::STATUS_PROPOSED, self::STATUS_RECOMMENDED, self::STATUS_REJECTED, self::STATUS_CANCELLED], true)) {
                        return false;
                    }

                    $rowStartDate = !empty($row->start_date)
                        ? Carbon::parse((string) $row->start_date)->toDateString()
                        : null;

                    if (empty($currentStartDate)) {
                        return (int) ($row->id ?? 0) < (int) ($promotionRow->id ?? 0);
                    }

                    if (!empty($rowStartDate) && $rowStartDate < $currentStartDate) {
                        return true;
                    }

                    if (!empty($rowStartDate) && $rowStartDate === $currentStartDate) {
                        return (int) ($row->id ?? 0) < (int) ($promotionRow->id ?? 0);
                    }

                    return false;
                })
                ->sortBy(function ($row) {
                    $rowStartDate = !empty($row->start_date)
                        ? Carbon::parse((string) $row->start_date)->toDateString()
                        : '0000-00-00';

                    return $rowStartDate . '|' . str_pad((string) ((int) ($row->id ?? 0)), 12, '0', STR_PAD_LEFT);
                })
                ->last();

            if ($previousRow && $previousRow->payLevel) {
                $result[$promotionId] = $this->displayPayLevelLabel($previousRow->payLevel);
            } else {
                $result[$promotionId] = '-';
            }
        }

        return $result;
    }

    protected function formatPayLevelLabel(GovPayLevel $payLevel): string
    {
        $nameKm = $this->sanitizePayLevelNameKm($payLevel->level_name_km);

        return trim((string) $payLevel->level_code . ($nameKm ? ' - ' . $nameKm : ''));
    }

    protected function displayPayLevelLabel(GovPayLevel $payLevel): string
    {
        $nameKm = $this->sanitizePayLevelNameKm($payLevel->level_name_km);
        if ($nameKm !== '') {
            return $nameKm;
        }

        return $this->normalizePayCodeToKhmer($payLevel->level_code);
    }

    protected function normalizePromotionType(?string $type): string
    {
        $value = trim((string) $type);
        if (in_array($value, ['regular', 'yearly_cycle', 'annual_grade'], true)) {
            return 'annual_grade';
        }
        if ($value === 'annual_rank') {
            return 'annual_rank';
        }
        if (in_array($value, ['special_request', 'degree_based'], true)) {
            return 'degree_based';
        }
        if (in_array($value, ['special_case', 'honorary_pre_retirement'], true)) {
            return 'honorary_pre_retirement';
        }
        if (in_array($value, ['annual_grade', 'annual_rank', 'degree_based', 'honorary_pre_retirement'], true)) {
            return $value;
        }

        return 'annual_grade';
    }

    protected function isAnnualCyclePromotionType(?string $type): bool
    {
        $value = $this->normalizePromotionType($type);
        return in_array($value, ['annual_grade', 'annual_rank'], true);
    }

    protected function isHonoraryPreRetirementPromotionType(?string $type): bool
    {
        $value = $this->normalizePromotionType($type);
        return $value === 'honorary_pre_retirement';
    }

    protected function pendingPromotionStatuses(): array
    {
        return [
            self::STATUS_PROPOSED,
            self::STATUS_RECOMMENDED,
        ];
    }

    protected function allowedProposalStatusesForAction(string $action): array
    {
        $normalizedAction = in_array($action, ['recommend', 'approve', 'reject'], true)
            ? $action
            : 'recommend';

        return match ($normalizedAction) {
            'recommend' => [self::STATUS_PROPOSED],
            'approve' => [self::STATUS_RECOMMENDED],
            'reject' => $this->pendingPromotionStatuses(),
            default => $this->pendingPromotionStatuses(),
        };
    }

    protected function proposalAllowsAction(EmployeePayGradeHistory $proposal, string $action): bool
    {
        $status = strtolower(trim((string) ($proposal->status ?? '')));

        return in_array($status, $this->allowedProposalStatusesForAction($action), true);
    }

    protected function isSpecialPromotionType(?string $type): bool
    {
        $normalized = $this->normalizePromotionType($type);

        return in_array($normalized, ['degree_based', 'honorary_pre_retirement'], true);
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<string, int>}
     */
    protected function buildPendingProposalPermissionMap(
        Collection $pendingProposals,
        OrgUnitRuleService $orgUnitRuleService
    ): array {
        $user = auth()->user();
        $map = [];
        $summary = [
            'total' => 0,
            'can_recommend' => 0,
            'can_approve' => 0,
            'can_reject' => 0,
            'blocked' => 0,
        ];

        foreach ($pendingProposals as $proposal) {
            $proposalId = (int) ($proposal->id ?? 0);
            if ($proposalId <= 0) {
                continue;
            }

            $summary['total']++;

            $employee = $proposal->employee;
            if (!$employee) {
                $summary['blocked']++;
                $map[$proposalId] = [
                    'can_recommend' => false,
                    'can_approve' => false,
                    'can_reject' => false,
                    'can_any' => false,
                    'blocked_message' => localize(
                        'proposal_employee_not_found',
                        'Cannot process this request because employee record is missing.'
                    ),
                ];
                continue;
            }

            $promotionType = $this->normalizePromotionType((string) ($proposal->promotion_type ?? 'annual_grade'));
            $canRecommendByRole = $this->canRecommendEmployeeAction(
                $user,
                $employee,
                $orgUnitRuleService,
                $promotionType,
                'recommend'
            );
            $canApproveByRole = $this->canFinalApprovePromotionAction(
                $user,
                $employee,
                $promotionType,
                $orgUnitRuleService
            );
            $canRejectByRole = $this->canRecommendEmployeeAction(
                $user,
                $employee,
                $orgUnitRuleService,
                $promotionType,
                'reject'
            );
            $stageCanRecommend = $this->proposalAllowsAction($proposal, 'recommend');
            $stageCanApprove = $this->proposalAllowsAction($proposal, 'approve');
            $stageCanReject = $this->proposalAllowsAction($proposal, 'reject');

            $canRecommend = $stageCanRecommend && $canRecommendByRole;
            $canApprove = $stageCanApprove && $canApproveByRole;
            $canReject = $stageCanReject && $canRejectByRole;

            $recommendReason = !$stageCanRecommend
                ? localize('recommend_only_from_proposed', 'Recommend is allowed only when request status is Proposed.')
                : ($canRecommendByRole
                    ? ''
                    : $this->permissionReasonForPromotionAction(
                        $user,
                        $employee,
                        $promotionType,
                        'recommend',
                        $orgUnitRuleService
                    ));
            $approveReason = !$stageCanApprove
                ? localize('approve_only_from_recommended', 'Approve is allowed only when request status is Recommended.')
                : ($canApproveByRole
                    ? ''
                    : $this->permissionReasonForPromotionAction(
                        $user,
                        $employee,
                        $promotionType,
                        'approve',
                        $orgUnitRuleService
                    ));
            $rejectReason = !$stageCanReject
                ? localize('reject_not_allowed_for_status', 'Reject is not allowed for current request status.')
                : ($canRejectByRole
                    ? ''
                    : $this->permissionReasonForPromotionAction(
                        $user,
                        $employee,
                        $promotionType,
                        'reject',
                        $orgUnitRuleService
                    ));
            $canAny = $canRecommend || $canApprove || $canReject;

            if ($canRecommend) {
                $summary['can_recommend']++;
            }
            if ($canApprove) {
                $summary['can_approve']++;
            }
            if ($canReject) {
                $summary['can_reject']++;
            }
            if (!$canAny) {
                $summary['blocked']++;
            }

            $map[$proposalId] = [
                'can_recommend' => $canRecommend,
                'can_approve' => $canApprove,
                'can_reject' => $canReject,
                'recommend_reason' => $recommendReason,
                'approve_reason' => $approveReason,
                'reject_reason' => $rejectReason,
                'can_any' => $canAny,
                'blocked_message' => trim(implode(' | ', array_filter([
                    !$canRecommend && $recommendReason !== ''
                        ? localize('cannot_recommend', 'Cannot recommend') . ': ' . $recommendReason
                        : '',
                    !$canApprove && $approveReason !== ''
                        ? localize('cannot_approve', 'Cannot approve') . ': ' . $approveReason
                        : '',
                    !$canReject && $rejectReason !== ''
                        ? localize('cannot_reject', 'Cannot reject') . ': ' . $rejectReason
                        : '',
                ]))),
            ];
        }

        return [$map, $summary];
    }

    protected function canRecommendEmployeeAction(
        ?User $user,
        Employee $employee,
        OrgUnitRuleService $orgUnitRuleService,
        ?string $promotionType = null,
        string $action = 'recommend'
    ): bool {
        return $this->canPromotionActionByRole(
            $user,
            $employee,
            $promotionType,
            $action,
            $orgUnitRuleService
        );
    }

    protected function canPromotionActionByRole(
        ?User $user,
        Employee $employee,
        ?string $promotionType,
        string $action,
        OrgUnitRuleService $orgUnitRuleService
    ): bool {
        $orgAccessService = app(OrgHierarchyAccessService::class);
        if ($orgAccessService->isSystemAdmin($user)) {
            return true;
        }

        $employeeUnitId = $this->employeeAssignedUnitId($employee);
        if (!$employeeUnitId) {
            return false;
        }

        $normalizedAction = in_array($action, ['recommend', 'approve', 'reject'], true) ? $action : 'recommend';

        // Backward compatibility when org roles are not configured yet.
        if (!$orgAccessService->hasAnyOrgRoleAssignment($user)) {
            if ($normalizedAction === 'approve') {
                return $orgAccessService->canApproveDepartment($user, $employeeUnitId);
            }

            $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);
            if (!is_array($managedBranchIds)) {
                return true;
            }
            return in_array($employeeUnitId, $managedBranchIds, true);
        }

        $requiredRoles = $this->requiredOrgRolesForPromotionAction($promotionType, $normalizedAction);

        return $this->userHasOrgRoleInDepartmentScope($user, $employeeUnitId, $requiredRoles, $orgUnitRuleService);
    }

    protected function canFinalApprovePromotionAction(
        ?User $user,
        Employee $employee,
        ?string $promotionType,
        OrgUnitRuleService $orgUnitRuleService
    ): bool {
        return $this->canPromotionActionByRole(
            $user,
            $employee,
            $promotionType,
            'approve',
            $orgUnitRuleService
        );
    }

    protected function requiredOrgRolesForPromotionAction(?string $promotionType, string $action): array
    {
        $normalizedType = $this->normalizePromotionType($promotionType);
        $normalizedAction = in_array($action, ['recommend', 'approve', 'reject'], true) ? $action : 'recommend';

        $roles = config("hr_workflow.promotion.{$normalizedType}.{$normalizedAction}");
        if (!is_array($roles) || empty($roles)) {
            $roles = config("hr_workflow.promotion._default.{$normalizedAction}");
        }

        if (!is_array($roles) || empty($roles)) {
            $roles = $this->defaultRequiredRolesForPromotionAction($promotionType, $normalizedAction);
        }

        return collect($roles)
            ->filter(fn ($role) => is_string($role) && trim($role) !== '')
            ->map(fn ($role) => trim((string) $role))
            ->unique()
            ->values()
            ->all();
    }

    protected function defaultRequiredRolesForPromotionAction(?string $promotionType, string $action): array
    {
        if ($action === 'approve') {
            return $this->isSpecialPromotionType($promotionType)
                ? [UserOrgRole::ROLE_HEAD]
                : [UserOrgRole::ROLE_HEAD, UserOrgRole::ROLE_DEPUTY_HEAD];
        }

        return [
            UserOrgRole::ROLE_MANAGER,
            UserOrgRole::ROLE_DEPUTY_HEAD,
            UserOrgRole::ROLE_HEAD,
        ];
    }

    protected function permissionReasonForPromotionAction(
        ?User $user,
        Employee $employee,
        ?string $promotionType,
        string $action,
        OrgUnitRuleService $orgUnitRuleService
    ): string {
        $orgAccessService = app(OrgHierarchyAccessService::class);
        if ($orgAccessService->isSystemAdmin($user)) {
            return '';
        }

        if (!$user) {
            return localize('user_not_authenticated', 'User is not authenticated.');
        }

        $employeeUnitId = $this->employeeAssignedUnitId($employee);
        if (!$employeeUnitId) {
            return localize('employee_unit_not_set', 'Employee has no assigned unit.');
        }

        $normalizedAction = in_array($action, ['recommend', 'approve', 'reject'], true) ? $action : 'recommend';
        $requiredRoles = $this->requiredOrgRolesForPromotionAction($promotionType, $normalizedAction);

        if (!$orgAccessService->hasAnyOrgRoleAssignment($user)) {
            if ($normalizedAction === 'approve') {
                if (!$orgAccessService->canApproveDepartment($user, $employeeUnitId)) {
                    return localize(
                        'fallback_user_not_approver_in_unit_scope',
                        'Fallback mode: you are not an approver in this unit scope.'
                    );
                }
                return '';
            }

            $managedBranchIds = $this->managedBranchIds($orgUnitRuleService);
            if (!is_array($managedBranchIds) || in_array($employeeUnitId, $managedBranchIds, true)) {
                return '';
            }

            return localize(
                'fallback_employee_outside_managed_hierarchy',
                'Fallback mode: employee is outside your managed hierarchy.'
            );
        }

        $effectiveRoles = $orgAccessService->effectiveOrgRoles($user);
        if ($effectiveRoles->isEmpty()) {
            return localize('no_active_org_role_assignment', 'No active org role assignment.');
        }

        $matchingRoles = $effectiveRoles->filter(function (UserOrgRole $role) use ($requiredRoles) {
            return in_array((string) $role->org_role, $requiredRoles, true);
        });

        if ($matchingRoles->isEmpty()) {
            return localize(
                'required_org_role_missing_for_action',
                'Required role missing for this action.'
            ) . ' ' . localize('required_roles', 'Required roles') . ': ' . $this->formatOrgRoleList($requiredRoles);
        }

        foreach ($matchingRoles as $role) {
            if (in_array($employeeUnitId, $this->roleScopeBranchIds($role, $orgUnitRuleService), true)) {
                return '';
            }
        }

        return localize(
            'role_exists_but_outside_scope',
            'Your org role exists but the employee unit is outside your scope.'
        );
    }

    protected function formatOrgRoleList(array $roles): string
    {
        $labels = [];
        foreach ($roles as $role) {
            $labels[] = match ((string) $role) {
                UserOrgRole::ROLE_HEAD => localize('org_role_head', 'Head'),
                UserOrgRole::ROLE_DEPUTY_HEAD => localize('org_role_deputy_head', 'Deputy head'),
                UserOrgRole::ROLE_MANAGER => localize('org_role_manager', 'Manager'),
                default => (string) $role,
            };
        }

        return implode(', ', array_values(array_unique(array_filter($labels))));
    }

    protected function userHasOrgRoleInDepartmentScope(
        ?User $user,
        int $departmentId,
        array $requiredRoles,
        OrgUnitRuleService $orgUnitRuleService
    ): bool {
        if (!$user || $departmentId <= 0) {
            return false;
        }

        $orgAccessService = app(OrgHierarchyAccessService::class);
        $roles = $orgAccessService->effectiveOrgRoles($user)
            ->filter(function (UserOrgRole $role) use ($requiredRoles) {
                return in_array((string) $role->org_role, $requiredRoles, true);
            });

        foreach ($roles as $role) {
            if (in_array($departmentId, $this->roleScopeBranchIds($role, $orgUnitRuleService), true)) {
                return true;
            }
        }

        return false;
    }

    protected function roleScopeBranchIds(UserOrgRole $role, OrgUnitRuleService $orgUnitRuleService): array
    {
        $departmentId = (int) ($role->department_id ?? 0);
        if ($departmentId <= 0) {
            return [];
        }

        $scopeType = (string) ($role->scope_type ?: UserOrgRole::SCOPE_SELF_AND_CHILDREN);
        if ($scopeType === UserOrgRole::SCOPE_SELF) {
            return [$departmentId];
        }

        return $orgUnitRuleService->branchIdsIncludingSelf($departmentId);
    }

    protected function normalizeKhmerText(?string $text): string
    {
        $value = trim((string) $text);
        if ($value === '') {
            return '';
        }

        // Common mojibake marker for Khmer UTF-8 interpreted as Windows-1252.
        $looksMojibake = str_contains($value, 'ÃƒÂ¡') || str_contains($value, 'ÃƒÂ¢') || str_contains($value, 'ÃƒÆ’');
        if (!$looksMojibake) {
            return $value;
        }

        $iconv = @iconv('Windows-1252', 'UTF-8//IGNORE', $value);
        if (is_string($iconv) && $iconv !== '' && preg_match('/\p{Khmer}/u', $iconv)) {
            return trim($iconv);
        }

        $mb = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
        if (is_string($mb) && $mb !== '' && preg_match('/\p{Khmer}/u', $mb)) {
            return trim($mb);
        }

        return $value;
    }

    protected function resolveLegacyPayLevelLabel(string $legacyLabel): string
    {
        $clean = trim($legacyLabel);
        if ($clean === '') {
            return '-';
        }

        static $kmByCode = null;
        if ($kmByCode === null) {
            $kmByCode = GovPayLevel::query()
                ->get(['level_code', 'level_name_km'])
                ->mapWithKeys(function ($row) {
                    $key = strtoupper(preg_replace('/\s+/', '', (string) $row->level_code));
                    return [$key => $this->sanitizePayLevelNameKm((string) $row->level_name_km)];
                })
                ->all();
        }

        $lookupKey = strtoupper(preg_replace('/\s+/', '', $clean));
        if (isset($kmByCode[$lookupKey]) && $kmByCode[$lookupKey] !== '') {
            return $kmByCode[$lookupKey];
        }

        return $this->normalizePayCodeToKhmer($clean);
    }

    protected function sanitizePayLevelNameKm(?string $name): string
    {
        $value = trim((string) $name);
        if ($value === '') {
            return '';
        }

        // Legacy import may contain duplicated dots like "Ã¡Å¾â€š..Ã¡Å¸Â¥".
        $value = preg_replace('/\.\.+/u', '.', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    protected function normalizePayCodeToKhmer(?string $code): string
    {
        $value = trim((string) $code);
        if ($value === '') {
            return '-';
        }

        $letterMap = [
            'A' => 'ក',
            'B' => 'ខ',
            'C' => 'គ',
            'D' => 'ឃ',
            'E' => 'ង',
            'F' => 'ច',
            'G' => 'ឆ',
            'H' => 'ជ',
        ];

        $digitMap = [
            '0' => '០',
            '1' => '១',
            '2' => '២',
            '3' => '៣',
            '4' => '៤',
            '5' => '៥',
            '6' => '៦',
            '7' => '៧',
            '8' => '៨',
            '9' => '៩',
        ];

        return strtr(strtoupper($value), $letterMap + $digitMap);
    }

    protected function buildPayPromotionChartData(
        int $year,
        Collection $employees,
        array $employeeSnapshots,
        ?array $managedBranchIds
    ): array {
        $promotedEmployeeIds = $this->promotionEmployeeIdsByStatusAndYear(
            $year,
            ['active', 'approved'],
            $managedBranchIds
        );

        $requestedEmployeeIds = $this->promotionEmployeeIdsByStatusAndYear(
            $year,
            $this->pendingPromotionStatuses(),
            $managedBranchIds
        );

        $overdueEmployeeIds = collect($employeeSnapshots)
            ->filter(fn ($snapshot) => (bool) ($snapshot['is_overdue_3y'] ?? false))
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $employeePool = $employees->keyBy('id');
        $needExtraIds = collect(array_merge($promotedEmployeeIds, $requestedEmployeeIds, $overdueEmployeeIds))
            ->unique()
            ->filter(fn ($id) => !$employeePool->has((int) $id))
            ->values()
            ->all();

        if (!empty($needExtraIds)) {
            $extraEmployees = Employee::query()
                ->whereIn('id', $needExtraIds)
                ->with(['department.unitType', 'sub_department.unitType'])
                ->get();

            foreach ($extraEmployees as $employee) {
                $employeePool->put((int) $employee->id, $employee);
            }
        }

        $levelDefinitions = [
            'phd_office' => [
                'label' => 'ទីចាត់ការមន្ទីរ',
                'codes' => ['phd', 'office', 'bureau', 'program'],
            ],
            'operational_district' => [
                'label' => 'ការិយាល័យស្រុកប្រតិបត្តិ',
                'codes' => ['operational_district', 'od_section', 'district_hospital'],
            ],
            'provincial_hospital' => [
                'label' => 'មន្ទីរពេទ្យខេត្ត',
                'codes' => ['provincial_hospital'],
            ],
            'health_center' => [
                'label' => 'មណ្ឌលសុខភាព',
                'codes' => ['health_center', 'health_center_with_bed', 'health_center_without_bed', 'health_post'],
            ],
        ];

        $levelStats = collect($levelDefinitions)->mapWithKeys(function ($def, $key) {
            return [
                $key => [
                    'label' => $def['label'],
                    'promoted' => 0,
                    'requested' => 0,
                    'overdue' => 0,
                ],
            ];
        })->all();

        $unitStats = [];
        $increase = function (int $employeeId, string $field) use (&$levelStats, &$unitStats, $employeePool, $levelDefinitions): void {
            $employee = $employeePool->get($employeeId);
            if (!$employee) {
                return;
            }

            $meta = $this->resolveEmployeeUnitMeta($employee);
            $unitId = (int) ($meta['unit_id'] ?? 0);
            $unitName = (string) ($meta['unit_name'] ?? '-');
            $typeCode = strtolower((string) ($meta['unit_type_code'] ?? ''));

            $levelKey = 'health_center';
            foreach ($levelDefinitions as $key => $definition) {
                if (in_array($typeCode, $definition['codes'], true)) {
                    $levelKey = $key;
                    break;
                }
            }

            $levelStats[$levelKey][$field] = (int) ($levelStats[$levelKey][$field] ?? 0) + 1;

            if (!isset($unitStats[$unitId])) {
                $unitStats[$unitId] = [
                    'label' => $unitName,
                    'promoted' => 0,
                    'requested' => 0,
                    'overdue' => 0,
                ];
            }

            $unitStats[$unitId][$field] = (int) ($unitStats[$unitId][$field] ?? 0) + 1;
        };

        foreach ($promotedEmployeeIds as $employeeId) {
            $increase((int) $employeeId, 'promoted');
        }
        foreach ($requestedEmployeeIds as $employeeId) {
            $increase((int) $employeeId, 'requested');
        }
        foreach ($overdueEmployeeIds as $employeeId) {
            $increase((int) $employeeId, 'overdue');
        }

        $topUnits = collect($unitStats)
            ->sortByDesc(function ($row) {
                return (int) ($row['promoted'] ?? 0) + (int) ($row['requested'] ?? 0) + (int) ($row['overdue'] ?? 0);
            })
            ->take(12)
            ->values()
            ->all();

        $trendYears = [];
        $trendPromoted = [];
        $trendRequested = [];
        foreach (range(max(1950, $year - 4), $year) as $trendYear) {
            $trendYears[] = (string) $trendYear;
            $trendPromoted[] = $this->promotionDistinctEmployeeCountByStatusAndYear(
                (int) $trendYear,
                ['active', 'approved'],
                $managedBranchIds
            );
            $trendRequested[] = $this->promotionDistinctEmployeeCountByStatusAndYear(
                (int) $trendYear,
                $this->pendingPromotionStatuses(),
                $managedBranchIds
            );
        }

        return [
            'status_labels' => ['បានឡើងកាំប្រាក់', 'សំណើសុំ', 'លើសឆ្នាំ'],
            'status_values' => [
                count($promotedEmployeeIds),
                count($requestedEmployeeIds),
                count($overdueEmployeeIds),
            ],
            'trend_labels' => $trendYears,
            'trend_promoted' => $trendPromoted,
            'trend_requested' => $trendRequested,
            'level_labels' => array_values(array_map(fn ($row) => $row['label'], $levelStats)),
            'level_promoted' => array_values(array_map(fn ($row) => (int) ($row['promoted'] ?? 0), $levelStats)),
            'level_requested' => array_values(array_map(fn ($row) => (int) ($row['requested'] ?? 0), $levelStats)),
            'level_overdue' => array_values(array_map(fn ($row) => (int) ($row['overdue'] ?? 0), $levelStats)),
            'unit_labels' => array_values(array_map(fn ($row) => (string) ($row['label'] ?? '-'), $topUnits)),
            'unit_promoted' => array_values(array_map(fn ($row) => (int) ($row['promoted'] ?? 0), $topUnits)),
            'unit_requested' => array_values(array_map(fn ($row) => (int) ($row['requested'] ?? 0), $topUnits)),
            'unit_overdue' => array_values(array_map(fn ($row) => (int) ($row['overdue'] ?? 0), $topUnits)),
        ];
    }

    protected function resolveEmployeeUnitMeta(Employee $employee): array
    {
        $unit = $employee->sub_department ?: $employee->department;
        if (!$unit) {
            return [
                'unit_id' => 0,
                'unit_name' => '-',
                'unit_type_code' => '',
            ];
        }

        return [
            'unit_id' => (int) $unit->id,
            'unit_name' => $this->normalizeKhmerText((string) ($unit->department_name ?? '-')),
            'unit_type_code' => (string) optional($unit->unitType)->code,
        ];
    }

    protected function promotionEmployeeIdsByStatusAndYear(int $year, array $statuses, ?array $managedBranchIds): array
    {
        $query = EmployeePayGradeHistory::query()
            ->whereYear('start_date', $year)
            ->whereIn('status', $statuses)
            ->orderByDesc('id');

        $this->applyManagedBranchScopeToPayHistoryQuery($query, $managedBranchIds);

        return $query->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function promotionDistinctEmployeeCountByStatusAndYear(int $year, array $statuses, ?array $managedBranchIds): int
    {
        $query = EmployeePayGradeHistory::query()
            ->whereYear('start_date', $year)
            ->whereIn('status', $statuses);

        $this->applyManagedBranchScopeToPayHistoryQuery($query, $managedBranchIds);

        return (int) $query->distinct('employee_id')->count('employee_id');
    }

    protected function applyManagedBranchScopeToPayHistoryQuery($query, ?array $managedBranchIds): void
    {
        if (!is_array($managedBranchIds)) {
            return;
        }

        if (empty($managedBranchIds)) {
            $query->whereRaw('1=0');
            return;
        }

        $query->whereHas('employee', function ($q) use ($managedBranchIds) {
            $q->whereIn('department_id', $managedBranchIds)
                ->orWhereIn('sub_department_id', $managedBranchIds);
        });
    }

    protected function resolveEmployeeDisplayUnitPath(Employee $employee): string
    {
        $chain = $this->employeeUnitChain($employee);
        if (empty($chain)) {
            return '-';
        }

        return implode(' | ', $chain);
    }

    protected function employeeUnitChain(Employee $employee): array
    {
        $currentId = (int) ($employee->sub_department_id ?: $employee->department_id ?: 0);
        if ($currentId <= 0) {
            return [];
        }

        $visited = [];
        $chain = [];
        $guard = 0;

        while ($currentId > 0 && $guard < 50) {
            if (isset($visited[$currentId])) {
                break;
            }
            $visited[$currentId] = true;

            $node = $this->employeeUnitNode($currentId);
            if (!$node) {
                break;
            }

            $name = trim((string) ($node['name'] ?? ''));
            if ($name !== '') {
                $chain[] = $this->normalizeKhmerText($name);
            }

            $currentId = (int) ($node['parent_id'] ?? 0);
            $guard++;
        }

        if (empty($chain)) {
            return [];
        }

        return array_values(array_unique(array_reverse($chain)));
    }

    protected function employeeUnitNode(int $departmentId): ?array
    {
        static $cache = [];

        if ($departmentId <= 0) {
            return null;
        }

        if (array_key_exists($departmentId, $cache)) {
            return $cache[$departmentId];
        }

        $unit = Department::withoutGlobalScopes()
            ->select(['id', 'department_name', 'parent_id'])
            ->find($departmentId);

        if (!$unit) {
            $cache[$departmentId] = null;
            return null;
        }

        $cache[$departmentId] = [
            'id' => (int) $unit->id,
            'name' => (string) ($unit->department_name ?? ''),
            'parent_id' => (int) ($unit->parent_id ?? 0),
        ];

        return $cache[$departmentId];
    }

    protected function managedBranchIds(OrgUnitRuleService $orgUnitRuleService): ?array
    {
        $user = auth()->user();
        $orgAccessService = app(OrgHierarchyAccessService::class);

        if ($orgAccessService->isSystemAdmin($user)) {
            return null;
        }

        if ($orgAccessService->hasAnyOrgRoleAssignment($user)) {
            return $orgAccessService->managedBranchIds($user);
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
            abort(403, localize('you_are_not_allowed_to_manage_employee_outside_assigned_units', 'You are not allowed to manage employees outside your assigned unit tree.'));
        }
    }

    protected function assertCanApproveEmployee(
        Employee $employee,
        OrgUnitRuleService $orgUnitRuleService,
        ?string $promotionType = null,
        string $action = 'approve'
    ): void
    {
        $user = auth()->user();
        $isAllowed = false;

        if ($action === 'approve') {
            $isAllowed = $this->canFinalApprovePromotionAction($user, $employee, $promotionType, $orgUnitRuleService);
            if (!$isAllowed) {
                abort(403, localize(
                    'you_are_not_allowed_to_final_approve_by_role_policy',
                    'You are not allowed to final-approve this request by current org-role policy.'
                ));
            }

            return;
        }

        // recommend/reject step
        $isAllowed = $this->canRecommendEmployeeAction($user, $employee, $orgUnitRuleService, $promotionType, $action);
        if (!$isAllowed) {
            abort(403, localize(
                'you_are_not_allowed_to_review_outside_assigned_hierarchy',
                'You are not allowed to review/recommend this request outside your assigned hierarchy.'
            ));
        }
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
}

