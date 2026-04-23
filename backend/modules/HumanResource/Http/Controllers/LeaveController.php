<?php

namespace Modules\HumanResource\Http\Controllers;

use App\Models\User;
use Brian2694\Toastr\Facades\Toastr;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Accounts\Entities\FinancialYear;
use Modules\HumanResource\DataTables\LeaveApplicationDataTable;
use Modules\HumanResource\Entities\ApplyLeave;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\LeaveType;
use Modules\HumanResource\Entities\LeaveTypeYear;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Entities\WeekHoliday;
use Modules\HumanResource\Entities\WorkflowInstance;
use Modules\HumanResource\Entities\WorkflowInstanceAction;
use Modules\HumanResource\Support\EmployeeServiceHistoryService;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Modules\HumanResource\Support\OrgUnitRuleService;
use Modules\HumanResource\Support\WorkflowActorResolverService;
use Modules\HumanResource\Support\WorkflowPolicyService;

class LeaveController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_weekly_holiday')->only('weekleave');
        $this->middleware('permission:update_weekly_holiday')->only(['weekleave_edit', 'weekleave_update']);
        $this->middleware('permission:read_leave_type')->only(['leaveTypeindex']);

        $this->middleware('permission:update_leave_generate')->only(['leaveGenerate', 'generateLeave']);
        $this->middleware('permission:read_leave_generate')->only(['generateLeaveDetail']);

        $this->middleware('permission:read_leave_application')->only('index', 'show');
        $this->middleware('permission:create_leave_application')->only('create');
        $this->middleware('permission:update_leave_application')->only('edit', 'update');
        $this->middleware('permission:delete_leave_application')->only('destroy');
        $this->middleware('permission:read_leave_application|create_leave_application|update_leave_application')->only('leaveBalance');

        $this->middleware('permission:create_leave_approval')->only(['approved', 'ApprovedByManager', 'reject']);
        $this->middleware('permission:read_leave_approval')->only('leaveApproval');
    }

    public function weekleave()
    {
        $dbData = WeekHoliday::all();
        return view('humanresource::leave.weekholiday', compact('dbData'));
    }
    public function weekleave_edit($uuid)
    {
        $dbData = WeekHoliday::where('uuid', $uuid)->first();
        $days = $dbData->dayname;
        $days = explode(',', $days);
        return view('humanresource::leave.weekholiday_edit', compact('dbData', 'days'));
    }

    public function weekleave_update(Request $request, WeekHoliday $weeklyholiday)
    {
        $request->validate([
            "dayname" => 'required',
        ]);

        $weeklyholiday->dayname = implode(",", $request->dayname);
        $weeklyholiday->save();

        Toastr::success('Update Successfully :)', 'Success');
        return redirect()->route('leave.weekleave.edit', $weeklyholiday->uuid);
    }
    public function leaveTypeindex()
    {
        $dbData = LeaveType::all();
        return view('humanresource::leave.leavetypeindex', [
            'dbData' => $dbData,
            'policyKeyOptions' => LeaveType::policyKeyOptions(),
            'scopeOptions' => LeaveType::entitlementScopeOptions(),
            'unitOptions' => LeaveType::entitlementUnitOptions(),
        ]);
    }
    public function leaveGenerate()
    {
        $yearData = LeaveTypeYear::all()->groupBy('academic_year_id');
        $yearKeys = $yearData->keys();
        $dbData = FinancialYear::findOrFail($yearKeys);
        $accYear = FinancialYear::where('status', 1)->get();
        return view('humanresource::leave.leavegenerateindex', compact('accYear', 'dbData'));
    }

    public function generateLeave(Request $request)
    {
        $request->validate([
            'academic_year_id' => 'required',
        ]);

        $yearId = $request->academic_year_id;

        $current_date_time = Carbon::now()->toDateTimeString();
        $employee = Employee::where('is_active', 1)->get();
        $leaveType = LeaveType::all();

        foreach ($employee as $eKey => $employeeValue) {
            foreach ($leaveType as $lkey => $leaveTypeValue) {
                $existingRecord = LeaveTypeYear::where('employee_id', $employeeValue->id)
                    ->where('academic_year_id', $yearId)
                    ->where('leave_type_id', $leaveTypeValue->id)
                    ->exists();

                if ($existingRecord) {
                    continue;
                }

                $insertData[$lkey]['employee_id'] = $employeeValue->id;
                $insertData[$lkey]['leave_type_id'] = $leaveTypeValue->id;
                $insertData[$lkey]['academic_year_id'] = $yearId;
                $insertData[$lkey]['entitled'] = $this->resolveEntitledDays($leaveTypeValue);
                $insertData[$lkey]['created_by'] = Auth::id();
                $insertData[$lkey]['created_at'] = $current_date_time;
                $insertData[$lkey]['updated_at'] = $current_date_time;
                $insertData[$lkey]['uuid'] = Str::uuid();
            }
            if (!empty($insertData)) {
                LeaveTypeYear::insert(array_values(array_filter($insertData)));
            }
            $insertData = [];
        }

        return redirect()->route('leave.leaveGenerate')->with('success', localize('leave_generate_successfully'));
    }

    public function generateLeaveDetail($yearId)
    {
        $dbData = LeaveTypeYear::where('academic_year_id', $yearId)->get();

        return view('humanresource::leave.leavegeneratedetail', compact('dbData'));
    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index(LeaveApplicationDataTable $dataTable)
    {
        $leaveTypes = LeaveType::all();
        $employees = Employee::where('is_active', 1)->get();
        return $dataTable->render('humanresource::leave.leaveapplication', compact('employees', 'leaveTypes'));
    }

    public function leaveBalance(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => ['required', 'integer', 'min:1'],
            'leave_type_id' => ['required', 'integer', 'min:1'],
            'start_date' => ['nullable', 'string', 'max:30'],
            'exclude_leave_uuid' => ['nullable', 'string', 'max:64'],
            'exclude_leave_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $leaveType = LeaveType::find((int) $validated['leave_type_id']);
        if (!$leaveType) {
            return response()->json([
                'ok' => false,
                'message' => localize('invalid_leave_type', 'Invalid leave type selected.'),
            ], 422);
        }

        $startDate = $this->normalizeDateInput((string) ($validated['start_date'] ?? '')) ?: now()->toDateString();
        $excludeLeaveId = null;

        if (!empty($validated['exclude_leave_id'])) {
            $excludeLeaveId = (int) $validated['exclude_leave_id'];
        } elseif (!empty($validated['exclude_leave_uuid'])) {
            $excludeLeaveId = (int) (ApplyLeave::withoutGlobalScopes()
                ->where('uuid', (string) $validated['exclude_leave_uuid'])
                ->value('id') ?? 0);
            if ($excludeLeaveId <= 0) {
                $excludeLeaveId = null;
            }
        }

        $scope = (string) ($leaveType->entitlement_scope ?? 'per_year');
        $entitled = $this->resolveEntitledDays($leaveType);
        $approvedTaken = 0;
        $pendingReserved = 0;
        $remaining = null;
        $financialYearId = null;
        $financialYearLabel = null;

        if ($this->isPerYearLeavePolicy($leaveType)) {
            $financialYear = $this->resolveFinancialYearByDate($startDate);
            $financialYearId = (int) $financialYear->id;
            $financialYearLabel = (string) ($financialYear->financial_year ?? '');

            $balance = $this->syncAndGetLeaveYearBalance(
                (int) $validated['employee_id'],
                (int) $validated['leave_type_id'],
                $financialYearId,
                $leaveType,
                $excludeLeaveId
            );

            $approvedTaken = (int) ($balance['approved_taken'] ?? 0);
            $pendingReserved = (int) ($balance['pending_reserved'] ?? 0);
            $remaining = (int) ($balance['remaining'] ?? 0);
            $entitled = (int) ($balance['entitled'] ?? $entitled);
        } elseif ($this->isPerServiceLifetimeLeavePolicy($leaveType)) {
            $approvedTaken = $this->sumApprovedLeaveDaysAllYears(
                (int) $validated['employee_id'],
                (int) $validated['leave_type_id'],
                $excludeLeaveId
            );
            $pendingReserved = $this->sumPendingLeaveDaysAllYears(
                (int) $validated['employee_id'],
                (int) $validated['leave_type_id'],
                $excludeLeaveId
            );
            $remaining = max($entitled - $approvedTaken - $pendingReserved, 0);
        } elseif ($this->isPerRequestLeavePolicy($leaveType)) {
            $remaining = $entitled;
        }

        return response()->json([
            'ok' => true,
            'scope' => $scope,
            'scope_label' => $this->leaveScopeLabel($scope),
            'unit' => (string) ($leaveType->entitlement_unit ?? 'day'),
            'unit_label' => $this->leaveUnitLabel((string) ($leaveType->entitlement_unit ?? 'day')),
            'entitled' => $entitled,
            'approved_taken' => $approvedTaken,
            'pending_reserved' => $pendingReserved,
            'remaining' => $remaining,
            'max_per_request' => $leaveType->max_per_request !== null
                ? (float) $leaveType->max_per_request
                : null,
            'financial_year_id' => $financialYearId,
            'financial_year_label' => $financialYearLabel,
        ]);
    }

    public function store(Request $request)
    {
        $path = '';

        $validated = $request->validate([
            'employee_id' => 'required',
            'leave_type_id' => 'required',
            'leave_apply_start_date' => 'required',
            'leave_apply_end_date' => 'required',
            'total_apply_day' => 'required',
            'reason' => 'nullable|string|max:2000',
            'location' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,rtf,jpeg,jpg,png,gif,svg|max:51200',

        ]);

        $leaveType = LeaveType::find((int) $validated['leave_type_id']);
        if (!$leaveType) {
            return redirect()->back()
                ->withErrors(['leave_type_id' => localize('invalid_leave_type', 'Invalid leave type selected.')])
                ->withInput();
        }

        $requiresAttachment = (bool) ($leaveType->requires_attachment ?? false)
            || (bool) ($leaveType->requires_medical_certificate ?? false);

        if ($requiresAttachment && !$request->hasFile('location')) {
            return redirect()->back()
                ->withErrors(['location' => localize('attachment_required', 'Attachment is required for this leave type.')])
                ->withInput();
        }

        $startDate = $this->normalizeDateInput((string) $request->leave_apply_start_date);
        $endDate = $this->normalizeDateInput((string) $request->leave_apply_end_date);

        if (!$startDate || !$endDate) {
            return redirect()->back()
                ->withErrors([
                    'leave_apply_start_date' => localize('invalid_date_format', 'Invalid date format.'),
                ])
                ->withInput();
        }

        $computedTotalDays = $this->calculateInclusiveDays($startDate, $endDate);
        if ($computedTotalDays <= 0) {
            return redirect()->back()
                ->withErrors([
                    'leave_apply_end_date' => localize('end_date_must_be_after_start_date', 'End date must be after start date.'),
                ])
                ->withInput();
        }

        $overlappingLeave = $this->findOverlappingLeave(
            (int) $validated['employee_id'],
            $startDate,
            $endDate
        );
        if ($overlappingLeave) {
            $overlapStart = (string) optional($overlappingLeave->leave_apply_start_date)->format('Y-m-d');
            $overlapEnd = (string) optional($overlappingLeave->leave_apply_end_date)->format('Y-m-d');

            return redirect()->back()
                ->withErrors([
                    'leave_apply_start_date' => localize('leave_date_overlap', 'Leave dates overlap with an existing request.')
                        . ' (' . $overlapStart . ' - ' . $overlapEnd . ')',
                ])
                ->withInput();
        }

        $financialYear = $this->resolveFinancialYearByDate($startDate);

        if ($request->hasFile('location')) {
            $request_file = $request->file('location');
            $filename = time() . rand(10, 1000) . '.' . $request_file->extension();
            $path = $request_file->storeAs('leave', $filename, 'public');
        }

        if ($this->isPerYearLeavePolicy($leaveType)) {
            $balance = $this->syncAndGetLeaveYearBalance(
                (int) $validated['employee_id'],
                (int) $validated['leave_type_id'],
                (int) $financialYear->id,
                $leaveType
            );

            if ($computedTotalDays > (int) $balance['remaining']) {
                return redirect()->back()
                    ->withErrors([
                        'total_apply_day' => localize('leave_balance_not_enough', 'Not enough leave balance.')
                            . ' ' . localize('remaining_days', 'Remaining') . ': '
                            . (int) $balance['remaining'] . ' ' . localize('day', 'day'),
                    ])
                    ->withInput();
            }
        } elseif ($this->isPerServiceLifetimeLeavePolicy($leaveType)) {
            $lifetimeEntitled = $this->resolveEntitledDays($leaveType);
            $lifetimeApproved = $this->sumApprovedLeaveDaysAllYears(
                (int) $validated['employee_id'],
                (int) $validated['leave_type_id']
            );
            $lifetimePending = $this->sumPendingLeaveDaysAllYears(
                (int) $validated['employee_id'],
                (int) $validated['leave_type_id']
            );
            $lifetimeRemaining = max($lifetimeEntitled - $lifetimeApproved - $lifetimePending, 0);

            if ($lifetimeEntitled > 0 && $computedTotalDays > $lifetimeRemaining) {
                return redirect()->back()
                    ->withErrors([
                        'total_apply_day' => localize('leave_balance_not_enough', 'Not enough leave balance.')
                            . ' ' . localize('remaining_days', 'Remaining') . ': '
                            . (int) $lifetimeRemaining . ' ' . localize('day', 'day'),
                    ])
                    ->withInput();
            }
        } elseif ($this->isPerRequestLeavePolicy($leaveType)) {
            $perRequestEntitled = $this->resolveEntitledDays($leaveType);
            if ($perRequestEntitled > 0 && $computedTotalDays > $perRequestEntitled) {
                return redirect()->back()
                    ->withErrors([
                        'total_apply_day' => localize('leave_request_exceeds_limit', 'Requested leave exceeds this leave type limit.')
                            . ' ' . localize('max_days', 'Max days') . ': '
                            . (int) $perRequestEntitled,
                    ])
                    ->withInput();
            }
        }

        $validated['leave_apply_start_date'] = $startDate;
        $validated['leave_apply_end_date'] = $endDate;
        $validated['academic_year_id'] = (int) $financialYear->id;
        $validated['total_apply_day'] = $computedTotalDays;
        $validated['location'] = $path;
        $validated['leave_apply_date'] = Carbon::now()->toDateTimeString();
        $validated['workflow_status'] = 'pending';

        $leave = ApplyLeave::create($validated);
        $this->initializeLeaveWorkflow($leave, Auth::id());

        return redirect()->route('leave.index')->with('success', localize('data_save'));
    }

    public function leaveApplicationEdit($id)
    {
        $row = ApplyLeave::findOrFail($id);
        $leaveTypes = LeaveType::all();
        $employees = Employee::where('is_active', 1)->get();
        return response()->view('humanresource::leave.livedit', compact('row', 'employees', 'leaveTypes'));
    }

    public function showApproveLeaveApplication($id)
    {
        $row = ApplyLeave::findOrFail($id);
        return response()->view('humanresource::leave.approveleave', compact('row'));
    }

    public function update(Request $request, ApplyLeave $leave)
    {
        $path = '';

        $validated = $request->validate([
            'employee_id' => 'required',
            'leave_type_id' => 'required',
            'leave_apply_start_date' => 'required',
            'leave_apply_end_date' => 'required',
            'total_apply_day' => 'required',
            'reason' => 'nullable|string|max:2000',
            'location' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,txt,rtf,jpeg,jpg,png,gif,svg|max:51200',

        ]);

        $leaveType = LeaveType::find((int) $validated['leave_type_id']);
        if (!$leaveType) {
            return redirect()->back()
                ->withErrors(['leave_type_id' => localize('invalid_leave_type', 'Invalid leave type selected.')])
                ->withInput();
        }

        $requiresAttachment = (bool) ($leaveType->requires_attachment ?? false)
            || (bool) ($leaveType->requires_medical_certificate ?? false);

        $hasAttachment = $request->hasFile('location') || !empty($request->oldlocation);
        if ($requiresAttachment && !$hasAttachment) {
            return redirect()->back()
                ->withErrors(['location' => localize('attachment_required', 'Attachment is required for this leave type.')])
                ->withInput();
        }

        $startDate = $this->normalizeDateInput((string) $request->leave_apply_start_date);
        $endDate = $this->normalizeDateInput((string) $request->leave_apply_end_date);

        if (!$startDate || !$endDate) {
            return redirect()->back()
                ->withErrors([
                    'leave_apply_start_date' => localize('invalid_date_format', 'Invalid date format.'),
                ])
                ->withInput();
        }

        $computedTotalDays = $this->calculateInclusiveDays($startDate, $endDate);
        if ($computedTotalDays <= 0) {
            return redirect()->back()
                ->withErrors([
                    'leave_apply_end_date' => localize('end_date_must_be_after_start_date', 'End date must be after start date.'),
                ])
                ->withInput();
        }

        $overlappingLeave = $this->findOverlappingLeave(
            (int) $validated['employee_id'],
            $startDate,
            $endDate,
            (int) $leave->id
        );
        if ($overlappingLeave) {
            $overlapStart = (string) optional($overlappingLeave->leave_apply_start_date)->format('Y-m-d');
            $overlapEnd = (string) optional($overlappingLeave->leave_apply_end_date)->format('Y-m-d');

            return redirect()->back()
                ->withErrors([
                    'leave_apply_start_date' => localize('leave_date_overlap', 'Leave dates overlap with an existing request.')
                        . ' (' . $overlapStart . ' - ' . $overlapEnd . ')',
                ])
                ->withInput();
        }

        $financialYear = $this->resolveFinancialYearByDate($startDate);

        if ($this->isPerYearLeavePolicy($leaveType)) {
            $balance = $this->syncAndGetLeaveYearBalance(
                (int) $validated['employee_id'],
                (int) $validated['leave_type_id'],
                (int) $financialYear->id,
                $leaveType,
                (int) $leave->id
            );

            if ($computedTotalDays > (int) $balance['remaining']) {
                return redirect()->back()
                    ->withErrors([
                        'total_apply_day' => localize('leave_balance_not_enough', 'Not enough leave balance.')
                            . ' ' . localize('remaining_days', 'Remaining') . ': '
                            . (int) $balance['remaining'] . ' ' . localize('day', 'day'),
                    ])
                    ->withInput();
            }
        } elseif ($this->isPerServiceLifetimeLeavePolicy($leaveType)) {
            $lifetimeEntitled = $this->resolveEntitledDays($leaveType);
            $lifetimeApproved = $this->sumApprovedLeaveDaysAllYears(
                (int) $validated['employee_id'],
                (int) $validated['leave_type_id'],
                (int) $leave->id
            );
            $lifetimePending = $this->sumPendingLeaveDaysAllYears(
                (int) $validated['employee_id'],
                (int) $validated['leave_type_id'],
                (int) $leave->id
            );
            $lifetimeRemaining = max($lifetimeEntitled - $lifetimeApproved - $lifetimePending, 0);

            if ($lifetimeEntitled > 0 && $computedTotalDays > $lifetimeRemaining) {
                return redirect()->back()
                    ->withErrors([
                        'total_apply_day' => localize('leave_balance_not_enough', 'Not enough leave balance.')
                            . ' ' . localize('remaining_days', 'Remaining') . ': '
                            . (int) $lifetimeRemaining . ' ' . localize('day', 'day'),
                    ])
                    ->withInput();
            }
        } elseif ($this->isPerRequestLeavePolicy($leaveType)) {
            $perRequestEntitled = $this->resolveEntitledDays($leaveType);
            if ($perRequestEntitled > 0 && $computedTotalDays > $perRequestEntitled) {
                return redirect()->back()
                    ->withErrors([
                        'total_apply_day' => localize('leave_request_exceeds_limit', 'Requested leave exceeds this leave type limit.')
                            . ' ' . localize('max_days', 'Max days') . ': '
                            . (int) $perRequestEntitled,
                    ])
                    ->withInput();
            }
        }

        $validated['leave_apply_start_date'] = $startDate;
        $validated['leave_apply_end_date'] = $endDate;
        $validated['academic_year_id'] = (int) $financialYear->id;
        $validated['total_apply_day'] = $computedTotalDays;

        if ($request->hasFile('location')) {
            $request_file = $request->file('location');
            $filename = time() . rand(10, 1000) . '.' . $request_file->extension();
            $path = $request_file->storeAs('leave', $filename, 'public');
        } else {
            $path = $request->oldlocation;
        }

        $validated['location'] = $path;
        $validated['leave_apply_date'] = Carbon::now()->toDateTimeString();

        $leave->update($validated);
        $this->initializeLeaveWorkflow($leave->fresh(), (int) Auth::id());

        return redirect()->route('leave.index')->with('update', localize('data_update'));
    }

    public function approved(Request $request, ApplyLeave $leave)
    {
        $validated = $request->validate([
            'leave_approved_start_date' => 'required',
            'leave_approved_end_date' => 'required',
            'total_approved_day' => 'required',
            'description' => 'nullable|string',
            'decision_ref_no' => 'nullable|string|max:100',
            'decision_ref_date' => 'nullable',
            'decision_note' => 'nullable|string|max:2000',
        ]);

        $normalizedStartDate = $this->normalizeDateInput((string) $validated['leave_approved_start_date']);
        $normalizedEndDate = $this->normalizeDateInput((string) $validated['leave_approved_end_date']);
        $normalizedRefDate = !empty($validated['decision_ref_date'])
            ? $this->normalizeDateInput((string) $validated['decision_ref_date'])
            : null;

        if (!$normalizedStartDate || !$normalizedEndDate) {
            return redirect()->back()
                ->withErrors([
                    'leave_approved_start_date' => localize('invalid_date_format', 'Invalid date format.'),
                ])
                ->withInput();
        }

        if (!empty($validated['decision_ref_date']) && !$normalizedRefDate) {
            return redirect()->back()
                ->withErrors([
                    'decision_ref_date' => localize('invalid_date_format', 'Invalid date format.'),
                ])
                ->withInput();
        }

        $validated['leave_approved_start_date'] = $normalizedStartDate;
        $validated['leave_approved_end_date'] = $normalizedEndDate;
        $validated['decision_ref_date'] = $normalizedRefDate;

        $result = $this->applyWorkflowApprovalDecision(
            $leave,
            $validated,
            Auth::user(),
            false,
            (string) $request->input('decision_action', 'approve')
        );

        if (!($result['ok'] ?? false)) {
            Toastr::warning((string) ($result['message'] ?? localize('permission_denied', 'Permission denied.')));
            return redirect()->back();
        }

        if (($result['final'] ?? false) === true) {
            return redirect()->route('leave.index')->with('success', localize('leave_approved_successfully'));
        }

        return redirect()->route('leave.index')->with('success', localize('leave_sent_to_next_approver', 'Leave sent to next approver.'));
    }

    public function leaveApproval()
    {
        $query = ApplyLeave::query()
            ->with(['employee.department', 'leaveType', 'workflowInstance.definition.steps.systemRole'])
            ->where('is_approved', false)
            ->where(function ($query) {
                $query->where('workflow_status', 'pending')
                    ->orWhereNull('workflow_status')
                    ->orWhere('workflow_status', 'draft');
            });

        $currentUser = Auth::user();
        $perPage = 30;

        if ($this->orgHierarchyAccessService()->isSystemAdmin($currentUser)) {
            $leaves = $query->paginate($perPage);
            $canApproveMap = [];
            foreach ($leaves as $leave) {
                $canApproveMap[(int) $leave->id] = true;
            }
        } else {
            $allPendingLeaves = $query->get();
            $filteredLeaves = $allPendingLeaves->filter(function ($leave) use ($currentUser) {
                return $this->canCurrentUserActOnLeave($leave, $currentUser);
            })->values();

            $currentPage = LengthAwarePaginator::resolveCurrentPage() ?: 1;
            $items = $filteredLeaves->forPage($currentPage, $perPage)->values();

            $leaves = new LengthAwarePaginator(
                $items,
                $filteredLeaves->count(),
                $perPage,
                $currentPage,
                [
                    'path' => request()->url(),
                    'query' => request()->query(),
                ]
            );

            $canApproveMap = [];
            foreach ($items as $leave) {
                $canApproveMap[(int) $leave->id] = true;
            }
        }

        return view('humanresource::leave.leave-approval', [
            'leaves' => $leaves,
            'canApproveMap' => $canApproveMap,
        ]);
    }

    public function ApprovedByManager(Request $request, $uuid)
    {

        $request->validate([
            "leave_approved_start_date" => 'required',
            "leave_approved_end_date" => 'required',
            "total_approved_day" => 'required',
            "description" => 'nullable|string',
            'decision_ref_no' => 'nullable|string|max:100',
            'decision_ref_date' => 'nullable',
            'decision_note' => 'nullable|string|max:2000',
        ]);

        $leave = ApplyLeave::where('uuid', $uuid)->firstOrFail();

        $normalizedStartDate = $this->normalizeDateInput((string) $request->input('leave_approved_start_date'));
        $normalizedEndDate = $this->normalizeDateInput((string) $request->input('leave_approved_end_date'));
        $normalizedRefDate = $request->filled('decision_ref_date')
            ? $this->normalizeDateInput((string) $request->input('decision_ref_date'))
            : null;

        if (!$normalizedStartDate || !$normalizedEndDate) {
            return redirect()->back()
                ->withErrors([
                    'leave_approved_start_date' => localize('invalid_date_format', 'Invalid date format.'),
                ])
                ->withInput();
        }

        if ($request->filled('decision_ref_date') && !$normalizedRefDate) {
            return redirect()->back()
                ->withErrors([
                    'decision_ref_date' => localize('invalid_date_format', 'Invalid date format.'),
                ])
                ->withInput();
        }

        $result = $this->applyWorkflowApprovalDecision(
            $leave,
            [
                'leave_approved_start_date' => $normalizedStartDate,
                'leave_approved_end_date' => $normalizedEndDate,
                'total_approved_day' => $request->input('total_approved_day'),
                'description' => $request->input('description'),
                'decision_ref_no' => $request->input('decision_ref_no'),
                'decision_ref_date' => $normalizedRefDate,
                'decision_note' => $request->input('decision_note'),
            ],
            Auth::user(),
            true,
            (string) $request->input('decision_action', 'approve')
        );

        if (!($result['ok'] ?? false)) {
            Toastr::warning((string) ($result['message'] ?? localize('permission_denied', 'Permission denied.')));
            return redirect()->back();
        }

        Toastr::success(
            ($result['final'] ?? false)
                ? localize('leave_approved_successfully', 'Leave approved successfully.')
                : localize('leave_sent_to_next_approver', 'Leave sent to next approver.'),
            'Success'
        );

        return redirect()->route('leave.approval')->with('update', localize('data_update'));
    }

    public function reject(Request $request, ApplyLeave $leave)
    {
        $validated = $request->validate([
            'reject_reason' => ['required', 'string', 'max:2000'],
        ]);

        $result = $this->applyWorkflowApprovalDecision(
            $leave,
            [
                'description' => (string) $validated['reject_reason'],
                'decision_note' => (string) $validated['reject_reason'],
            ],
            Auth::user(),
            true,
            'reject'
        );

        if (!($result['ok'] ?? false)) {
            Toastr::warning((string) ($result['message'] ?? localize('permission_denied', 'Permission denied.')));
            return redirect()->back();
        }

        Toastr::success(localize('leave_rejected_successfully', 'Leave rejected successfully.'), 'Success');
        return redirect()->route('leave.approval')->with('update', localize('data_update'));
    }

    public function destroy(ApplyLeave $leave)
    {
        $leave->delete();
        Toastr::success('Leave Application Deleted successfully :)', 'Success');
        return response()->json(['success' => 'success']);
    }

    protected function normalizeDateInput(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        $value = trim($input);
        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'm/d/Y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);
                if ($date && $date->format($format) === $value) {
                    return $date->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                // Keep trying next format.
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function initializeLeaveWorkflow(ApplyLeave $leave, ?int $submittedBy = null): void
    {
        if ($leave->workflow_instance_id) {
            return;
        }

        $plan = $this->workflowPolicyService()->resolveAndBuild(
            'leave',
            'leave_request',
            $this->buildLeaveWorkflowContext($leave)
        );

        if (!$plan || empty($plan['steps'])) {
            $leave->update([
                'workflow_status' => 'pending',
                'workflow_current_step_order' => null,
                'workflow_last_action_at' => now(),
                'workflow_snapshot_json' => null,
            ]);
            return;
        }

        DB::transaction(function () use ($leave, $plan, $submittedBy): void {
            $steps = collect((array) ($plan['steps'] ?? []))->sortBy('step_order')->values();
            $firstStep = $steps->first();

            $instance = WorkflowInstance::create([
                'module_key' => 'leave',
                'request_type_key' => 'leave_request',
                'source_type' => ApplyLeave::class,
                'source_id' => (int) $leave->id,
                'workflow_definition_id' => (int) ($plan['definition_id'] ?? 0),
                'status' => 'pending',
                'current_step_order' => (int) ($firstStep['step_order'] ?? 1),
                'submitted_by' => $submittedBy ?: Auth::id(),
                'submitted_at' => now(),
                'context_json' => $this->buildLeaveWorkflowContext($leave),
            ]);

            WorkflowInstanceAction::create([
                'workflow_instance_id' => (int) $instance->id,
                'step_order' => 0,
                'action_type' => 'submit',
                'action_status' => 'submitted',
                'acted_by' => $submittedBy ?: Auth::id(),
                'acted_at' => now(),
                'decision_note' => localize('leave_submitted', 'Leave submitted'),
                'payload_json' => [
                    'leave_id' => (int) $leave->id,
                    'total_apply_day' => (int) $leave->total_apply_day,
                ],
            ]);

            $leave->update([
                'workflow_instance_id' => (int) $instance->id,
                'workflow_status' => 'pending',
                'workflow_current_step_order' => (int) ($firstStep['step_order'] ?? 1),
                'workflow_last_action_at' => now(),
                'workflow_snapshot_json' => $plan,
            ]);
        });
    }

    protected function applyWorkflowApprovalDecision(
        ApplyLeave $leave,
        array $validated,
        ?User $actor,
        bool $managerActionRoute,
        string $decision = 'approve'
    ): array {
        $decision = trim(mb_strtolower($decision));
        if (!in_array($decision, ['approve', 'reject', 'recommend'], true)) {
            $decision = 'approve';
        }

        if (!$actor) {
            return [
                'ok' => false,
                'message' => localize('authentication_required', 'Authentication required.'),
            ];
        }

        if (!$leave->workflow_instance_id) {
            $this->initializeLeaveWorkflow($leave, (int) $leave->created_by);
            $leave = $leave->fresh();
        }

        $instance = $leave->workflowInstance()
            ->with(['definition.steps.systemRole'])
            ->first();

        if (!$instance || !$instance->definition) {
            // Legacy fallback: keep old behavior if workflow is unavailable.
            if ($decision === 'reject') {
                $leave->update([
                    'is_approved' => 0,
                    'workflow_status' => 'rejected',
                    'workflow_last_action_at' => now(),
                    'manager_approved_description' => !empty($validated['decision_note'])
                        ? Str::limit((string) $validated['decision_note'], 250, '')
                        : (!empty($validated['description']) ? Str::limit((string) $validated['description'], 250, '') : null),
                ]);

                return [
                    'ok' => true,
                    'final' => true,
                ];
            }

            $leave->update([
                'is_approved_by_manager' => 1,
                'manager_approved_description' => (string) ($validated['description'] ?? ''),
                'approved_by_manager' => $actor->id,
                'manager_approved_date' => now(),
                'is_approved' => 1,
                'approved_by' => $actor->id,
                'leave_approved_start_date' => $validated['leave_approved_start_date'] ?? null,
                'leave_approved_end_date' => $validated['leave_approved_end_date'] ?? null,
                'total_approved_day' => $validated['total_approved_day'] ?? null,
                'leave_approved_date' => now(),
                'workflow_status' => 'approved',
                'workflow_last_action_at' => now(),
            ]);

            $this->updateLeaveEntitlementTaken($leave, (int) ($validated['total_approved_day'] ?? 0));
            $this->logLeaveApprovalHistory($leave, $validated);

            return [
                'ok' => true,
                'final' => true,
            ];
        }

        $currentStep = $this->resolveCurrentWorkflowStep($instance);

        if (!$currentStep) {
            return [
                'ok' => false,
                'message' => localize('workflow_step_not_found', 'Workflow step not found.'),
            ];
        }

        if (!$this->canUserActOnWorkflowStep($actor, $leave, $currentStep)) {
            return [
                'ok' => false,
                'message' => localize('not_allowed_for_current_step', 'You are not allowed to approve this step.'),
            ];
        }

        if ($decision === 'reject' && !(bool) ($currentStep->can_reject ?? true)) {
            return [
                'ok' => false,
                'message' => localize('reject_not_allowed_for_current_step', 'Reject is not allowed in the current step.'),
            ];
        }

        $nextStep = $this->resolveNextWorkflowStep($instance, (int) $currentStep->step_order);
        $leaveType = $leave->leaveType ?: LeaveType::find((int) ($leave->leave_type_id ?? 0));
        $approvedDays = (int) ($validated['total_approved_day'] ?? $leave->total_apply_day ?? 0);
        $requiresUpperReview = $this->requiresUpperReview($leaveType, $approvedDays);

        if ($decision === 'reject') {
            DB::transaction(function () use (
                $leave,
                $instance,
                $currentStep,
                $validated,
                $actor
            ): void {
                $note = !empty($validated['decision_note'])
                    ? (string) $validated['decision_note']
                    : (!empty($validated['description']) ? (string) $validated['description'] : null);

                WorkflowInstanceAction::create([
                    'workflow_instance_id' => (int) $instance->id,
                    'step_order' => (int) $currentStep->step_order,
                    'action_type' => 'reject',
                    'action_status' => 'rejected',
                    'acted_by' => (int) $actor->id,
                    'acted_at' => now(),
                    'decision_note' => $note,
                ]);

                $instance->update([
                    'status' => 'rejected',
                    'current_step_order' => (int) $currentStep->step_order,
                    'finalized_at' => now(),
                ]);

                $leave->update([
                    'is_approved' => 0,
                    'workflow_status' => 'rejected',
                    'workflow_current_step_order' => (int) $currentStep->step_order,
                    'workflow_last_action_at' => now(),
                    'manager_approved_description' => !empty($note) ? Str::limit((string) $note, 250, '') : null,
                ]);
            });

            return [
                'ok' => true,
                'final' => true,
            ];
        }

        if ($requiresUpperReview && !$this->orgHierarchyAccessService()->isSystemAdmin($actor)) {
            if (!$nextStep) {
                return [
                    'ok' => false,
                    'message' => localize(
                        'leave_requires_upper_review',
                        'This leave request exceeds allowed days and must be reviewed by an upper approver.'
                    ),
                ];
            }
            $decision = 'recommend';
        }

        $isFinal = (bool) $currentStep->is_final_approval || !$nextStep;
        if ($decision === 'recommend') {
            if (!$nextStep) {
                return [
                    'ok' => false,
                    'message' => localize('next_approver_not_found', 'Next approver is not configured.'),
                ];
            }
            $isFinal = false;
        }

        $actionType = $isFinal ? 'approve' : 'recommend';
        $actionStatus = $isFinal ? 'approved' : 'recommended';

        DB::transaction(function () use (
            $leave,
            $instance,
            $currentStep,
            $nextStep,
            $isFinal,
            $actionType,
            $actionStatus,
            $validated,
            $actor,
            $managerActionRoute
        ): void {
            $decisionNote = !empty($validated['decision_note'])
                ? (string) $validated['decision_note']
                : (!empty($validated['description']) ? (string) $validated['description'] : null);

            WorkflowInstanceAction::create([
                'workflow_instance_id' => (int) $instance->id,
                'step_order' => (int) $currentStep->step_order,
                'action_type' => $actionType,
                'action_status' => $actionStatus,
                'acted_by' => (int) $actor->id,
                'acted_at' => now(),
                'decision_ref_no' => !empty($validated['decision_ref_no']) ? (string) $validated['decision_ref_no'] : null,
                'decision_ref_date' => !empty($validated['decision_ref_date']) ? (string) $validated['decision_ref_date'] : null,
                'decision_note' => $decisionNote,
                'payload_json' => [
                    'leave_approved_start_date' => $validated['leave_approved_start_date'] ?? null,
                    'leave_approved_end_date' => $validated['leave_approved_end_date'] ?? null,
                    'total_approved_day' => (int) ($validated['total_approved_day'] ?? 0),
                    'manager_action_route' => $managerActionRoute,
                    'step_name' => (string) ($currentStep->step_name ?? ''),
                ],
            ]);

            $update = [
                'leave_approved_start_date' => $validated['leave_approved_start_date'] ?? null,
                'leave_approved_end_date' => $validated['leave_approved_end_date'] ?? null,
                'total_approved_day' => $validated['total_approved_day'] ?? null,
                'workflow_last_action_at' => now(),
            ];

            $isManagerStep = $this->resolveWorkflowStepRoleCode($currentStep) === UserOrgRole::ROLE_MANAGER;
            if ($isManagerStep) {
                $update['is_approved_by_manager'] = 1;
                $update['manager_approved_description'] = !empty($decisionNote)
                    ? Str::limit((string) $decisionNote, 250, '')
                    : null;
                $update['approved_by_manager'] = (int) $actor->id;
                $update['manager_approved_date'] = now();
            }

            if ($isFinal) {
                $instance->update([
                    'status' => 'approved',
                    'current_step_order' => (int) $currentStep->step_order,
                    'finalized_at' => now(),
                ]);

                $update['is_approved'] = 1;
                $update['approved_by'] = (int) $actor->id;
                $update['leave_approved_date'] = now();
                $update['workflow_status'] = 'approved';
                $update['workflow_current_step_order'] = (int) $currentStep->step_order;
            } else {
                $instance->update([
                    'status' => 'pending',
                    'current_step_order' => (int) $nextStep->step_order,
                ]);

                $update['workflow_status'] = 'pending';
                $update['workflow_current_step_order'] = (int) $nextStep->step_order;
            }

            $leave->update($update);
        });

        if ($isFinal) {
            $leave = $leave->fresh(['leaveType']);
            $this->updateLeaveEntitlementTaken($leave, (int) ($validated['total_approved_day'] ?? 0));
            $this->logLeaveApprovalHistory($leave, $validated);
        }

        return [
            'ok' => true,
            'final' => $isFinal,
        ];
    }

    protected function updateLeaveEntitlementTaken(ApplyLeave $leave, int $approvedDays): void
    {
        if ((int) ($leave->employee_id ?? 0) <= 0 || (int) ($leave->leave_type_id ?? 0) <= 0) {
            return;
        }

        $leaveType = $leave->leaveType ?: LeaveType::find((int) $leave->leave_type_id);
        if (!$leaveType || !$this->isPerYearLeavePolicy($leaveType)) {
            return;
        }

        $academicYearId = $this->normalizeAcademicYearIdForLeave($leave);
        if ($academicYearId <= 0) {
            return;
        }

        $entitled = $this->resolveEntitledDays($leaveType);
        $balance = LeaveTypeYear::firstOrCreate(
            [
                'employee_id' => (int) $leave->employee_id,
                'leave_type_id' => (int) $leave->leave_type_id,
                'academic_year_id' => $academicYearId,
            ],
            [
                'entitled' => $entitled,
                'taken' => 0,
            ]
        );

        $approvedTaken = $this->sumApprovedLeaveDays(
            (int) $leave->employee_id,
            (int) $leave->leave_type_id,
            $academicYearId
        );

        if ($balance->entitled === null || (int) $balance->entitled <= 0) {
            $balance->entitled = $entitled;
        }
        $balance->taken = max($approvedTaken, 0);
        $balance->save();
    }

    protected function logLeaveApprovalHistory(ApplyLeave $leave, array $validated): void
    {
        $leaveType = LeaveType::find($leave->leave_type_id);
        $isWithoutPay = $this->isLeaveWithoutPay($leaveType);
        $eventType = $isWithoutPay ? 'leave_without_pay' : 'leave_approved';
        $title = $isWithoutPay ? 'Leave without pay approved' : 'Leave approved';
        $eventDate = (string) ($validated['leave_approved_start_date'] ?? now()->toDateString());
        $details = sprintf(
            '%s | %s to %s | %s days',
            $leaveType?->display_name ?? 'Leave',
            (string) ($validated['leave_approved_start_date'] ?? ''),
            (string) ($validated['leave_approved_end_date'] ?? ''),
            (string) ($validated['total_approved_day'] ?? 0)
        );

        $this->historyService()->log(
            (int) $leave->employee_id,
            $eventType,
            $title,
            $details,
            $eventDate,
            null,
            null,
            'apply_leave',
            $leave->id,
            [
                'leave_type_id' => $leaveType?->id,
                'leave_code' => $leaveType?->leave_code,
                'total_approved_day' => (int) ($validated['total_approved_day'] ?? 0),
            ]
        );
    }

    protected function resolveCurrentWorkflowStep(WorkflowInstance $instance): ?\Modules\HumanResource\Entities\WorkflowDefinitionStep
    {
        $steps = $instance->definition?->steps;
        if (!$steps || $steps->isEmpty()) {
            return null;
        }

        $order = (int) ($instance->current_step_order ?? 0);
        if ($order <= 0) {
            return $steps->sortBy('step_order')->first();
        }

        return $steps->firstWhere('step_order', $order);
    }

    protected function resolveNextWorkflowStep(WorkflowInstance $instance, int $currentOrder): ?\Modules\HumanResource\Entities\WorkflowDefinitionStep
    {
        $steps = $instance->definition?->steps;
        if (!$steps || $steps->isEmpty()) {
            return null;
        }

        return $steps
            ->filter(fn ($step) => (int) $step->step_order > $currentOrder)
            ->sortBy('step_order')
            ->first();
    }

    protected function canCurrentUserActOnLeave(ApplyLeave $leave, ?User $user): bool
    {
        if (!$user || (int) ($leave->is_approved ?? 0) === 1) {
            return false;
        }

        if ($this->orgHierarchyAccessService()->isSystemAdmin($user)) {
            return true;
        }

        $instance = $leave->workflowInstance;
        if (!$instance || !$instance->definition) {
            // Legacy records fallback for approvers who have leave-approval permission.
            return $user->can('create_leave_approval');
        }

        $step = $this->resolveCurrentWorkflowStep($instance);
        if (!$step) {
            return false;
        }

        return $this->canUserActOnWorkflowStep($user, $leave, $step);
    }

    protected function canUserActOnWorkflowStep(
        User $user,
        ApplyLeave $leave,
        \Modules\HumanResource\Entities\WorkflowDefinitionStep $step
    ): bool {
        if ($this->orgHierarchyAccessService()->isSystemAdmin($user)) {
            return true;
        }

        $sourceDepartmentId = $this->resolveLeaveSourceDepartmentId($leave);

        return $this->workflowActorResolverService()->canUserActOnStep($user, $step, $sourceDepartmentId);
    }

    protected function resolveWorkflowStepRoleCode(\Modules\HumanResource\Entities\WorkflowDefinitionStep $step): string
    {
        if (method_exists($step, 'getEffectiveRoleCode')) {
            return (string) $step->getEffectiveRoleCode();
        }

        return (string) ($step->org_role ?? '');
    }

    protected function buildLeaveWorkflowContext(ApplyLeave $leave): array
    {
        $employee = $leave->employee()
            ->with([
                'department.unitType',
                'employee_type',
                'primaryUnitPosting.department.unitType',
                'primaryUnitPosting.position',
            ])
            ->first();

        $currentDepartment = $employee?->primaryUnitPosting?->department ?: $employee?->department;
        $currentDepartmentId = (int) ($employee?->primaryUnitPosting?->department_id
            ?: $employee?->sub_department_id
            ?: $employee?->department_id
            ?: 0);
        $currentPositionId = (int) ($employee?->primaryUnitPosting?->position_id
            ?: $employee?->position_id
            ?: 0);

        return [
            'days' => (float) ($leave->total_apply_day ?? 0),
            'employee_id' => (int) ($leave->employee_id ?? 0),
            'department_id' => $currentDepartmentId > 0 ? $currentDepartmentId : null,
            'position_id' => $currentPositionId > 0 ? $currentPositionId : null,
            'employee_type_id' => (int) ($employee?->employee_type_id ?? 0),
            'employee_type_code' => '',
            'org_unit_type_id' => (int) ($currentDepartment?->unit_type_id ?? 0),
            'org_unit_type_code' => (string) ($currentDepartment?->unitType?->code ?? ''),
            'is_full_right' => (bool) ($employee?->is_full_right_officer ?? false),
        ];
    }

    protected function resolveLeaveSourceDepartmentId(ApplyLeave $leave): int
    {
        $employee = $leave->employee()
            ->with('primaryUnitPosting:id,employee_id,department_id')
            ->first();

        if (!$employee) {
            return 0;
        }

        $postingDepartmentId = (int) ($employee->primaryUnitPosting?->department_id ?? 0);
        if ($postingDepartmentId > 0) {
            return $postingDepartmentId;
        }

        $subDepartmentId = (int) ($employee->sub_department_id ?? 0);
        if ($subDepartmentId > 0) {
            return $subDepartmentId;
        }

        return (int) ($employee->department_id ?? 0);
    }

    protected function calculateInclusiveDays(string $startDate, string $endDate): int
    {
        try {
            $start = Carbon::parse($startDate)->startOfDay();
            $end = Carbon::parse($endDate)->startOfDay();
        } catch (\Throwable $e) {
            return 0;
        }

        if ($end->lt($start)) {
            return 0;
        }

        return (int) $start->diffInDays($end) + 1;
    }

    protected function resolveFinancialYearByDate(string $date): FinancialYear
    {
        $year = (int) Carbon::parse($date)->format('Y');
        $yearText = (string) $year;

        $existing = FinancialYear::query()
            ->where('financial_year', $yearText)
            ->orWhere('financial_year', $yearText . '-' . ($year + 1))
            ->orWhere(function ($query) use ($date) {
                $query->whereDate('start_date', '<=', $date)
                    ->whereDate('end_date', '>=', $date);
            })
            ->orderBy('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return FinancialYear::create([
            'financial_year' => $yearText,
            'start_date' => $year . '-01-01',
            'end_date' => $year . '-12-31',
            'status' => 1,
            'is_close' => 0,
        ]);
    }

    protected function isPerYearLeavePolicy(?LeaveType $leaveType): bool
    {
        if (!$leaveType) {
            return false;
        }

        $scope = (string) ($leaveType->entitlement_scope ?? 'per_year');
        return $scope === 'per_year';
    }

    protected function isPerRequestLeavePolicy(?LeaveType $leaveType): bool
    {
        if (!$leaveType) {
            return false;
        }

        return (string) ($leaveType->entitlement_scope ?? '') === 'per_request';
    }

    protected function isPerServiceLifetimeLeavePolicy(?LeaveType $leaveType): bool
    {
        if (!$leaveType) {
            return false;
        }

        return (string) ($leaveType->entitlement_scope ?? '') === 'per_service_lifetime';
    }

    protected function normalizeAcademicYearIdForLeave(ApplyLeave $leave): int
    {
        $currentId = (int) ($leave->academic_year_id ?? 0);
        if ($currentId > 0 && FinancialYear::query()->where('id', $currentId)->exists()) {
            return $currentId;
        }

        $referenceDate = null;
        if (!empty($leave->leave_apply_start_date)) {
            $referenceDate = Carbon::parse($leave->leave_apply_start_date)->toDateString();
        } elseif (!empty($leave->leave_approved_start_date)) {
            $referenceDate = Carbon::parse($leave->leave_approved_start_date)->toDateString();
        } else {
            $referenceDate = now()->toDateString();
        }

        $financialYear = $this->resolveFinancialYearByDate($referenceDate);
        if ((int) $financialYear->id > 0 && (int) $leave->academic_year_id !== (int) $financialYear->id) {
            $leave->academic_year_id = (int) $financialYear->id;
            $leave->save();
        }

        return (int) $financialYear->id;
    }

    protected function resolveEntitledDays(LeaveType $leaveType): int
    {
        $value = $leaveType->entitlement_value;
        if ($value === null || $value === '') {
            $value = $leaveType->leave_days;
        }

        return max((int) round((float) $value), 0);
    }

    protected function requiresUpperReview(?LeaveType $leaveType, int $days): bool
    {
        if (!$leaveType || $days <= 0) {
            return false;
        }

        $maxPerRequest = (float) ($leaveType->max_per_request ?? 0);
        if ($maxPerRequest <= 0) {
            return false;
        }

        return (float) $days > $maxPerRequest;
    }

    protected function syncAndGetLeaveYearBalance(
        int $employeeId,
        int $leaveTypeId,
        int $academicYearId,
        LeaveType $leaveType,
        ?int $excludeLeaveId = null
    ): array {
        $entitled = $this->resolveEntitledDays($leaveType);

        $balance = LeaveTypeYear::firstOrCreate(
            [
                'employee_id' => $employeeId,
                'leave_type_id' => $leaveTypeId,
                'academic_year_id' => $academicYearId,
            ],
            [
                'entitled' => $entitled,
                'taken' => 0,
            ]
        );

        if ($balance->entitled === null || (int) $balance->entitled <= 0) {
            $balance->entitled = $entitled;
        }

        $approvedTaken = $this->sumApprovedLeaveDays($employeeId, $leaveTypeId, $academicYearId, $excludeLeaveId);
        $reservedPending = $this->sumPendingLeaveDays($employeeId, $leaveTypeId, $academicYearId, $excludeLeaveId);

        $balance->taken = max($approvedTaken, 0);
        $balance->save();

        $remaining = max(((int) $balance->entitled) - $approvedTaken - $reservedPending, 0);

        return [
            'entitled' => (int) $balance->entitled,
            'approved_taken' => $approvedTaken,
            'pending_reserved' => $reservedPending,
            'remaining' => $remaining,
        ];
    }

    protected function sumApprovedLeaveDays(
        int $employeeId,
        int $leaveTypeId,
        int $academicYearId,
        ?int $excludeLeaveId = null
    ): int {
        $query = ApplyLeave::query()
            ->withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('academic_year_id', $academicYearId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('is_approved', 1)
                    ->orWhere('workflow_status', 'approved');
            });

        if ($excludeLeaveId) {
            $query->where('id', '!=', $excludeLeaveId);
        }

        $sum = $query->sum(DB::raw('COALESCE(total_approved_day, total_apply_day, 0)'));
        return max((int) $sum, 0);
    }

    protected function sumPendingLeaveDays(
        int $employeeId,
        int $leaveTypeId,
        int $academicYearId,
        ?int $excludeLeaveId = null
    ): int {
        $query = ApplyLeave::query()
            ->withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('academic_year_id', $academicYearId)
            ->whereNull('deleted_at')
            ->where('is_approved', 0)
            ->where(function ($q) {
                $q->whereIn('workflow_status', ['pending', 'draft'])
                    ->orWhereNull('workflow_status');
            });

        if ($excludeLeaveId) {
            $query->where('id', '!=', $excludeLeaveId);
        }

        $sum = $query->sum(DB::raw('COALESCE(total_apply_day, 0)'));
        return max((int) $sum, 0);
    }

    protected function sumApprovedLeaveDaysAllYears(
        int $employeeId,
        int $leaveTypeId,
        ?int $excludeLeaveId = null
    ): int {
        $query = ApplyLeave::query()
            ->withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->where('is_approved', 1)
                    ->orWhere('workflow_status', 'approved');
            });

        if ($excludeLeaveId) {
            $query->where('id', '!=', $excludeLeaveId);
        }

        $sum = $query->sum(DB::raw('COALESCE(total_approved_day, total_apply_day, 0)'));
        return max((int) $sum, 0);
    }

    protected function sumPendingLeaveDaysAllYears(
        int $employeeId,
        int $leaveTypeId,
        ?int $excludeLeaveId = null
    ): int {
        $query = ApplyLeave::query()
            ->withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->whereNull('deleted_at')
            ->where('is_approved', 0)
            ->where(function ($q) {
                $q->whereIn('workflow_status', ['pending', 'draft'])
                    ->orWhereNull('workflow_status');
            });

        if ($excludeLeaveId) {
            $query->where('id', '!=', $excludeLeaveId);
        }

        $sum = $query->sum(DB::raw('COALESCE(total_apply_day, 0)'));
        return max((int) $sum, 0);
    }

    protected function leaveScopeLabel(string $scope): string
    {
        return match ($scope) {
            'per_year' => localize('scope_per_year', 'Per year'),
            'per_request' => localize('scope_per_request', 'Per request'),
            'per_service_lifetime' => localize('scope_per_service_lifetime', 'Per service lifetime'),
            'manual' => localize('scope_manual', 'Manual'),
            default => $scope,
        };
    }

    protected function leaveUnitLabel(string $unit): string
    {
        return match ($unit) {
            'month' => localize('month', 'month'),
            default => localize('day', 'day'),
        };
    }

    protected function findOverlappingLeave(
        int $employeeId,
        string $startDate,
        string $endDate,
        ?int $excludeLeaveId = null
    ): ?ApplyLeave {
        if ($employeeId <= 0 || empty($startDate) || empty($endDate)) {
            return null;
        }

        $query = ApplyLeave::query()
            ->withoutGlobalScopes()
            ->where('employee_id', $employeeId)
            ->whereNull('deleted_at')
            ->whereDate('leave_apply_start_date', '<=', $endDate)
            ->whereDate('leave_apply_end_date', '>=', $startDate)
            ->where(function ($q) {
                $q->where('is_approved', 1)
                    ->orWhereIn('workflow_status', ['pending', 'approved', 'draft'])
                    ->orWhereNull('workflow_status');
            });

        if ($excludeLeaveId) {
            $query->where('id', '!=', $excludeLeaveId);
        }

        return $query->orderByDesc('id')->first();
    }

    protected function workflowPolicyService(): WorkflowPolicyService
    {
        return app(WorkflowPolicyService::class);
    }

    protected function orgHierarchyAccessService(): OrgHierarchyAccessService
    {
        return app(OrgHierarchyAccessService::class);
    }

    protected function orgUnitRuleService(): OrgUnitRuleService
    {
        return app(OrgUnitRuleService::class);
    }

    protected function historyService(): EmployeeServiceHistoryService
    {
        return app(EmployeeServiceHistoryService::class);
    }

    protected function workflowActorResolverService(): WorkflowActorResolverService
    {
        return app(WorkflowActorResolverService::class);
    }

    protected function isLeaveWithoutPay(?LeaveType $leaveType): bool
    {
        if (!$leaveType) {
            return false;
        }

        if ((string) ($leaveType->policy_key ?? '') === 'unpaid') {
            return true;
        }

        $name = mb_strtolower(trim((string) $leaveType->leave_type . ' ' . (string) ($leaveType->leave_type_km ?? '')));
        $code = mb_strtolower(trim((string) $leaveType->leave_code));
        $keywords = [
            'without pay',
            'unpaid',
            'lwop',
            'គ្មានបៀវត្ស',
            'គ្មានបៀវត្ត',
        ];

        foreach ($keywords as $keyword) {
            if ($keyword !== '' && (str_contains($name, $keyword) || str_contains($code, $keyword))) {
                return true;
            }
        }

        return false;
    }

}

