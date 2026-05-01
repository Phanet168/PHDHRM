<?php

namespace Modules\Correspondence\Http\Controllers;

use App\Models\User;
use App\Notifications\CorrespondenceAssignedNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Correspondence\Entities\CorrespondenceLetterAction;
use Modules\Correspondence\Entities\CorrespondenceLetterDistribution;
use Modules\Correspondence\Entities\CorrespondenceLetter;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\OrgUnitType;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Support\ModuleTableGovernanceService;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Modules\HumanResource\Support\OrgRolePermissionService;
use Modules\HumanResource\Support\OrgUnitRuleService;
use Modules\Correspondence\Traits\CorrespondenceScope;

class CorrespondenceController extends Controller
{
    use CorrespondenceScope;

    protected const DASHBOARD_COUNT_CACHE_SECONDS = 45;
    protected const MANAGER_TEMPLATE_KEY = 'administration_office_manager';

    public function index(Request $request)
    {
        $level = $this->corrLevel();
        $filters = $this->resolveListFilters($request);
        $user = Auth::user();
        $userId = (int) ($user->id ?? 0);
        $scopeIds = $this->corrAccessibleDepartmentIds();
        $scopeSignature = $scopeIds === null ? 'all' : implode(',', array_map('intval', $scopeIds));
        $isAdmin = $user ? (int) $this->orgAccessService()->isSystemAdmin($user) : 0;
        $cacheKey = 'corr:dashboard:counts:' . $userId . ':' . $isAdmin . ':' . md5(
            $level
            . '|'
            . $scopeSignature
            . '|'
            . $filters['period']
            . '|'
            . ($filters['startDate']?->toDateString() ?? '')
            . '|'
            . ($filters['endDate']?->toDateString() ?? '')
        );

        $counts = Cache::remember($cacheKey, now()->addSeconds(self::DASHBOARD_COUNT_CACHE_SECONDS), function () use ($filters) {
            $incomingQuery = (clone $this->accessibleLettersQuery(CorrespondenceLetter::TYPE_INCOMING));
            $outgoingQuery = (clone $this->accessibleLettersQuery(CorrespondenceLetter::TYPE_OUTGOING));
            $pendingAckQuery = (clone $this->accessibleLettersQuery())
                ->where('status', CorrespondenceLetter::STATUS_PENDING);
            $inProgressQuery = (clone $this->accessibleLettersQuery())
                ->where('status', CorrespondenceLetter::STATUS_IN_PROGRESS);
            $completedQuery = (clone $this->accessibleLettersQuery())
                ->where('status', CorrespondenceLetter::STATUS_COMPLETED);

            $this->applyDateRange($incomingQuery, 'received_date', $filters['startDate'], $filters['endDate']);
            $this->applyDateRange($outgoingQuery, 'sent_date', $filters['startDate'], $filters['endDate']);
            $this->applyDateRange($pendingAckQuery, 'created_at', $filters['startDate'], $filters['endDate']);
            $this->applyDateRange($inProgressQuery, 'created_at', $filters['startDate'], $filters['endDate']);
            $this->applyDateRange($completedQuery, 'created_at', $filters['startDate'], $filters['endDate']);

            $incomingCount = $incomingQuery->count();
            $outgoingCount = $outgoingQuery->count();
            $pendingAckCount = $pendingAckQuery->count();
            $inProgressCount = $inProgressQuery->count();
            $completedCount = $completedQuery->count();

            return [
                'incomingCount' => $incomingCount,
                'outgoingCount' => $outgoingCount,
                'pendingCount' => $pendingAckCount,
                'pendingAckCount' => $pendingAckCount,
                'inProgressCount' => $inProgressCount,
                'completedCount' => $completedCount,
            ];
        });

        if ($this->shouldReturnApiResponse($request)) {
            $navPerms = $this->navPermissionData();
            // Keep API create flags aligned with backend create-role policy.
            $canCreateIncoming = (bool) ($navPerms['canCreateIncoming'] ?? false);
            $canCreateOutgoing = (bool) ($navPerms['canCreateOutgoing'] ?? false);
            $canCreateAny = $canCreateIncoming || $canCreateOutgoing;
            return $this->correspondenceApiResponse([
                'incoming_total' => (int) ($counts['incomingCount'] ?? 0),
                'outgoing_total' => (int) ($counts['outgoingCount'] ?? 0),
                'pending_ack_count' => (int) ($counts['pendingAckCount'] ?? $counts['pendingCount'] ?? 0),
                'in_progress_count' => (int) ($counts['inProgressCount'] ?? 0),
                'completed_count' => (int) ($counts['completedCount'] ?? 0),
                'permissions' => [
                    'corr_level' => (string) ($navPerms['corrLevel'] ?? ''),
                    'corr_level_label' => (string) ($navPerms['corrLevelLabel'] ?? ''),
                    'corr_department_name' => (string) ($navPerms['corrDepartmentName'] ?? ''),
                    // UI/mobile should use these flags to render create actions.
                    'can_create_incoming' => $canCreateIncoming,
                    'can_create_outgoing' => $canCreateOutgoing,
                    'can_create_any' => $canCreateAny,
                ],
            ]);
        }

        return view('correspondence::dashboard.index', [
            'level' => $level,
            'incomingCount' => (int) ($counts['incomingCount'] ?? 0),
            'outgoingCount' => (int) ($counts['outgoingCount'] ?? 0),
            'pendingCount' => (int) ($counts['pendingCount'] ?? 0),
            'completedCount' => (int) ($counts['completedCount'] ?? 0),
            'period' => $filters['period'],
            'startDate' => $filters['startDate']?->toDateString(),
            'endDate' => $filters['endDate']?->toDateString(),
        ] + $this->navPermissionData());
    }

    public function incoming(Request $request)
    {
        $level = $this->corrLevel();
        $filters = $this->resolveListFilters($request);
        $search = $filters['search'];
        $perPage = $filters['perPage'];

        $letters = $this->accessibleLettersQuery(CorrespondenceLetter::TYPE_INCOMING)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('registry_no', 'like', "%{$search}%")
                        ->orWhere('letter_no', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('from_org', 'like', "%{$search}%");
                });
            })
            ->when($filters['startDate'] || $filters['endDate'], function ($query) use ($filters) {
                $this->applyDateRange($query, 'received_date', $filters['startDate'], $filters['endDate']);
            })
            ->select([
                'id',
                'registry_no',
                'letter_no',
                'subject',
                'priority',
                'from_org',
                'to_org',
                'attachment_path',
                'status',
                'current_step',
                'letter_date',
                'received_date',
                'origin_department_id',
                'assigned_department_id',
            ])
            ->with([
                'originDepartment:id,department_name',
                'assignedDepartment:id,department_name',
                'currentHandler:id,full_name',
            ])
            ->latest('received_date')
            ->latest('letter_date')
            ->latest('id')
            ->paginate($perPage)
            ->appends($request->query());

        if ($this->shouldReturnApiResponse($request)) {
            return $this->correspondenceApiResponse($this->paginatedLetterPayload($letters));
        }

        return view('correspondence::incoming.index', [
            'level' => $level,
            'letters' => $letters,
            'search' => $search,
            'period' => $filters['period'],
            'startDate' => $filters['startDate']?->toDateString(),
            'endDate' => $filters['endDate']?->toDateString(),
            'perPage' => $perPage,
        ] + $this->navPermissionData());
    }

    public function outgoing(Request $request)
    {
        $level = $this->corrLevel();
        $filters = $this->resolveListFilters($request);
        $search = $filters['search'];
        $perPage = $filters['perPage'];

        $letters = $this->accessibleLettersQuery(CorrespondenceLetter::TYPE_OUTGOING)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('registry_no', 'like', "%{$search}%")
                        ->orWhere('letter_no', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('to_org', 'like', "%{$search}%");
                });
            })
            ->when($filters['startDate'] || $filters['endDate'], function ($query) use ($filters) {
                $this->applyDateRange($query, 'sent_date', $filters['startDate'], $filters['endDate']);
            })
            ->select([
                'id',
                'registry_no',
                'letter_no',
                'subject',
                'priority',
                'from_org',
                'to_org',
                'attachment_path',
                'status',
                'current_step',
                'letter_date',
                'sent_date',
                'origin_department_id',
                'assigned_department_id',
            ])
            ->with([
                'originDepartment:id,department_name',
                'assignedDepartment:id,department_name',
                'currentHandler:id,full_name',
                'distributions:id,letter_id,distribution_type,target_department_id,target_user_id',
                'distributions.targetDepartment:id,department_name',
                'distributions.targetUser:id,full_name,email',
            ])
            ->withCount([
                'distributions as distributions_total_count',
                'distributions as distributions_done_count' => function ($query) {
                    $query->whereIn('status', [
                        CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
                        CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
                        CorrespondenceLetterDistribution::STATUS_CLOSED,
                    ]);
                },
            ])
            ->latest('sent_date')
            ->latest('letter_date')
            ->latest('id')
            ->paginate($perPage)
            ->appends($request->query());

        if ($this->shouldReturnApiResponse($request)) {
            return $this->correspondenceApiResponse($this->paginatedLetterPayload($letters));
        }

        return view('correspondence::outgoing.index', [
            'level' => $level,
            'letters' => $letters,
            'search' => $search,
            'period' => $filters['period'],
            'startDate' => $filters['startDate']?->toDateString(),
            'endDate' => $filters['endDate']?->toDateString(),
            'perPage' => $perPage,
        ] + $this->navPermissionData());
    }

    public function settings()
    {
        $this->assertCanManageSettings();

        $managerTemplateId = 0;
        $managerTemplateName = '';

        if (Schema::hasTable('correspondence_responsibility_templates')) {
            $managerTemplate = DB::table('correspondence_responsibility_templates')
                ->where('template_key', self::MANAGER_TEMPLATE_KEY)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->first(['id', 'name', 'name_km']);

            if ($managerTemplate) {
                $managerTemplateId = (int) ($managerTemplate->id ?? 0);
                $managerTemplateName = (string) (($managerTemplate->name_km ?? '') !== ''
                    ? ($managerTemplate->name_km ?? '')
                    : ($managerTemplate->name ?? ''));
            }
        }

        $managerAssignments = collect();
        if ($managerTemplateId > 0 && Schema::hasTable('correspondence_user_responsibilities')) {
            $managerAssignments = DB::table('correspondence_user_responsibilities as cur')
                ->join('users as u', 'u.id', '=', 'cur.user_id')
                ->leftJoin('departments as d', 'd.id', '=', 'cur.department_id')
                ->where('cur.template_id', $managerTemplateId)
                ->whereNull('cur.deleted_at')
                ->where('cur.is_active', 1)
                ->orderByDesc('cur.is_primary')
                ->orderBy('u.full_name')
                ->get([
                    'cur.id',
                    'cur.user_id',
                    'cur.scope_type',
                    'cur.is_primary',
                    'u.full_name as user_name',
                    'u.email as user_email',
                    'd.department_name as department_name',
                ])
                ->map(function ($row): array {
                    return [
                        'id' => (int) ($row->id ?? 0),
                        'user_id' => (int) ($row->user_id ?? 0),
                        'user_name' => (string) ($row->user_name ?? ''),
                        'user_email' => (string) ($row->user_email ?? ''),
                        'department_name' => (string) ($row->department_name ?? ''),
                        'scope_type' => (string) ($row->scope_type ?? ''),
                        'is_primary' => (bool) ($row->is_primary ?? false),
                    ];
                })
                ->values();
        }

        $userOptions = User::query()
            ->where('is_active', 1)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'email']);

        $departmentOptions = Department::query()
            ->where('is_active', 1)
            ->orderBy('department_name')
            ->get(['id', 'department_name']);

        return view('correspondence::settings.index', [
            'managerTemplateId' => $managerTemplateId,
            'managerTemplateName' => $managerTemplateName,
            'managerAssignments' => $managerAssignments,
            'userOptions' => $userOptions,
            'departmentOptions' => $departmentOptions,
        ] + $this->navPermissionData());
    }

    public function assignSettingsUser(Request $request)
    {
        $this->assertCanManageSettings();

        $managerTemplateId = $this->resolveManagerTemplateId();
        if ($managerTemplateId <= 0 || !Schema::hasTable('correspondence_user_responsibilities')) {
            return redirect()
                ->back()
                ->with('error', localize('settings_not_ready', 'Settings table is not ready. Please run migration.'));
        }

        $validated = $request->validate([
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'nullable|integer|exists:users,id',
            'department_id' => 'nullable|integer|exists:departments,id',
            'is_primary' => 'nullable|boolean',
        ]);

        $userIds = collect($validated['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return redirect()
                ->back()
                ->with('error', localize('user_required', 'Please select at least one user.'));
        }

        $baseDepartmentId = (int) ($validated['department_id'] ?? 0);
        $requestPrimary = (bool) ($validated['is_primary'] ?? false);
        $savedCount = 0;

        if ($requestPrimary) {
            DB::table('correspondence_user_responsibilities')
                ->where('template_id', $managerTemplateId)
                ->whereNull('deleted_at')
                ->update([
                    'is_primary' => false,
                    'updated_at' => now(),
                ]);
        }

        foreach ($userIds as $index => $userId) {
            $departmentId = $baseDepartmentId > 0
                ? $baseDepartmentId
                : $this->resolveSettingDepartmentForUser((int) $userId);

            if ($departmentId <= 0) {
                continue;
            }

            $isPrimary = $requestPrimary && $index === 0;

            $existing = DB::table('correspondence_user_responsibilities')
                ->where('template_id', $managerTemplateId)
                ->where('user_id', (int) $userId)
                ->where('department_id', $departmentId)
                ->whereNull('deleted_at')
                ->first(['id']);

            if ($existing) {
                DB::table('correspondence_user_responsibilities')
                    ->where('id', (int) $existing->id)
                    ->update([
                        'scope_type' => 'self_and_children',
                        'is_primary' => $isPrimary,
                        'is_active' => true,
                        'updated_at' => now(),
                    ]);
            } else {
                DB::table('correspondence_user_responsibilities')->insert([
                    'uuid' => (string) Str::uuid(),
                    'user_id' => (int) $userId,
                    'employee_id' => Employee::query()->where('user_id', (int) $userId)->value('id'),
                    'department_id' => $departmentId,
                    'template_id' => $managerTemplateId,
                    'scope_type' => 'self_and_children',
                    'effective_from' => now()->toDateString(),
                    'effective_to' => null,
                    'is_primary' => $isPrimary,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $savedCount++;
        }

        if ($savedCount <= 0) {
            return redirect()->back()->with('error', localize('department_required', 'Department is required for assignment.'));
        }

        return redirect()->back()->with('success', localize('data_save', 'Saved successfully') . " ({$savedCount})");
    }

    protected function resolveManagerTemplateId(): int
    {
        if (!Schema::hasTable('correspondence_responsibility_templates')) {
            return 0;
        }

        return (int) (DB::table('correspondence_responsibility_templates')
            ->where('template_key', self::MANAGER_TEMPLATE_KEY)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->value('id') ?? 0);
    }

    public function removeSettingsUser(int $assignment)
    {
        $this->assertCanManageSettings();

        if (Schema::hasTable('correspondence_user_responsibilities')) {
            DB::table('correspondence_user_responsibilities')
                ->where('id', $assignment)
                ->whereNull('deleted_at')
                ->update([
                    'is_active' => false,
                    'deleted_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return redirect()->back()->with('success', localize('data_delete', 'Deleted successfully'));
    }

    protected function resolveListFilters(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $period = trim((string) $request->query('period', 'today'));
        $allowedPeriods = ['today', 'yesterday', 'this_week', 'this_month', 'all', 'custom'];

        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'today';
        }

        $today = Carbon::today();
        $startDate = null;
        $endDate = null;

        switch ($period) {
            case 'today':
                $startDate = $today->copy();
                $endDate = $today->copy();
                break;
            case 'yesterday':
                $startDate = $today->copy()->subDay();
                $endDate = $startDate->copy();
                break;
            case 'this_week':
                $startDate = $today->copy()->startOfWeek();
                $endDate = $today->copy()->endOfWeek();
                break;
            case 'this_month':
                $startDate = $today->copy()->startOfMonth();
                $endDate = $today->copy()->endOfMonth();
                break;
            case 'custom':
                $rawStart = trim((string) $request->query('start_date', ''));
                $rawEnd = trim((string) $request->query('end_date', ''));
                try {
                    $startDate = $rawStart !== '' ? Carbon::parse($rawStart) : null;
                } catch (\Throwable $throwable) {
                    $startDate = null;
                }
                try {
                    $endDate = $rawEnd !== '' ? Carbon::parse($rawEnd) : null;
                } catch (\Throwable $throwable) {
                    $endDate = null;
                }
                if (!$startDate && !$endDate) {
                    $period = 'today';
                    $startDate = $today->copy();
                    $endDate = $today->copy();
                }
                break;
            case 'all':
            default:
                $startDate = null;
                $endDate = null;
                break;
        }

        if ($startDate && $endDate && $startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $perPage = (int) $request->query('per_page', 20);
        if (!in_array($perPage, [10, 20, 50, 100], true)) {
            $perPage = 20;
        }

        return [
            'search' => $search,
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'perPage' => $perPage,
        ];
    }

    protected function applyDateRange($query, string $column, ?Carbon $startDate, ?Carbon $endDate): void
    {
        if (!$startDate && !$endDate) {
            return;
        }

        if ($startDate && $endDate) {
            $query->whereBetween($column, [$startDate->toDateString(), $endDate->toDateString()]);
            return;
        }

        if ($startDate) {
            $query->whereDate($column, '>=', $startDate->toDateString());
            return;
        }

        $query->whereDate($column, '<=', $endDate->toDateString());
    }

    public function create(string $type)
    {
        abort_unless(in_array($type, [CorrespondenceLetter::TYPE_INCOMING, CorrespondenceLetter::TYPE_OUTGOING], true), 404);
        $this->assertCanCreateType($type);

        $originUnitOptions = $this->originOrgUnitOptions();

        $oldInput = session('_old_input', []);
        $selectedUserIds = collect(array_merge(
            (array) ($oldInput['to_user_ids'] ?? []),
            (array) ($oldInput['cc_user_ids'] ?? [])
        ))
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $selectedUsers = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->whereIn('id', $selectedUserIds)
            ->get(['id', 'full_name', 'email'])
            ->map(function ($item) {
                $name = trim((string) ($item->full_name ?? ''));
                $email = trim((string) ($item->email ?? ''));
                return [
                    'id' => (int) $item->id,
                    'text' => $email !== '' ? "{$name} ({$email})" : $name,
                ];
            })
            ->values();

        return view('correspondence::form', [
            'type' => $type,
            'level' => $this->corrLevel(),
            'orgUnitOptions' => $originUnitOptions,
            'selectedUsers' => $selectedUsers,
        ] + $this->navPermissionData());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'letter_type' => ['required', Rule::in([CorrespondenceLetter::TYPE_INCOMING, CorrespondenceLetter::TYPE_OUTGOING])],
            'registry_no' => ['nullable', 'string', 'max:100'],
            'letter_no' => ['nullable', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:500'],
            'from_org' => ['nullable', 'string', 'max:255'],
            'to_org' => ['nullable', 'string', 'max:255'],
            'priority' => ['nullable', Rule::in(['normal', 'urgent', 'confidential'])],
            'letter_date' => ['nullable', 'date'],
            'received_date' => ['nullable', 'date'],
            'sent_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'summary' => ['nullable', 'string'],
            'origin_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'to_department_ids' => ['nullable', 'array'],
            'to_department_ids.*' => ['nullable', 'integer', 'exists:departments,id'],
            'cc_department_ids' => ['nullable', 'array'],
            'cc_department_ids.*' => ['nullable', 'integer', 'exists:departments,id'],
            'to_user_ids' => ['nullable', 'array'],
            'to_user_ids.*' => ['nullable', 'integer', 'exists:users,id'],
            'cc_user_ids' => ['nullable', 'array'],
            'cc_user_ids.*' => ['nullable', 'integer', 'exists:users,id'],
            'send_action' => ['nullable', Rule::in(['draft', 'send'])],
            'attachments.*' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png'],
        ]);
        $this->assertCanCreateType((string) ($validated['letter_type'] ?? ''));

        $user = Auth::user();
        $originDepartmentId = (int) ($validated['origin_department_id'] ?? 0);
        if ($originDepartmentId <= 0) {
            $originDepartmentId = $this->resolveDefaultOriginDepartmentId($user);
        }

        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ((array) $request->file('attachments') as $file) {
                if (!$file) {
                    continue;
                }
                $originalName = basename((string) $file->getClientOriginalName());
                $directory = 'correspondence/attachments/' . now()->format('Y/m/d');
                $attachmentPaths[] = $file->storeAs($directory, $originalName, 'public');
            }
        }

        $fromOrg = $validated['from_org'] ?? null;
        if ((string) $validated['letter_type'] === CorrespondenceLetter::TYPE_OUTGOING) {
            $fromOrg = $fromOrg ?: $this->resolveDepartmentName($originDepartmentId);
        }

        $letter = CorrespondenceLetter::create([
            'letter_type' => (string) $validated['letter_type'],
            'registry_no' => $this->resolveRegistryNo($validated),
            'letter_no' => $validated['letter_no'] ?? null,
            'subject' => (string) $validated['subject'],
            'from_org' => $fromOrg ?: null,
            'to_org' => $validated['to_org'] ?? null,
            'priority' => (string) ($validated['priority'] ?? 'normal'),
            'status' => CorrespondenceLetter::STATUS_PENDING,
            'letter_date' => $validated['letter_date'] ?? null,
            'received_date' => $validated['received_date'] ?? null,
            'sent_date' => $validated['sent_date'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'summary' => $validated['summary'] ?? null,
            'attachment_path' => !empty($attachmentPaths) ? json_encode($attachmentPaths, JSON_UNESCAPED_UNICODE) : null,
            'origin_department_id' => $originDepartmentId > 0 ? $originDepartmentId : null,
            'current_step' => CorrespondenceLetter::defaultStepForType((string) $validated['letter_type']),
            'current_handler_user_id' => (int) ($user->id ?? 0) ?: null,
            'created_by' => (int) ($user->id ?? 0) ?: null,
            'updated_by' => (int) ($user->id ?? 0) ?: null,
        ]);

        $this->logAction(
            $letter,
            'created',
            $letter->current_step,
            null,
            null,
            localize('letter_created', 'Letter created')
        );

        if ($letter->letter_type === CorrespondenceLetter::TYPE_OUTGOING && ($validated['send_action'] ?? 'draft') === 'send') {
            $toDepartmentIds = collect($validated['to_department_ids'] ?? [])
                ->filter(fn ($id) => (int) $id > 0)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            $ccDepartmentIds = collect($validated['cc_department_ids'] ?? [])
                ->filter(fn ($id) => (int) $id > 0)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            $toUserIds = collect($validated['to_user_ids'] ?? [])
                ->filter(fn ($id) => (int) $id > 0)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
            $ccUserIds = collect($validated['cc_user_ids'] ?? [])
                ->filter(fn ($id) => (int) $id > 0)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($toUserIds->isNotEmpty() && $ccUserIds->isNotEmpty()) {
                $ccUserIds = $ccUserIds->reject(fn ($id) => $toUserIds->contains($id))->values();
            }
            if ($toDepartmentIds->isNotEmpty() && $ccDepartmentIds->isNotEmpty()) {
                $ccDepartmentIds = $ccDepartmentIds->reject(fn ($id) => $toDepartmentIds->contains($id))->values();
            }

            if ($toDepartmentIds->isEmpty() && $ccDepartmentIds->isEmpty() && $toUserIds->isEmpty() && $ccUserIds->isEmpty()) {
                return back()->withErrors([
                    'to_department_ids' => localize('distribution_target_required', 'Please select at least one distribution target.'),
                ])->withInput();
            }

            $allDepartmentIds = $toDepartmentIds->merge($ccDepartmentIds)->unique()->values();
            $toDepartmentSet = $toDepartmentIds->flip();

            foreach ($allDepartmentIds as $departmentId) {
                $distributionType = $toDepartmentSet->has($departmentId)
                    ? CorrespondenceLetterDistribution::TYPE_TO
                    : CorrespondenceLetterDistribution::TYPE_CC;

                $distribution = CorrespondenceLetterDistribution::create([
                    'letter_id' => (int) $letter->id,
                    'target_department_id' => (int) $departmentId,
                    'target_user_id' => null,
                    'distribution_type' => $distributionType,
                    'distributed_by' => (int) ($user->id ?? 0) ?: null,
                    'distributed_at' => now(),
                    'status' => CorrespondenceLetterDistribution::STATUS_PENDING_ACK,
                ]);

                $this->logAction(
                    $letter,
                    'distribute',
                    (string) $letter->current_step,
                    null,
                    (int) $departmentId,
                    null,
                    ['distribution_id' => (int) $distribution->id]
                );

                $this->notifyAssignmentRecipients(
                    $letter,
                    $distribution,
                    '',
                    'distributed',
                    null,
                    (int) $departmentId
                );
            }

            $allUserIds = $toUserIds
                ->merge($ccUserIds)
                ->unique()
                ->values();
            $toUserSet = $toUserIds->flip();

            foreach ($allUserIds as $userId) {
                $distributionType = $toUserSet->has($userId)
                    ? CorrespondenceLetterDistribution::TYPE_TO
                    : CorrespondenceLetterDistribution::TYPE_CC;

                $distribution = CorrespondenceLetterDistribution::create([
                    'letter_id' => (int) $letter->id,
                    'target_department_id' => null,
                    'target_user_id' => (int) $userId,
                    'distribution_type' => $distributionType,
                    'distributed_by' => (int) ($user->id ?? 0) ?: null,
                    'distributed_at' => now(),
                    'status' => CorrespondenceLetterDistribution::STATUS_PENDING_ACK,
                ]);

                $this->logAction(
                    $letter,
                    'distribute',
                    (string) $letter->current_step,
                    (int) $userId,
                    null,
                    null,
                    ['distribution_id' => (int) $distribution->id]
                );

                $this->notifyAssignmentRecipients(
                    $letter,
                    $distribution,
                    '',
                    'distributed',
                    (int) $userId,
                    null
                );
            }

            $letter->update([
                'current_step' => CorrespondenceLetter::STEP_OUTGOING_DISTRIBUTED,
                'status' => CorrespondenceLetter::STATUS_IN_PROGRESS,
                'updated_by' => (int) ($user->id ?? 0) ?: null,
            ]);
        }

        if ($this->shouldReturnApiResponse($request)) {
            $letter = $letter->fresh([
                'originDepartment:id,department_name',
                'assignedDepartment:id,department_name',
                'currentHandler:id,full_name',
                'actions',
                'distributions.targetDepartment:id,department_name',
                'distributions.targetUser:id,full_name,email',
            ]);

            return $this->correspondenceApiResponse(
                $this->serializeLetter($letter),
                localize('data_save', 'Saved successfully.'),
                201
            );
        }

        return redirect()
            ->route('correspondence.show', $letter->id)
            ->with('success', localize('data_save', 'Saved successfully.'));
    }

    public function openNotification(Request $request, string $notification)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $userNotification = $user->notifications()
            ->where('id', $notification)
            ->first();

        if (!$userNotification) {
            return redirect()
                ->route('correspondence.index')
                ->with('error', localize('notification_not_found', 'Notification was not found.'));
        }

        if (!$userNotification->read_at) {
            $userNotification->markAsRead();
        }

        $payload = is_array($userNotification->data) ? $userNotification->data : [];
        $letterId = (int) ($payload['letter_id'] ?? 0);
        $distributionId = (int) ($payload['distribution_id'] ?? 0);
        $context = trim((string) ($payload['context'] ?? ''));

        if ($letterId <= 0) {
            return redirect()
                ->route('correspondence.index')
                ->with('error', localize('letter_not_found', 'Letter was not found.'));
        }

        $letter = CorrespondenceLetter::query()->find($letterId);
        if (!$letter) {
            return redirect()
                ->route('correspondence.index')
                ->with('error', localize('letter_not_found', 'Letter was not found.'));
        }

        try {
            $this->assertCanView($letter);
        } catch (\Throwable $e) {
            return redirect()
                ->route('correspondence.index')
                ->with('error', localize('permission_denied', 'Permission denied.'));
        }

        $routeParams = ['letter' => (int) $letter->id];
        $anchor = '';

        if ($distributionId > 0) {
            $distribution = CorrespondenceLetterDistribution::query()
                ->where('id', $distributionId)
                ->where('letter_id', (int) $letter->id)
                ->first();

            if ($distribution) {
                $routeParams['highlight_distribution'] = $distributionId;

                $focusAction = null;
                $distributionStatus = (string) ($distribution->status ?? '');

                if ($this->canAccessDistribution($distribution)) {
                    if ($distributionStatus === CorrespondenceLetterDistribution::STATUS_PENDING_ACK) {
                        $focusAction = 'acknowledge';
                    } elseif (
                        $letter->letter_type === CorrespondenceLetter::TYPE_INCOMING
                        && in_array($distributionStatus, [
                            CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
                            CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
                        ], true)
                    ) {
                        $focusAction = 'feedback';
                    }
                }

                if ($focusAction !== null) {
                    $routeParams['focus_action'] = $focusAction;
                    $anchor = '#distribution-action-' . $distributionId;
                } else {
                    $anchor = '#distribution-row-' . $distributionId;
                }
            }
        } else {
            $workflowFocusAction = match ($context) {
                'delegated' => 'office_comment',
                'office_comment_related' => 'office_comment',
                'office_commented' => 'deputy_review',
                'deputy_reviewed' => 'director_decision',
                default => null,
            };

            if ($workflowFocusAction !== null) {
                $routeParams['focus_action'] = $workflowFocusAction;
                $anchor = '#workflow-action-' . str_replace('_', '-', $workflowFocusAction);
            }
        }

        return redirect()->to(route('correspondence.show', $routeParams) . $anchor);
    }

    public function show(Request $request, CorrespondenceLetter $letter)
    {
        $this->assertCanView($letter);
        $this->markCorrespondenceNotificationsRead((int) $letter->id);

        $originUnitOptions = $this->originOrgUnitOptions();

        $letter->load([
            'originDepartment',
            'assignedDepartment',
            'currentHandler',
            'actions',
            'distributions.targetDepartment',
            'distributions.targetUser',
            'distributions.childLetter.assignedDepartment',
            'parentLetter.originDepartment',
            'parentLetter.assignedDepartment',
            'childLetters.assignedDepartment',
        ]);

        $sourceDistribution = null;
        if ((int) ($letter->source_distribution_id ?? 0) > 0) {
            $sourceDistribution = CorrespondenceLetterDistribution::query()
                ->with(['letter', 'targetDepartment', 'targetUser'])
                ->find((int) $letter->source_distribution_id);
        }

        $workflowAssignments = $this->incomingWorkflowAssignments($letter);

        $relatedUserIds = collect([$letter->current_handler_user_id, $letter->created_by, $letter->updated_by])
            ->merge([$letter->parentLetter?->current_handler_user_id, $letter->parentLetter?->created_by])
            ->merge($letter->actions->pluck('acted_by'))
            ->merge($letter->actions->pluck('target_user_id'))
            ->merge($letter->distributions->pluck('target_user_id'))
            ->merge($letter->childLetters->pluck('current_handler_user_id'))
            ->merge([
                (int) ($workflowAssignments['office_comment_user_id'] ?? 0),
                (int) ($workflowAssignments['deputy_review_user_id'] ?? 0),
                (int) ($workflowAssignments['director_user_id'] ?? 0),
            ])
            ->merge($workflowAssignments['office_comment_related_user_ids'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $userMap = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->whereIn('id', $relatedUserIds)
            ->get(['id', 'full_name', 'email'])
            ->mapWithKeys(function ($item) {
                $name = trim((string) ($item->full_name ?? ''));
                $email = trim((string) ($item->email ?? ''));
                return [(int) $item->id => $email !== '' ? "{$name} ({$email})" : $name];
            })
            ->all();

        $userRoleMap = UserOrgRole::withoutGlobalScopes()
            ->effective()
            ->whereIn('user_id', $relatedUserIds)
            ->with('systemRole:id,code')
            ->get(['id', 'user_id', 'org_role', 'system_role_id'])
            ->groupBy('user_id')
            ->map(function ($rows) {
                $roleCodes = $rows
                    ->map(function ($row) {
                        return $row->getEffectiveRoleCode();
                    })
                    ->filter(fn ($code) => trim((string) $code) !== '')
                    ->values();

                $roleCode = $roleCodes->first(function ($code) {
                    return in_array((string) $code, [
                        UserOrgRole::ROLE_HEAD,
                        UserOrgRole::ROLE_DEPUTY_HEAD,
                        UserOrgRole::ROLE_MANAGER,
                    ], true);
                });

                if (!$roleCode) {
                    $roleCode = $roleCodes->first();
                }

                return match ((string) $roleCode) {
                    UserOrgRole::ROLE_HEAD => localize('head_of_unit', 'ប្រធានអង្គភាព'),
                    UserOrgRole::ROLE_DEPUTY_HEAD => localize('deputy_head', 'អនុប្រធានអង្គភាព'),
                    UserOrgRole::ROLE_MANAGER => localize('manager', 'អ្នកគ្រប់គ្រង/ប្រធានការិយាល័យ'),
                    default => (string) ($roleCode ?: ''),
                };
            })
            ->all();

        $relatedDepartmentIds = collect([$letter->origin_department_id, $letter->assigned_department_id, $letter->parentLetter?->origin_department_id, $letter->parentLetter?->assigned_department_id])
            ->merge($letter->actions->pluck('target_department_id'))
            ->merge($letter->distributions->pluck('target_department_id'))
            ->merge($letter->childLetters->pluck('assigned_department_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $departmentMap = Department::withoutGlobalScopes()
            ->whereIn('id', $relatedDepartmentIds)
            ->get(['id', 'department_name'])
            ->mapWithKeys(fn ($item) => [(int) $item->id => (string) $item->department_name])
            ->all();

        $highlightDistributionId = max(0, (int) $request->query('highlight_distribution', 0));

        $currentStep = (string) ($letter->current_step ?? '');
        $isRecipientActor = $this->isRecipientActor($letter);
        $isRelatedOfficeActor = $this->isRelatedOfficeCommentParticipant($letter, (int) Auth::id());

        $canDelegate = $this->canDelegateIncoming($letter)
            && $letter->letter_type === CorrespondenceLetter::TYPE_INCOMING
            && in_array($currentStep, [
                CorrespondenceLetter::STEP_INCOMING_RECEIVED,
                CorrespondenceLetter::STEP_INCOMING_DELEGATED,
            ], true)
            && $isRecipientActor;

        $canOfficeComment = $letter->letter_type === CorrespondenceLetter::TYPE_INCOMING
            && (
                ($currentStep === CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT && $isRecipientActor)
                || (
                    in_array($currentStep, [
                        CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT,
                        CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW,
                    ], true)
                    && $isRelatedOfficeActor
                )
            );

        $canDeputyReview = $letter->letter_type === CorrespondenceLetter::TYPE_INCOMING
            && $currentStep === CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW
            && $isRecipientActor;

        $canDirectorDecision = $letter->letter_type === CorrespondenceLetter::TYPE_INCOMING
            && $currentStep === CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION
            && $isRecipientActor;

        $canDistribute = $this->canDistributeLetter($letter);

        $canClose = $letter->letter_type === CorrespondenceLetter::TYPE_OUTGOING
            && $currentStep !== CorrespondenceLetter::STEP_CLOSED
            && $currentStep !== CorrespondenceLetter::STEP_OUTGOING_DRAFT
            && $this->canPerformModuleAction(
                $letter,
                'close',
                [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD]
            );

        if ($this->shouldReturnApiResponse($request)) {
            $canSendParentFeedback = (int) ($letter->source_distribution_id ?? 0) > 0 && (
                ((int) $letter->current_handler_user_id === (int) Auth::id())
                || $this->canPerformModuleAction(
                    $letter,
                    'feedback',
                    [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD]
                )
            );

            $payload = $this->serializeLetter($letter, $userMap, $departmentMap);
            $payload['permissions'] = [
                'can_delegate' => $canDelegate,
                'can_office_comment' => $canOfficeComment,
                'can_deputy_review' => $canDeputyReview,
                'can_director_decision' => $canDirectorDecision,
                'can_distribute' => $canDistribute,
                'can_close' => $canClose,
                'can_send_parent_feedback' => $canSendParentFeedback,
            ];

            return $this->correspondenceApiResponse(
                $payload
            );
        }

        return view('correspondence::show', [
            'letter' => $letter,
            'level' => $this->corrLevel(),
            'orgUnitOptions' => $originUnitOptions,
            'workflowAssignments' => $workflowAssignments,
            'isRecipientActor' => $isRecipientActor,
            'canDelegate' => $canDelegate,
            'canOfficeComment' => $canOfficeComment,
            'canDeputyReview' => $canDeputyReview,
            'canDirectorDecision' => $canDirectorDecision,
            'canDistribute' => $canDistribute,
            'canClose' => $canClose,
            'stepLabels' => CorrespondenceLetter::stepLabels(),
            'sourceDistribution' => $sourceDistribution,
            'isChildLetter' => (int) ($letter->parent_letter_id ?? 0) > 0,
            'canSendParentFeedback' => (int) ($letter->source_distribution_id ?? 0) > 0 && (
                ((int) $letter->current_handler_user_id === (int) Auth::id())
                || $this->canPerformModuleAction(
                    $letter,
                    'feedback',
                    [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD]
                )
            ),
            'distributionStatuses' => [
                CorrespondenceLetterDistribution::STATUS_PENDING_ACK => localize('pending_ack', 'Pending acknowledge'),
                CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED => localize('acknowledged', 'Acknowledged'),
                CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT => localize('feedback_sent', 'Feedback sent'),
                CorrespondenceLetterDistribution::STATUS_CLOSED => localize('closed', 'Closed'),
            ],
            'userMap' => $userMap,
            'userRoleMap' => $userRoleMap,
            'departmentMap' => $departmentMap,
            'highlightDistributionId' => $highlightDistributionId,
        ] + $this->navPermissionData());
    }

    public function print(CorrespondenceLetter $letter)
    {
        $this->assertCanView($letter);

        $letter->load([
            'originDepartment',
            'assignedDepartment',
            'distributions.targetDepartment',
            'distributions.targetUser',
            'actions',
        ]);

        // Reload distributions to include soft-deleted records for complete feedback display
        $letter->setRelation('distributions', 
            $letter->distributions()
                ->withTrashed()
                ->with(['targetDepartment', 'targetUser'])
                ->orderByDesc('id')
                ->get()
        );

        $actionUserIds = collect([$letter->created_by, $letter->updated_by, $letter->current_handler_user_id])
            ->merge($letter->actions->pluck('acted_by'))
            ->merge($letter->actions->pluck('target_user_id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $userMap = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->whereIn('id', $actionUserIds)
            ->get(['id', 'full_name'])
            ->mapWithKeys(function ($item) {
                $name = trim((string) ($item->full_name ?? ''));
                return [(int) $item->id => $name !== '' ? $name : ('#' . (int) $item->id)];
            })
            ->all();

        $userRoleMap = Employee::query()
            ->withoutGlobalScopes()
            ->withTrashed()
            ->with(['position' => function ($query) {
                $query->withTrashed()->select('id', 'position_name', 'position_name_km');
            }])
            ->whereIn('user_id', $actionUserIds)
            ->get(['user_id', 'position_id'])
            ->mapWithKeys(function ($employee) {
                $roleNameKm = trim((string) ($employee->position?->position_name_km ?? ''));
                $roleNameEn = trim((string) ($employee->position?->position_name ?? ''));
                $roleName = $roleNameKm !== '' ? $roleNameKm : $roleNameEn;
                return [(int) $employee->user_id => ($roleName !== '' ? $roleName : '-')];
            })
            ->all();

        return view('correspondence::print-report', [
            'letter' => $letter,
            'userMap' => $userMap,
            'userRoleMap' => $userRoleMap,
        ]);
    }

    public function previewAttachment(CorrespondenceLetter $letter, int $index)
    {
        $this->assertCanView($letter);

        $resolvedPath = $this->resolveAttachmentPathByIndex($letter, $index);

        $ext = strtolower(pathinfo($resolvedPath, PATHINFO_EXTENSION));
        $previewableExts = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

        if ($ext === 'pdf') {
            return redirect()->to(route('correspondence.attachments.file', [$letter->id, (int) $index]));
        }

        return view('correspondence::attachment-preview', [
            'fileUrl' => route('correspondence.attachments.file', [$letter->id, (int) $index]),
            'downloadUrl' => route('correspondence.attachments.file', [$letter->id, (int) $index]) . '?download=1',
            'fileName' => basename($resolvedPath),
            'fileExt' => $ext,
            'isPreviewable' => in_array($ext, $previewableExts, true),
        ]);
    }

    public function attachmentFile(Request $request, CorrespondenceLetter $letter, int $index)
    {
        $this->assertCanView($letter);

        return $this->attachmentFileResponse($request, $letter, $index);
    }

    public function attachmentSignedUrl(Request $request, CorrespondenceLetter $letter, int $index)
    {
        $this->assertCanView($letter);
        $this->resolveAttachmentPathByIndex($letter, $index);

        $download = (string) $request->query('download', '0') === '1' ? '1' : '0';
        $signedUrl = URL::temporarySignedRoute(
            'correspondence.attachments.file.signed',
            now()->addMinutes(10),
            [
                'letter' => (int) $letter->id,
                'index' => (int) $index,
                'download' => $download,
            ]
        );

        $path = (string) (parse_url($signedUrl, PHP_URL_PATH) ?? '');
        $query = (string) (parse_url($signedUrl, PHP_URL_QUERY) ?? '');
        $relativePath = $query !== '' ? ($path . '?' . $query) : $path;

        return $this->correspondenceApiResponse([
            'url' => $signedUrl,
            'path' => $relativePath,
        ]);
    }

    public function attachmentFileSigned(Request $request, CorrespondenceLetter $letter, int $index)
    {
        return $this->attachmentFileResponse($request, $letter, $index);
    }

    protected function attachmentFileResponse(Request $request, CorrespondenceLetter $letter, int $index)
    {
        $resolvedPath = $this->resolveAttachmentPathByIndex($letter, $index);
        $fileName = basename($resolvedPath);
        $absolutePath = storage_path('app/public/' . ltrim($resolvedPath, '/'));

        if (!is_file($absolutePath)) {
            abort(404);
        }

        if ((string) $request->query('download', '0') === '1') {
            return response()->download($absolutePath, $fileName);
        }

        $mimeType = @mime_content_type($absolutePath) ?: 'application/octet-stream';

        return response()->file($absolutePath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $fileName . '"',
        ]);
    }

    protected function resolveAttachmentPathByIndex(CorrespondenceLetter $letter, int $index): string
    {
        $attachments = is_array($letter->attachment_path)
            ? $letter->attachment_path
            : json_decode((string) $letter->attachment_path, true);

        if (!is_array($attachments)) {
            $attachments = !empty($letter->attachment_path) ? [(string) $letter->attachment_path] : [];
        }

        $attachments = array_values(array_filter($attachments, fn ($item) => !empty($item)));
        $index = (int) $index;

        if (!isset($attachments[$index])) {
            abort(404);
        }

        $path = ltrim((string) $attachments[$index], '/');
        $normalizedPath = preg_replace('#^(storage/|public/)#', '', $path) ?: $path;
        $disk = Storage::disk('public');

        if ($disk->exists($normalizedPath)) {
            return $normalizedPath;
        }

        if ($disk->exists($path)) {
            return $path;
        }

        abort(404);
    }

    public function progress(Request $request, CorrespondenceLetter $letter)
    {
        $this->assertCanView($letter);

        $validated = $request->validate([
            'action' => ['required', Rule::in([
                'delegate',
                'office_comment',
                'deputy_review',
                'director_decision',
                'close',
            ])],
            'assigned_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'assigned_department_ids' => ['nullable', 'array'],
            'assigned_department_ids.*' => ['nullable', 'integer', 'exists:departments,id'],
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'office_comment_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'office_comment_related_user_ids' => ['nullable', 'array'],
            'office_comment_related_user_ids.*' => ['nullable', 'integer', 'exists:users,id'],
            'deputy_review_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'director_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'target_department_ids' => ['nullable', 'array'],
            'target_department_ids.*' => ['nullable', 'integer', 'exists:departments,id'],
            'cc_department_ids' => ['nullable', 'array'],
            'cc_department_ids.*' => ['nullable', 'integer', 'exists:departments,id'],
            'to_user_ids' => ['nullable', 'array'],
            'to_user_ids.*' => ['nullable', 'integer', 'exists:users,id'],
            'cc_user_ids' => ['nullable', 'array'],
            'cc_user_ids.*' => ['nullable', 'integer', 'exists:users,id'],
            'decision' => ['nullable', Rule::in([CorrespondenceLetter::DECISION_APPROVED, CorrespondenceLetter::DECISION_REJECTED])],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $action = (string) $validated['action'];
        $note = trim((string) ($validated['note'] ?? ''));
        $user = Auth::user();
        $successMessage = localize('data_update', 'Updated successfully.');

        switch ($action) {
            case 'delegate':
                if ($letter->letter_type !== CorrespondenceLetter::TYPE_INCOMING) {
                    return back()->with('error', localize('invalid_action', 'Invalid action for this letter type.'));
                }
                if (!$this->isRecipientActor($letter)) {
                    return back()->with('error', localize('only_recipient_can_assign', 'Only the current recipient can assign this letter.'));
                }
                if (!in_array((string) $letter->current_step, [
                    CorrespondenceLetter::STEP_INCOMING_RECEIVED,
                    CorrespondenceLetter::STEP_INCOMING_DELEGATED,
                ], true)) {
                    return back()->with('error', localize('step_not_allowed', 'Current step does not allow delegation.'));
                }
                if (!$this->canDelegateIncoming($letter)) {
                    return back()->with('error', localize('permission_denied', 'Permission denied.'));
                }

                $officeCommentUserId = (int) ($validated['office_comment_user_id'] ?? 0);
                $deputyReviewUserId = (int) ($validated['deputy_review_user_id'] ?? 0);
                $directorUserId = (int) ($validated['director_user_id'] ?? 0);
                $assignedDepartmentIds = collect($validated['assigned_department_ids'] ?? [])
                    ->filter(fn ($id) => (int) $id > 0)
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values();

                if ($assignedDepartmentIds->isEmpty() && !empty($validated['assigned_department_id'])) {
                    $assignedDepartmentIds = collect([(int) $validated['assigned_department_id']]);
                }

                if ($assignedDepartmentIds->isEmpty()) {
                    return back()->withErrors([
                        'assigned_department_ids' => localize('assigned_units_required', 'Please select at least one org unit.'),
                    ])->withInput();
                }

                $assignedDepartmentId = (int) ($assignedDepartmentIds->first() ?? 0);

                $officeCommentRelatedUserIds = collect($validated['office_comment_related_user_ids'] ?? [])
                    ->filter(fn ($id) => (int) $id > 0)
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->reject(function ($id) use ($officeCommentUserId, $deputyReviewUserId, $directorUserId) {
                        return in_array((int) $id, [$officeCommentUserId, $deputyReviewUserId, $directorUserId], true);
                    })
                    ->values();

                if ($officeCommentUserId <= 0 || $deputyReviewUserId <= 0 || $directorUserId <= 0) {
                    return back()->withErrors([
                        'office_comment_user_id' => localize('incoming_chain_required', 'Please select users for steps 3, 4, and 5.'),
                    ])->withInput();
                }

                if (!$this->isUserLinkedToAnyDepartment($officeCommentUserId, $assignedDepartmentIds->all())) {
                    return back()->withErrors([
                        'office_comment_user_id' => localize('step3_user_not_in_selected_units', 'Step 3 responsible user must belong to selected org units.'),
                    ])->withInput();
                }

                $invalidRelatedUserId = $officeCommentRelatedUserIds
                    ->first(fn ($id) => !$this->isUserLinkedToAnyDepartment((int) $id, $assignedDepartmentIds->all()));

                if ((int) $invalidRelatedUserId > 0) {
                    return back()->withErrors([
                        'office_comment_related_user_ids' => localize('step3_related_users_not_in_selected_units', 'Related users in step 3 must belong to selected org units.'),
                    ])->withInput();
                }

                $letter->update([
                    'assigned_department_id' => $assignedDepartmentId > 0 ? $assignedDepartmentId : $letter->assigned_department_id,
                    'current_handler_user_id' => $officeCommentUserId,
                    'current_step' => CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT,
                    'status' => CorrespondenceLetter::STATUS_IN_PROGRESS,
                    'updated_by' => (int) $user->id,
                ]);

                $this->logAction(
                    $letter,
                    'delegate',
                    CorrespondenceLetter::STEP_INCOMING_DELEGATED,
                    $officeCommentUserId,
                    $assignedDepartmentId > 0 ? $assignedDepartmentId : null,
                    $note,
                    [
                        'assigned_department_ids' => $assignedDepartmentIds->all(),
                        'office_comment_user_id' => $officeCommentUserId,
                        'office_comment_related_user_ids' => $officeCommentRelatedUserIds->all(),
                        'deputy_review_user_id' => $deputyReviewUserId,
                        'director_user_id' => $directorUserId,
                    ]
                );

                $this->notifyAssignmentRecipients(
                    $letter,
                    null,
                    $note,
                    'delegated',
                    $officeCommentUserId,
                    $assignedDepartmentId > 0 ? $assignedDepartmentId : null
                );

                foreach ($officeCommentRelatedUserIds as $relatedUserId) {
                    $this->notifyAssignmentRecipients(
                        $letter,
                        null,
                        $note,
                        'delegated',
                        (int) $relatedUserId,
                        null
                    );
                }
                break;

            case 'office_comment':
                if ($note === '') {
                    return back()->withErrors(['note' => localize('comment_required', 'Comment is required.')]);
                }

                $currentStep = (string) ($letter->current_step ?? '');
                $workflowAssignments = $this->repairIncomingWorkflowAssignments($letter, $this->incomingWorkflowAssignments($letter));
                $officeCommentUserId = (int) ($workflowAssignments['office_comment_user_id'] ?? 0);
                $relatedOfficeUserIds = collect($workflowAssignments['office_comment_related_user_ids'] ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->filter(fn ($id) => $id > 0)
                    ->unique()
                    ->values();
                $pendingRelatedUserIds = $this->step3PendingRelatedUserIds($letter, $workflowAssignments);
                $currentUserId = (int) ($user->id ?? 0);
                $isMainOfficeActor = $currentStep === CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT && $this->isRecipientActor($letter);
                $isRelatedOfficeActor = $relatedOfficeUserIds->contains($currentUserId);
                $allowLateRelatedComment = $currentStep === CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW
                    && in_array($currentUserId, $pendingRelatedUserIds, true);

                if (!in_array($currentStep, [
                    CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT,
                    CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW,
                ], true)) {
                    return back()->with('error', localize('step_not_allowed', 'Current step does not allow office comment.'));
                }

                if ($currentStep === CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW && !$allowLateRelatedComment) {
                    return back()->with('error', localize('step_not_allowed', 'Current step does not allow office comment.'));
                }

                if (!$isMainOfficeActor && !$isRelatedOfficeActor && !$allowLateRelatedComment) {
                    return back()->with('error', localize('permission_denied', 'Permission denied.'));
                }

                if ($isRelatedOfficeActor || $allowLateRelatedComment) {
                    $this->logAction(
                        $letter,
                        'office_comment_related',
                        CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT,
                        $officeCommentUserId > 0 ? $officeCommentUserId : null,
                        (int) ($letter->assigned_department_id ?? 0) ?: null,
                        $note
                    );

                    if ($officeCommentUserId > 0 && $officeCommentUserId !== (int) ($user->id ?? 0)) {
                        $this->notifyAssignmentRecipients(
                            $letter,
                            null,
                            $note,
                            'office_comment_related',
                            $officeCommentUserId,
                            null
                        );
                    }

                    $successMessage = localize('related_comment_saved_wait_main_step3', 'Related comment saved. Waiting for the step 3 responsible actor to continue.');
                    break;
                }

                if (!empty($pendingRelatedUserIds)) {
                    return back()->withErrors([
                        'note' => localize('step3_waiting_related_comments', 'Cannot continue to step 4 yet. Waiting related comments from') . ': ' . $this->formatUserDisplayList($pendingRelatedUserIds),
                    ])->withInput();
                }

                $deputyReviewUserId = (int) ($workflowAssignments['deputy_review_user_id'] ?? 0);
                if ($deputyReviewUserId <= 0) {
                    return back()->with('error', localize('incoming_chain_missing_deputy', 'Deputy reviewer is not configured. Please update the delegation step first.'));
                }

                $letter->update([
                    'current_handler_user_id' => $deputyReviewUserId,
                    'current_step' => CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW,
                    'status' => CorrespondenceLetter::STATUS_IN_PROGRESS,
                    'updated_by' => (int) $user->id,
                ]);

                $this->logAction($letter, 'office_comment', CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT, $deputyReviewUserId, null, $note);

                $assignedDepartmentId = (int) ($letter->assigned_department_id ?? 0);
                if ($assignedDepartmentId <= 0) {
                    $assignedDepartmentId = (int) ($letter->origin_department_id ?? 0);
                }

                $this->notifyAssignmentRecipients(
                    $letter,
                    null,
                    $note,
                    'office_commented',
                    $deputyReviewUserId,
                    $assignedDepartmentId > 0 ? $assignedDepartmentId : null
                );
                break;

            case 'deputy_review':
                if ((string) $letter->current_step !== CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW) {
                    return back()->with('error', localize('step_not_allowed', 'Current step does not allow deputy review.'));
                }
                if (!$this->isRecipientActor($letter)) {
                    return back()->with('error', localize('permission_denied', 'Permission denied.'));
                }
                if ($note === '') {
                    return back()->withErrors(['note' => localize('comment_required', 'Comment is required.')]);
                }

                $workflowAssignments = $this->repairIncomingWorkflowAssignments($letter, $this->incomingWorkflowAssignments($letter));
                $pendingRelatedUserIds = $this->step3PendingRelatedUserIds($letter, $workflowAssignments);
                if (!empty($pendingRelatedUserIds)) {
                    return back()->withErrors([
                        'note' => localize('step3_waiting_related_comments', 'Cannot continue to step 4 yet. Waiting related comments from') . ': ' . $this->formatUserDisplayList($pendingRelatedUserIds),
                    ])->withInput();
                }
                $directorUserId = (int) ($workflowAssignments['director_user_id'] ?? 0);
                if ($directorUserId <= 0) {
                    return back()->with('error', localize('incoming_chain_missing_director', 'Director is not configured. Please update the delegation step first.'));
                }

                $letter->update([
                    'current_handler_user_id' => $directorUserId,
                    'current_step' => CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION,
                    'status' => CorrespondenceLetter::STATUS_IN_PROGRESS,
                    'updated_by' => (int) $user->id,
                ]);

                $this->logAction($letter, 'deputy_review', CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW, $directorUserId, null, $note);

                $assignedDepartmentId = (int) ($letter->assigned_department_id ?? 0);
                if ($assignedDepartmentId <= 0) {
                    $assignedDepartmentId = (int) ($letter->origin_department_id ?? 0);
                }

                $this->notifyAssignmentRecipients(
                    $letter,
                    null,
                    $note,
                    'deputy_reviewed',
                    $directorUserId,
                    $assignedDepartmentId > 0 ? $assignedDepartmentId : null
                );
                break;

            case 'director_decision':
                if ((string) $letter->current_step !== CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION) {
                    return back()->with('error', localize('step_not_allowed', 'Current step does not allow director decision.'));
                }
                if (in_array((string) ($letter->status ?? ''), [
                    CorrespondenceLetter::STATUS_COMPLETED,
                    CorrespondenceLetter::STATUS_ARCHIVED,
                ], true) || trim((string) ($letter->final_decision ?? '')) !== '') {
                    return back()->with('warning', localize('incoming_already_finalized', 'លិខិតនេះត្រូវបានសម្រេចរួចរាល់ហើយ។'));
                }
                if (!$this->isRecipientActor($letter)) {
                    return back()->with('error', localize('permission_denied', 'Permission denied.'));
                }
                $decision = (string) ($validated['decision'] ?? '');
                if ($decision === '') {
                    return back()->withErrors(['decision' => localize('decision_required', 'Decision is required.')]);
                }
                if ($decision === CorrespondenceLetter::DECISION_REJECTED && $note === '') {
                    return back()->withErrors(['note' => localize('rejected_reason_required', 'Reason is required for rejection.')]);
                }

                $isApproved = $decision === CorrespondenceLetter::DECISION_APPROVED;
                $nextStep = CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION;

                $letter->update([
                    'final_decision' => $decision,
                    'decision_note' => $note !== '' ? $note : null,
                    'decision_at' => now(),
                    'current_handler_user_id' => (int) $user->id,
                    'current_step' => $nextStep,
                    'status' => $isApproved ? CorrespondenceLetter::STATUS_COMPLETED : CorrespondenceLetter::STATUS_ARCHIVED,
                    'completed_at' => now(),
                    'updated_by' => (int) $user->id,
                ]);

                $this->logAction(
                    $letter,
                    $isApproved ? 'director_approved' : 'director_rejected',
                    $nextStep,
                    null,
                    null,
                    $note
                );

                $successMessage = $isApproved
                    ? localize('incoming_approved_done_wait_distribution', 'បានអនុម័តរួចរាល់។ ប្រព័ន្ធបានបញ្ចប់ដំណើរការ និងរង់ចាំការបែងចែកបន្ត។')
                    : localize('incoming_rejected_done', 'បានសម្រេចមិនអនុម័តរួចរាល់។');
                break;

            case 'close':
                if (!$this->canPerformModuleAction($letter, 'close', [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD])) {
                    return back()->with('error', localize('permission_denied', 'Permission denied.'));
                }
                if ($letter->letter_type === CorrespondenceLetter::TYPE_INCOMING) {
                    return back()->with('warning', localize('incoming_closed_by_director_decision', 'Incoming letter is finalized at director decision step.'));
                }
                $letter->update([
                    'current_step' => CorrespondenceLetter::STEP_CLOSED,
                    'status' => CorrespondenceLetter::STATUS_COMPLETED,
                    'completed_at' => now(),
                    'updated_by' => (int) $user->id,
                ]);
                $this->logAction($letter, 'closed', CorrespondenceLetter::STEP_CLOSED, null, null, $note);
                break;
        }

        if ((int) ($letter->source_distribution_id ?? 0) > 0) {
            if ($action === 'close' || $action === 'director_decision') {
                $feedbackNote = $note !== '' ? $note : localize('child_unit_completed', 'Child unit completed processing.');
                $this->syncParentDistributionFromChild($letter, CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT, $feedbackNote);
            } else {
                $ackNote = $note !== '' ? $note : localize('child_unit_in_progress', 'Child unit is processing this letter.');
                $this->syncParentDistributionFromChild($letter, CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED, $ackNote);
            }
        }

        if ($this->shouldReturnApiResponse($request)) {
            return $this->correspondenceApiResponse(
                $this->freshSerializedLetter($letter),
                $successMessage
            );
        }

        return redirect()
            ->route('correspondence.show', $letter->id)
            ->with('success', $successMessage);
    }

    public function distribute(Request $request, CorrespondenceLetter $letter)
    {
        $this->assertCanView($letter);

        if (!$this->canDistributeLetter($letter)) {
            return back()->with('error', localize('permission_denied', 'Permission denied.'));
        }

        if ($letter->letter_type === CorrespondenceLetter::TYPE_INCOMING) {
            if (
                (string) $letter->current_step !== CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION
                || (string) $letter->final_decision !== CorrespondenceLetter::DECISION_APPROVED
            ) {
                return back()->with('error', localize('incoming_not_ready_for_distribution', 'Incoming letter is not ready for distribution.'));
            }
        } elseif (!in_array((string) $letter->current_step, [
            CorrespondenceLetter::STEP_OUTGOING_DRAFT,
            CorrespondenceLetter::STEP_OUTGOING_DISTRIBUTED,
        ], true)) {
            return back()->with('error', localize('outgoing_not_ready_for_distribution', 'Outgoing letter is not ready for distribution.'));
        }

        $validated = $request->validate([
            'target_department_ids' => ['nullable', 'array'],
            'target_department_ids.*' => ['nullable', 'integer', 'exists:departments,id'],
            'cc_department_ids' => ['nullable', 'array'],
            'cc_department_ids.*' => ['nullable', 'integer', 'exists:departments,id'],
            'to_department_ids' => ['nullable', 'array'],
            'to_department_ids.*' => ['nullable', 'integer', 'exists:departments,id'],
            'target_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'to_user_ids' => ['nullable', 'array'],
            'to_user_ids.*' => ['nullable', 'integer', 'exists:users,id'],
            'cc_user_ids' => ['nullable', 'array'],
            'cc_user_ids.*' => ['nullable', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $targetDepartmentIds = collect($validated['target_department_ids'] ?? [])
            ->merge(!empty($validated['target_department_id']) ? [(int) $validated['target_department_id']] : [])
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $ccDepartmentIds = collect($validated['cc_department_ids'] ?? [])
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $targetUserId = !empty($validated['target_user_id']) ? (int) $validated['target_user_id'] : null;
        $toDepartmentIds = collect($validated['to_department_ids'] ?? [])
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $ccOutgoingDepartmentIds = collect($validated['cc_department_ids'] ?? [])
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $toUserIds = collect($validated['to_user_ids'] ?? [])
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $ccUserIds = collect($validated['cc_user_ids'] ?? [])
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $note = trim((string) ($validated['note'] ?? ''));

        if ($letter->letter_type === CorrespondenceLetter::TYPE_OUTGOING) {
            if ($toDepartmentIds->isEmpty() && $ccOutgoingDepartmentIds->isEmpty() && $toUserIds->isEmpty() && $ccUserIds->isEmpty()) {
                return back()->withErrors([
                    'to_user_ids' => localize('distribution_target_required', 'Please select at least one distribution target.'),
                ]);
            }
        } elseif ($targetDepartmentIds->isEmpty() && $ccDepartmentIds->isEmpty() && !$targetUserId) {
            return back()->withErrors([
                'target_user_id' => localize('distribution_target_required', 'Please select at least one distribution target.'),
            ]);
        }

        if ($toUserIds->isNotEmpty() && $ccUserIds->isNotEmpty()) {
            $ccUserIds = $ccUserIds->reject(fn ($id) => $toUserIds->contains($id))->values();
        }
        if ($toDepartmentIds->isNotEmpty() && $ccOutgoingDepartmentIds->isNotEmpty()) {
            $ccOutgoingDepartmentIds = $ccOutgoingDepartmentIds->reject(fn ($id) => $toDepartmentIds->contains($id))->values();
        }

        if ($letter->letter_type === CorrespondenceLetter::TYPE_OUTGOING) {
            $createdCount = 0;
            $skippedCount = 0;
            $allDepartmentIds = $toDepartmentIds->merge($ccOutgoingDepartmentIds)->unique()->values();
            $toDepartmentSet = $toDepartmentIds->flip();
            $allUserIds = $toUserIds->merge($ccUserIds)->unique()->values();
            $toUserSet = $toUserIds->flip();

            foreach ($allDepartmentIds as $departmentId) {
                $distributionType = $toDepartmentSet->has($departmentId)
                    ? CorrespondenceLetterDistribution::TYPE_TO
                    : CorrespondenceLetterDistribution::TYPE_CC;

                $hasOpenDistribution = CorrespondenceLetterDistribution::query()
                    ->where('letter_id', (int) $letter->id)
                    ->where('target_department_id', (int) $departmentId)
                    ->whereIn('status', [
                        CorrespondenceLetterDistribution::STATUS_PENDING_ACK,
                        CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
                    ])
                    ->exists();

                if ($hasOpenDistribution) {
                    $skippedCount++;
                    continue;
                }

                $distribution = CorrespondenceLetterDistribution::create([
                    'letter_id' => (int) $letter->id,
                    'target_department_id' => (int) $departmentId,
                    'target_user_id' => null,
                    'distribution_type' => $distributionType,
                    'distributed_by' => (int) Auth::id(),
                    'distributed_at' => now(),
                    'status' => CorrespondenceLetterDistribution::STATUS_PENDING_ACK,
                    'acknowledgement_note' => $note !== '' ? $note : null,
                ]);

                $this->logAction(
                    $letter,
                    'distribute',
                    (string) $letter->current_step,
                    null,
                    (int) $departmentId,
                    $note,
                    ['distribution_id' => (int) $distribution->id]
                );

                $this->notifyAssignmentRecipients(
                    $letter,
                    $distribution,
                    $note,
                    'distributed',
                    null,
                    (int) $departmentId
                );

                $createdCount++;
            }

            foreach ($allUserIds as $userId) {
                $distributionType = $toUserSet->has($userId)
                    ? CorrespondenceLetterDistribution::TYPE_TO
                    : CorrespondenceLetterDistribution::TYPE_CC;

                $hasOpenDistribution = CorrespondenceLetterDistribution::query()
                    ->where('letter_id', (int) $letter->id)
                    ->where('target_user_id', (int) $userId)
                    ->whereIn('status', [
                        CorrespondenceLetterDistribution::STATUS_PENDING_ACK,
                        CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
                    ])
                    ->exists();

                if ($hasOpenDistribution) {
                    $skippedCount++;
                    continue;
                }

                $distribution = CorrespondenceLetterDistribution::create([
                    'letter_id' => (int) $letter->id,
                    'target_department_id' => null,
                    'target_user_id' => (int) $userId,
                    'distribution_type' => $distributionType,
                    'distributed_by' => (int) Auth::id(),
                    'distributed_at' => now(),
                    'status' => CorrespondenceLetterDistribution::STATUS_PENDING_ACK,
                    'acknowledgement_note' => $note !== '' ? $note : null,
                ]);

                $this->logAction(
                    $letter,
                    'distribute',
                    (string) $letter->current_step,
                    (int) $userId,
                    null,
                    $note,
                    ['distribution_id' => (int) $distribution->id]
                );

                $this->notifyAssignmentRecipients(
                    $letter,
                    $distribution,
                    $note,
                    'distributed',
                    (int) $userId,
                    null
                );

                $createdCount++;
            }

            if ($createdCount <= 0) {
                return back()->with('warning', localize('distribution_skipped', 'No new distribution was created (duplicate open targets).'));
            }

            $letter->update([
                'current_step' => CorrespondenceLetter::STEP_OUTGOING_DISTRIBUTED,
                'status' => CorrespondenceLetter::STATUS_IN_PROGRESS,
                'updated_by' => (int) Auth::id(),
            ]);

            $message = localize(
                'letter_distributed_summary',
                "Distributed {$createdCount} target(s), skipped {$skippedCount} duplicate target(s)."
            );

            if ($this->shouldReturnApiResponse($request)) {
                return $this->correspondenceApiResponse(
                    $this->freshSerializedLetter($letter),
                    $message
                );
            }

            return redirect()
                ->route('correspondence.show', $letter->id)
                ->with('success', $message);
        }

        $createdCount = 0;
        $childCount = 0;
        $skippedCount = 0;
        $allowTargetUserOnDepartmentRows = $targetDepartmentIds->count() <= 1;

        $allDepartmentIds = $targetDepartmentIds
            ->merge($ccDepartmentIds)
            ->unique()
            ->values();

        $toDepartmentSet = $targetDepartmentIds->flip();
        $ccDepartmentSet = $ccDepartmentIds->flip();

        foreach ($allDepartmentIds as $targetDepartmentId) {
            $distributionType = $toDepartmentSet->has($targetDepartmentId)
                ? CorrespondenceLetterDistribution::TYPE_TO
                : CorrespondenceLetterDistribution::TYPE_CC;

            $hasOpenDistribution = CorrespondenceLetterDistribution::query()
                ->where('letter_id', (int) $letter->id)
                ->where('target_department_id', (int) $targetDepartmentId)
                ->whereIn('status', [
                    CorrespondenceLetterDistribution::STATUS_PENDING_ACK,
                    CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
                ])
                ->exists();

            if ($hasOpenDistribution) {
                $skippedCount++;
                continue;
            }

            $distribution = CorrespondenceLetterDistribution::create([
                'letter_id' => (int) $letter->id,
                'target_department_id' => (int) $targetDepartmentId,
                'target_user_id' => $distributionType === CorrespondenceLetterDistribution::TYPE_TO && $allowTargetUserOnDepartmentRows ? $targetUserId : null,
                'distribution_type' => $distributionType,
                'distributed_by' => (int) Auth::id(),
                'distributed_at' => now(),
                'status' => CorrespondenceLetterDistribution::STATUS_PENDING_ACK,
                'acknowledgement_note' => $note !== '' ? $note : null,
            ]);

            $childLetter = null;
            if ($letter->letter_type === CorrespondenceLetter::TYPE_INCOMING) {
                $childLetter = $this->createChildIncomingLetterFromDistribution(
                    $letter,
                    $distribution,
                    (int) $targetDepartmentId,
                    $allowTargetUserOnDepartmentRows ? $targetUserId : null,
                    $note
                );

                if ($childLetter) {
                    $distribution->update([
                        'child_letter_id' => (int) $childLetter->id,
                    ]);
                    $childCount++;
                }
            }

            $this->logAction(
                $letter,
                'distribute',
                (string) $letter->current_step,
                $allowTargetUserOnDepartmentRows ? $targetUserId : null,
                (int) $targetDepartmentId,
                $note,
                [
                    'distribution_id' => (int) $distribution->id,
                    'child_letter_id' => (int) ($childLetter->id ?? 0) ?: null,
                ]
            );
            $this->notifyAssignmentRecipients(
                $letter,
                $distribution,
                $note,
                'distributed',
                $distributionType === CorrespondenceLetterDistribution::TYPE_TO && $allowTargetUserOnDepartmentRows ? $targetUserId : null,
                (int) $targetDepartmentId
            );
            $createdCount++;
        }

        if ($allDepartmentIds->isEmpty() && $targetUserId) {
            $hasOpenDistribution = CorrespondenceLetterDistribution::query()
                ->where('letter_id', (int) $letter->id)
                ->where('target_user_id', $targetUserId)
                ->whereIn('status', [
                    CorrespondenceLetterDistribution::STATUS_PENDING_ACK,
                    CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
                ])
                ->exists();

            if ($hasOpenDistribution) {
                $skippedCount++;
            } else {
                $distribution = CorrespondenceLetterDistribution::create([
                    'letter_id' => (int) $letter->id,
                    'target_department_id' => null,
                    'target_user_id' => $targetUserId,
                    'distribution_type' => CorrespondenceLetterDistribution::TYPE_TO,
                    'distributed_by' => (int) Auth::id(),
                    'distributed_at' => now(),
                    'status' => CorrespondenceLetterDistribution::STATUS_PENDING_ACK,
                    'acknowledgement_note' => $note !== '' ? $note : null,
                ]);

                $this->logAction(
                    $letter,
                    'distribute',
                    (string) $letter->current_step,
                    $targetUserId,
                    null,
                    $note,
                    ['distribution_id' => (int) $distribution->id]
                );
                $this->notifyAssignmentRecipients(
                    $letter,
                    $distribution,
                    $note,
                    'distributed',
                    $targetUserId,
                    null
                );
                $createdCount++;
            }
        }

        if ($createdCount <= 0) {
            return back()->with('warning', localize('distribution_skipped', 'No new distribution was created (duplicate open targets).'));
        }

        $nextStep = $letter->letter_type === CorrespondenceLetter::TYPE_INCOMING
            ? CorrespondenceLetter::STEP_INCOMING_DISTRIBUTED
            : CorrespondenceLetter::STEP_OUTGOING_DISTRIBUTED;

        $letter->update([
            'current_step' => $nextStep,
            'status' => CorrespondenceLetter::STATUS_IN_PROGRESS,
            'updated_by' => (int) Auth::id(),
        ]);

        $message = localize(
            'letter_distributed_summary',
            "Distributed {$createdCount} target(s), auto-created {$childCount} child letter(s), skipped {$skippedCount} duplicate target(s)."
        );

        if ($this->shouldReturnApiResponse($request)) {
            return $this->correspondenceApiResponse(
                $this->freshSerializedLetter($letter),
                $message
            );
        }

        return redirect()
            ->route('correspondence.show', $letter->id)
            ->with('success', $message);
    }

    public function acknowledge(Request $request, CorrespondenceLetterDistribution $distribution)
    {
        $letter = $distribution->letter;
        if (!$letter) {
            abort(404);
        }

        $this->assertCanView($letter);

        if (!$this->canAccessDistribution($distribution)) {
            return back()->with('error', localize('permission_denied', 'Permission denied.'));
        }

        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $distribution->update([
            'acknowledged_at' => now(),
            'status' => CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
            'acknowledgement_note' => trim((string) ($validated['note'] ?? '')) ?: $distribution->acknowledgement_note,
        ]);

        $this->logAction(
            $letter,
            'acknowledge',
            (string) $letter->current_step,
            (int) ($distribution->target_user_id ?? 0) ?: null,
            (int) ($distribution->target_department_id ?? 0) ?: null,
            $distribution->acknowledgement_note
        );

        if ((int) ($letter->source_distribution_id ?? 0) > 0) {
            $this->syncParentDistributionFromChild(
                $letter,
                CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
                $distribution->acknowledgement_note ?: localize('child_unit_acknowledged', 'Child unit acknowledged receipt.')
            );
        }

        $this->refreshCompletionState($letter);

        if ($this->shouldReturnApiResponse($request)) {
            return $this->correspondenceApiResponse(
                $this->freshSerializedDistribution($distribution),
                localize('acknowledged', 'Acknowledged.')
            );
        }

        return redirect()
            ->route('correspondence.show', $letter->id)
            ->with('success', localize('acknowledged', 'Acknowledged.'));
    }

    public function feedback(Request $request, CorrespondenceLetterDistribution $distribution)
    {
        $letter = $distribution->letter;
        if (!$letter) {
            abort(404);
        }

        $this->assertCanView($letter);

        if (!$this->canAccessDistribution($distribution)) {
            return back()->with('error', localize('permission_denied', 'Permission denied.'));
        }

        $validated = $request->validate([
            'feedback_note' => ['required', 'string', 'max:5000'],
        ]);

        $feedbackNote = trim((string) $validated['feedback_note']);

        $distribution->update([
            'feedback_note' => $feedbackNote,
            'feedback_at' => now(),
            'status' => CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
        ]);

        $this->logAction(
            $letter,
            'feedback',
            (string) $letter->current_step,
            (int) ($distribution->target_user_id ?? 0) ?: null,
            (int) ($distribution->target_department_id ?? 0) ?: null,
            $feedbackNote
        );

        if ((int) ($letter->source_distribution_id ?? 0) > 0) {
            $this->syncParentDistributionFromChild(
                $letter,
                CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
                $feedbackNote
            );
        }

        $this->refreshCompletionState($letter);

        if ($this->shouldReturnApiResponse($request)) {
            return $this->correspondenceApiResponse(
                $this->freshSerializedDistribution($distribution),
                localize('feedback_saved', 'Feedback saved.')
            );
        }

        return redirect()
            ->route('correspondence.show', $letter->id)
            ->with('success', localize('feedback_saved', 'Feedback saved.'));
    }

    public function feedbackParent(Request $request, CorrespondenceLetter $letter)
    {
        $this->assertCanView($letter);

        $sourceDistributionId = (int) ($letter->source_distribution_id ?? 0);
        if ($sourceDistributionId <= 0) {
            return back()->with('error', localize('parent_distribution_not_found', 'Parent distribution link was not found.'));
        }

        $canFeedback = ((int) ($letter->current_handler_user_id ?? 0) === (int) Auth::id())
            || $this->canPerformModuleAction($letter, 'feedback', [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD]);

        if (!$canFeedback) {
            return back()->with('error', localize('permission_denied', 'Permission denied.'));
        }

        $validated = $request->validate([
            'feedback_note' => ['required', 'string', 'max:5000'],
        ]);

        $feedbackNote = trim((string) $validated['feedback_note']);

        $sourceDistribution = CorrespondenceLetterDistribution::query()->find($sourceDistributionId);
        if (!$sourceDistribution) {
            return back()->with('error', localize('parent_distribution_not_found', 'Parent distribution link was not found.'));
        }

        $this->syncParentDistributionFromChild(
            $letter,
            CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
            $feedbackNote
        );

        $this->logAction(
            $letter,
            'feedback_to_parent',
            (string) $letter->current_step,
            (int) ($sourceDistribution->target_user_id ?? 0) ?: null,
            (int) ($sourceDistribution->target_department_id ?? 0) ?: null,
            $feedbackNote,
            ['source_distribution_id' => $sourceDistributionId]
        );

        if ($this->shouldReturnApiResponse($request)) {
            return $this->correspondenceApiResponse(
                null,
                localize('feedback_sent_to_parent', 'Feedback has been sent to the parent department.')
            );
        }

        return redirect()
            ->route('correspondence.show', $letter->id)
            ->with('success', localize('feedback_sent_to_parent', 'Feedback has been sent to the parent department.'));
    }

    public function markNotificationsRead(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $user->unreadNotifications()
            ->where('type', CorrespondenceAssignedNotification::class)
            ->update(['read_at' => now()]);

        if ($request->expectsJson()) {
            return response()->json(['status' => 'ok']);
        }

        return back();
    }

    public function clearNotifications(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $deleted = $user->notifications()
            ->where('type', CorrespondenceAssignedNotification::class)
            ->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'ok',
                'deleted_count' => (int) $deleted,
            ]);
        }

        return back()->with(
            'success',
            localize('correspondence_notifications_cleared', 'Correspondence notifications cleared successfully.')
        );
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));
        $limit = max(10, min(50, (int) $request->query('limit', 20)));
        $authUser = Auth::user();
        $departmentIds = collect($request->query('department_ids', []));

        if ($departmentIds->isEmpty() && !empty($request->query('department_id'))) {
            $departmentIds = collect([$request->query('department_id')]);
        }

        if ($departmentIds->count() === 1 && is_string($departmentIds->first()) && str_contains((string) $departmentIds->first(), ',')) {
            $departmentIds = collect(explode(',', (string) $departmentIds->first()));
        }

        $departmentIds = $departmentIds
            ->flatMap(function ($value) {
                return is_array($value) ? $value : [$value];
            })
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        // Users with correspondence workflow permission can search all users (needed to
        // assign director / deputy / office-comment roles across org levels).
        // For other non-admin users restrict to their managed branches only.
        $canSearchAll = $this->orgAccessService()->isSystemAdmin($authUser)
            || ($authUser && method_exists($authUser, 'can') && $authUser->can('update_correspondence_management'));

        $departmentRoleUserIds = collect();
        if ($departmentIds->isNotEmpty()) {
            $departmentRoleUserIds = UserOrgRole::query()
                ->effective()
                ->whereIn('department_id', $departmentIds->all())
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values();
        }

        $rows = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->when(
                !$canSearchAll,
                function ($query) use ($authUser) {
                    $managedBranchIds = $this->orgAccessService()->managedBranchIds($authUser);
                    $authUserId = (int) ($authUser->id ?? 0);

                    if (is_array($managedBranchIds) && !empty($managedBranchIds)) {
                        $query->where(function ($q) use ($managedBranchIds, $authUserId) {
                            $q->where('id', $authUserId)
                                ->orWhereHas('employee', function ($employeeQuery) use ($managedBranchIds) {
                                    $employeeQuery->where(function ($deptQuery) use ($managedBranchIds) {
                                        $deptQuery
                                            ->whereIn('department_id', $managedBranchIds)
                                            ->orWhereIn('sub_department_id', $managedBranchIds);
                                    });
                                });
                        });
                    } else {
                        $query->where('id', $authUserId);
                    }
                }
            )
            ->when($departmentIds->isNotEmpty(), function ($query) use ($departmentIds, $departmentRoleUserIds) {
                $departmentIdList = $departmentIds->all();
                $query->where(function ($q) use ($departmentIdList, $departmentRoleUserIds) {
                    $q->whereHas('employee', function ($employeeQuery) use ($departmentIdList) {
                        $employeeQuery->where(function ($deptQuery) use ($departmentIdList) {
                            $deptQuery
                                ->whereIn('department_id', $departmentIdList)
                                ->orWhereIn('sub_department_id', $departmentIdList);
                        });
                    });

                    if ($departmentRoleUserIds->isNotEmpty()) {
                        $q->orWhereIn('id', $departmentRoleUserIds->all());
                    }
                });
            })
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('full_name', 'like', '%' . $keyword . '%')
                        ->orWhere('email', 'like', '%' . $keyword . '%');
                });
            })
            ->orderBy('full_name')
            ->limit($limit)
            ->get(['id', 'full_name', 'email']);

        $results = $rows->map(function ($user) {
                $name = trim((string) ($user->full_name ?? ''));
                $email = trim((string) ($user->email ?? ''));
                return [
                    'id' => (int) $user->id,
                    'text' => $email !== '' ? "{$name} ({$email})" : $name,
                ];
            })->values();

        if ($this->shouldReturnApiResponse($request)) {
            return $this->correspondenceApiResponse($results);
        }

        return response()->json([
            'results' => $results,
        ]);
    }

    public function orgUnits(Request $request): JsonResponse
    {
        $options = $this->originOrgUnitOptions()
            ->map(function ($unit) {
                $path = trim((string) ($unit->path ?? ''));
                $label = trim((string) ($unit->label ?? ''));
                $displayName = $path !== '' ? $path : $label;

                return [
                    'id' => (int) ($unit->id ?? 0),
                    'text' => $displayName,
                    'path' => $path !== '' ? $path : null,
                    'label' => $label !== '' ? $label : $displayName,
                ];
            })
            ->values();

        if ($this->shouldReturnApiResponse($request)) {
            return $this->correspondenceApiResponse($options);
        }

        return response()->json([
            'results' => $options,
        ]);
    }

    protected function shouldReturnApiResponse(Request $request): bool
    {
        return $request->expectsJson() || $request->is('api/*');
    }

    protected function correspondenceApiResponse($data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'response' => [
                'status' => 'ok',
                'message' => $message,
                'data' => $data,
            ],
        ], $status);
    }

    protected function paginatedLetterPayload($paginator): array
    {
        $items = collect($paginator->items())
            ->map(fn (CorrespondenceLetter $letter) => $this->serializeLetter($letter))
            ->values()
            ->all();

        $currentPage = method_exists($paginator, 'currentPage') ? (int) $paginator->currentPage() : 1;
        $perPage = method_exists($paginator, 'perPage') ? (int) $paginator->perPage() : count($items);
        $total = method_exists($paginator, 'total')
            ? (int) $paginator->total()
            : (($currentPage - 1) * max(1, $perPage)) + count($items) + ((method_exists($paginator, 'hasMorePages') && $paginator->hasMorePages()) ? 1 : 0);
        $lastPage = method_exists($paginator, 'lastPage')
            ? (int) $paginator->lastPage()
            : $currentPage + ((method_exists($paginator, 'hasMorePages') && $paginator->hasMorePages()) ? 1 : 0);

        return [
            'data' => $items,
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, $lastPage),
        ];
    }

    protected function freshSerializedLetter(CorrespondenceLetter $letter): array
    {
        $fresh = $letter->fresh([
            'originDepartment:id,department_name',
            'assignedDepartment:id,department_name',
            'currentHandler:id,full_name',
            'actions',
            'distributions.targetDepartment:id,department_name',
            'distributions.targetUser:id,full_name,email',
        ]);

        return $this->serializeLetter($fresh ?? $letter);
    }

    protected function freshSerializedDistribution(CorrespondenceLetterDistribution $distribution): array
    {
        $fresh = $distribution->fresh([
            'targetDepartment:id,department_name',
            'targetUser:id,full_name,email',
        ]);

        return $this->serializeDistribution($fresh ?? $distribution);
    }

    protected function serializeLetter(CorrespondenceLetter $letter, array $userMap = [], array $departmentMap = []): array
    {
        $originDepartmentName = optional($letter->originDepartment)->department_name
            ?? ($departmentMap[(int) ($letter->origin_department_id ?? 0)] ?? null);
        $assignedDepartmentName = optional($letter->assignedDepartment)->department_name
            ?? ($departmentMap[(int) ($letter->assigned_department_id ?? 0)] ?? null);
        $currentHandlerName = optional($letter->currentHandler)->full_name
            ?? ($userMap[(int) ($letter->current_handler_user_id ?? 0)] ?? null);
        $createdByName = $userMap[(int) ($letter->created_by ?? 0)] ?? null;

        $payload = [
            'id' => (int) $letter->id,
            'letter_type' => (string) ($letter->letter_type ?? ''),
            'subject' => (string) ($letter->subject ?? ''),
            'status' => (string) ($letter->status ?? ''),
            'current_step' => (string) ($letter->current_step ?? ''),
            'letter_no' => $letter->letter_no,
            'registry_no' => $letter->registry_no,
            'priority' => $letter->priority,
            'from_org' => $letter->from_org,
            'to_org' => $letter->to_org,
            'letter_date' => optional($letter->letter_date)->toDateTimeString(),
            'received_date' => optional($letter->received_date)->toDateTimeString(),
            'sent_date' => optional($letter->sent_date)->toDateTimeString(),
            'due_date' => optional($letter->due_date)->toDateTimeString(),
            'attachment_path' => $letter->attachment_path,
            'current_handler_user_id' => $letter->current_handler_user_id ? (int) $letter->current_handler_user_id : null,
            'current_handler_name' => $currentHandlerName,
            'origin_department_id' => $letter->origin_department_id ? (int) $letter->origin_department_id : null,
            'origin_department_name' => $originDepartmentName,
            'assigned_department_id' => $letter->assigned_department_id ? (int) $letter->assigned_department_id : null,
            'assigned_department_name' => $assignedDepartmentName,
            'parent_letter_id' => $letter->parent_letter_id ? (int) $letter->parent_letter_id : null,
            'source_distribution_id' => $letter->source_distribution_id ? (int) $letter->source_distribution_id : null,
            'created_by_user_id' => $letter->created_by ? (int) $letter->created_by : null,
            'created_by_name' => $createdByName,
            'created_at' => optional($letter->created_at)->toDateTimeString(),
            'decision' => $letter->final_decision,
            'distributions_count' => isset($letter->distributions_total_count)
                ? (int) $letter->distributions_total_count
                : (isset($letter->distributions_count) ? (int) $letter->distributions_count : null),
            'completed_count' => isset($letter->distributions_done_count) ? (int) $letter->distributions_done_count : null,
        ];

        if ($letter->relationLoaded('actions')) {
            $payload['actions'] = $letter->actions
                ->map(fn (CorrespondenceLetterAction $action) => $this->serializeAction($action, $userMap, $departmentMap))
                ->values()
                ->all();
        }

        if ($letter->relationLoaded('distributions')) {
            $payload['distributions'] = $letter->distributions
                ->map(fn (CorrespondenceLetterDistribution $distribution) => $this->serializeDistribution($distribution, $userMap, $departmentMap))
                ->values()
                ->all();
        }

        return $payload;
    }

    protected function serializeAction(CorrespondenceLetterAction $action, array $userMap = [], array $departmentMap = []): array
    {
        return [
            'id' => (int) $action->id,
            'letter_id' => (int) $action->letter_id,
            'step_key' => (string) ($action->step_key ?? ''),
            'action_type' => (string) ($action->action_type ?? ''),
            'acted_by_user_id' => $action->acted_by ? (int) $action->acted_by : null,
            'acted_by_name' => $userMap[(int) ($action->acted_by ?? 0)] ?? null,
            'target_user_id' => $action->target_user_id ? (int) $action->target_user_id : null,
            'target_user_name' => $userMap[(int) ($action->target_user_id ?? 0)] ?? null,
            'target_department_id' => $action->target_department_id ? (int) $action->target_department_id : null,
            'target_department_name' => $departmentMap[(int) ($action->target_department_id ?? 0)] ?? null,
            'note' => $action->note,
            'meta_json' => $action->meta_json,
            'created_at' => optional($action->created_at)->toDateTimeString(),
        ];
    }

    protected function serializeDistribution(CorrespondenceLetterDistribution $distribution, array $userMap = [], array $departmentMap = []): array
    {
        $canAccess = $this->canAccessDistribution($distribution);
        $distributionStatus = (string) ($distribution->status ?? '');

        return [
            'id' => (int) $distribution->id,
            'letter_id' => (int) $distribution->letter_id,
            'target_department_id' => $distribution->target_department_id ? (int) $distribution->target_department_id : null,
            'target_department_name' => optional($distribution->targetDepartment)->department_name
                ?? ($departmentMap[(int) ($distribution->target_department_id ?? 0)] ?? null),
            'target_user_id' => $distribution->target_user_id ? (int) $distribution->target_user_id : null,
            'target_user_name' => optional($distribution->targetUser)->full_name
                ?? ($userMap[(int) ($distribution->target_user_id ?? 0)] ?? null),
            'distribution_type' => (string) ($distribution->distribution_type ?? ''),
            'status' => (string) ($distribution->status ?? ''),
            'acknowledged_at' => optional($distribution->acknowledged_at)->toDateTimeString(),
            'feedback_note' => $distribution->feedback_note,
            'child_letter_id' => $distribution->child_letter_id ? (int) $distribution->child_letter_id : null,
            'created_at' => optional($distribution->created_at)->toDateTimeString(),
            'can_acknowledge' => $canAccess && $distributionStatus === CorrespondenceLetterDistribution::STATUS_PENDING_ACK,
            'can_feedback' => $canAccess && in_array($distributionStatus, [
                CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
                CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
            ], true),
        ];
    }

    protected function accessibleLettersQuery(?string $type = null)
    {
        $query = CorrespondenceLetter::query();
        if ($type !== null) {
            $query->where('letter_type', $type);
        }

        $user = Auth::user();
        if (!$user) {
            return $query->whereRaw('1=0');
        }

        if ($this->orgAccessService()->isSystemAdmin($user) || $this->isCorrespondenceManager($user)) {
            return $query;
        }

        // Non-manager users only see letters directly related to themselves.
        $userId = (int) $user->id;

        $query->where(function ($q) use ($userId) {
            $q->where('created_by', $userId)
                ->orWhere('current_handler_user_id', $userId)
                ->orWhere(function ($workflowQuery) use ($userId) {
                    $workflowQuery->where('letter_type', CorrespondenceLetter::TYPE_INCOMING)
                        ->whereHas('actions', function ($actionQuery) use ($userId) {
                            $actionQuery->where('action_type', 'delegate')
                                ->where(function ($metaQuery) use ($userId) {
                                    $metaQuery->where('meta_json', 'like', '%"office_comment_user_id":' . $userId . '%')
                                        ->orWhere('meta_json', 'like', '%"deputy_review_user_id":' . $userId . '%')
                                        ->orWhere('meta_json', 'like', '%"director_user_id":' . $userId . '%')
                                        ->orWhere('meta_json', 'like', '%"office_comment_related_user_ids":%"' . $userId . '"%')
                                        ->orWhere('meta_json', 'like', '%"office_comment_related_user_ids":%,' . $userId . ',%')
                                        ->orWhere('meta_json', 'like', '%"office_comment_related_user_ids":%[' . $userId . ',%')
                                        ->orWhere('meta_json', 'like', '%"office_comment_related_user_ids":%,' . $userId . ']%')
                                        ->orWhere('meta_json', 'like', '%"office_comment_related_user_ids":%[' . $userId . ']%');
                                });
                        });
                })
                ->orWhereHas('distributions', function ($distributionQuery) use ($userId) {
                    $distributionQuery->where('target_user_id', $userId);
                });
        });

        return $query;
    }

    protected function assertCanView(CorrespondenceLetter $letter): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        if ($this->orgAccessService()->isSystemAdmin($user) || $this->isCorrespondenceManager($user)) {
            return;
        }

        $userId = (int) $user->id;
        $hasDistribution = $letter->distributions()->exists();

        // Undistributed letters are private except for explicit workflow participants.
        if (!$hasDistribution) {
            if (
                (int) $letter->created_by === $userId
                || (int) $letter->current_handler_user_id === $userId
                || $this->isUserInIncomingWorkflowChain($letter, $userId)
            ) {
                return;
            }

            abort(403);
        }

        if (
            (int) $letter->created_by === $userId
            || (int) $letter->current_handler_user_id === $userId
            || $this->isUserInIncomingWorkflowChain($letter, $userId)
            || $letter->distributions()->where('target_user_id', $userId)->exists()
        ) {
            return;
        }

        abort(403);
    }

    protected function isCorrespondenceManager(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if (!Schema::hasTable('correspondence_user_responsibilities') || !Schema::hasTable('correspondence_responsibility_templates')) {
            return false;
        }

        $today = now()->toDateString();

        return DB::table('correspondence_user_responsibilities as cur')
            ->join('correspondence_responsibility_templates as crt', 'crt.id', '=', 'cur.template_id')
            ->where('cur.user_id', (int) $user->id)
            ->where('cur.is_active', 1)
            ->where('crt.is_active', 1)
            ->where('crt.template_key', self::MANAGER_TEMPLATE_KEY)
            ->whereNull('cur.deleted_at')
            ->whereNull('crt.deleted_at')
            ->where(function ($query) use ($today) {
                $query->whereNull('cur.effective_from')->orWhereDate('cur.effective_from', '<=', $today);
            })
            ->where(function ($query) use ($today) {
                $query->whereNull('cur.effective_to')->orWhereDate('cur.effective_to', '>=', $today);
            })
            ->exists();
    }

    protected function isUserInIncomingWorkflowChain(CorrespondenceLetter $letter, int $userId): bool
    {
        if ($userId <= 0 || (string) $letter->letter_type !== CorrespondenceLetter::TYPE_INCOMING) {
            return false;
        }

        $delegateAction = CorrespondenceLetterAction::query()
            ->where('letter_id', (int) $letter->id)
            ->where('action_type', 'delegate')
            ->latest('id')
            ->first(['meta_json']);

        if (!$delegateAction) {
            return false;
        }

        $meta = $delegateAction->meta_json;
        if (!is_array($meta)) {
            $decoded = json_decode((string) $meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }

        $relatedOfficeUserIds = collect($meta['office_comment_related_user_ids'] ?? [])
            ->flatMap(function ($value) {
                return is_array($value) ? $value : [$value];
            })
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($relatedOfficeUserIds->contains($userId)) {
            return true;
        }

        return in_array($userId, [
            (int) ($meta['office_comment_user_id'] ?? 0),
            (int) ($meta['deputy_review_user_id'] ?? 0),
            (int) ($meta['director_user_id'] ?? 0),
        ], true);
    }

    protected function canPerformRoleAction(CorrespondenceLetter $letter, array $requiredRoles): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($this->orgAccessService()->isSystemAdmin($user)) {
            return true;
        }

        $roles = $this->orgAccessService()
            ->effectiveOrgRoles($user)
            ->filter(function (UserOrgRole $role) use ($requiredRoles) {
                return in_array((string) $role->getEffectiveRoleCode(), $requiredRoles, true);
            });

        if ($roles->isEmpty()) {
            return false;
        }

        $targetDepartmentId = (int) ($letter->assigned_department_id ?: $letter->origin_department_id);
        if ($targetDepartmentId <= 0) {
            return true;
        }

        foreach ($roles as $role) {
            $roleDepartmentId = (int) ($role->department_id ?? 0);
            if ($roleDepartmentId <= 0) {
                continue;
            }

            $scopeType = (string) ($role->scope_type ?: UserOrgRole::SCOPE_SELF_AND_CHILDREN);
            if (in_array($scopeType, [UserOrgRole::SCOPE_SELF, UserOrgRole::SCOPE_SELF_ONLY], true)) {
                if ($roleDepartmentId === $targetDepartmentId) {
                    return true;
                }
                continue;
            }

            $branchIds = $this->orgUnitRuleService()->branchIdsIncludingSelf($roleDepartmentId);
            if (in_array($targetDepartmentId, $branchIds, true)) {
                return true;
            }
        }

        return false;
    }

    protected function canDelegateIncoming(CorrespondenceLetter $letter): bool
    {
        if ($letter->letter_type !== CorrespondenceLetter::TYPE_INCOMING) {
            return false;
        }

        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($this->orgAccessService()->isSystemAdmin($user)) {
            return true;
        }

        $receivingDepartmentId = (int) ($letter->origin_department_id ?: $letter->assigned_department_id);
        if ($receivingDepartmentId <= 0) {
            return false;
        }

        return $this->orgAccessService()
            ->effectiveOrgRoles($user)
            ->contains(function (UserOrgRole $role) use ($receivingDepartmentId) {
                $roleCode = $role->getEffectiveRoleCode();
                return in_array($roleCode, [
                    UserOrgRole::ROLE_MANAGER,
                    UserOrgRole::ROLE_DEPUTY_HEAD,
                ], true) && (int) ($role->department_id ?? 0) === $receivingDepartmentId;
            });
    }

    protected function canDistributeLetter(CorrespondenceLetter $letter): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $isOwnerActor = $this->isRecipientActor($letter);
        $hasUpdatePermission = method_exists($user, 'can')
            && $user->can('update_correspondence_management');
        $hasRoleActionPermission = $this->canPerformModuleAction(
            $letter,
            'distribute',
            [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD]
        );

        if ($letter->letter_type === CorrespondenceLetter::TYPE_INCOMING) {
            $isStepReady = (string) $letter->current_step === CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION
                && (string) $letter->final_decision === CorrespondenceLetter::DECISION_APPROVED;

            if (!$isStepReady) {
                return false;
            }

            // Incoming distribution must be performed by the actor who currently owns this step.
            if (!$isOwnerActor) {
                return false;
            }

            return $hasRoleActionPermission || $hasUpdatePermission;
        }

        if (!in_array((string) $letter->current_step, [
            CorrespondenceLetter::STEP_OUTGOING_DRAFT,
            CorrespondenceLetter::STEP_OUTGOING_DISTRIBUTED,
        ], true)) {
            return false;
        }

        // Outgoing distribution is also limited to the current step owner.
        if (!$isOwnerActor) {
            return false;
        }

        return $hasRoleActionPermission || $hasUpdatePermission;
    }

    protected function canPerformModuleAction(
        CorrespondenceLetter $letter,
        string $actionKey,
        array $fallbackRoles = []
    ): bool {
        $targetDepartmentId = (int) ($letter->assigned_department_id ?: $letter->origin_department_id);

        return $this->rolePermissionService()->canUserPerform(
            Auth::user(),
            'correspondence',
            $actionKey,
            $targetDepartmentId > 0 ? $targetDepartmentId : null,
            $fallbackRoles
        );
    }

    protected function canAccessDistribution(CorrespondenceLetterDistribution $distribution): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($this->orgAccessService()->isSystemAdmin($user)) {
            return true;
        }

        if ((int) ($distribution->target_user_id ?? 0) === (int) $user->id) {
            return true;
        }

        return $this->canPerformModuleAction($distribution->letter, 'acknowledge', [
            UserOrgRole::ROLE_MANAGER,
            UserOrgRole::ROLE_DEPUTY_HEAD,
            UserOrgRole::ROLE_HEAD,
        ]);
    }

    protected function isRecipientActor(CorrespondenceLetter $letter): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $handlerUserId = (int) ($letter->current_handler_user_id ?? 0);
        if ($handlerUserId > 0) {
            return $handlerUserId === (int) $user->id;
        }

        // Fallback for letters without explicit handler: creator is treated as current recipient.
        return (int) ($letter->created_by ?? 0) === (int) $user->id;
    }

    protected function resolveDefaultOriginDepartmentId(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        $role = $this->orgAccessService()->effectiveOrgRoles($user)->first();
        return (int) ($role->department_id ?? 0);
    }

    protected function navPermissionData(): array
    {
        $level = $this->corrLevel();
        $dept = $this->corrUserDepartment();
        $user = Auth::user();
        $canManageSettings = false;

        if ($user) {
            $canManageSettings = $this->orgAccessService()->isSystemAdmin($user)
                || $user->can('setting_correspondence_management');
        }

        return [
            'corrLevel' => $level,
            'corrLevelLabel' => $this->corrLevelLabel(),
            'corrDepartmentName' => $dept ? (string) $dept->department_name : '',
            'canCreateIncoming' => $this->canCreateType(CorrespondenceLetter::TYPE_INCOMING),
            'canCreateOutgoing' => $this->canCreateType(CorrespondenceLetter::TYPE_OUTGOING),
            'canManageSettings' => $canManageSettings,
        ];
    }

    protected function assertCanManageSettings(): void
    {
        if ($this->canManageSettings(Auth::user())) {
            return;
        }

        abort(403, localize('permission_denied', 'Permission denied.'));
    }

    protected function canManageSettings(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $this->orgAccessService()->isSystemAdmin($user)
            || $user->can('setting_correspondence_management');
    }

    protected function resolveSettingDepartmentForUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $roleQuery = UserOrgRole::query()
            ->withoutGlobalScope('sortByLatest')
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->whereNull('deleted_at');

        if (Schema::hasColumn('user_org_roles', 'is_primary')) {
            $roleQuery->orderByDesc('is_primary');
        }
        if (Schema::hasColumn('user_org_roles', 'effective_from')) {
            $roleQuery->orderByDesc('effective_from');
        }

        $roleQuery->orderByDesc('id');

        $departmentId = (int) ($roleQuery->value('department_id') ?? 0);

        if ($departmentId > 0) {
            return $departmentId;
        }

        $employee = Employee::query()
            ->withoutGlobalScope('sortByLatest')
            ->where('user_id', $userId)
            ->first(['department_id', 'sub_department_id']);

        return (int) ($employee?->sub_department_id ?: $employee?->department_id ?: 0);
    }

    protected function assertCanCreateType(string $type): void
    {
        if ($this->canCreateType($type)) {
            return;
        }

        abort(403, localize('permission_denied', 'Permission denied.'));
    }

    protected function canCreateType(string $type): bool
    {
        if (!in_array($type, [CorrespondenceLetter::TYPE_INCOMING, CorrespondenceLetter::TYPE_OUTGOING], true)) {
            return false;
        }

        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $departmentId = (int) ($this->corrUserDepartment()?->id ?? 0);
        $actionKey = $type === CorrespondenceLetter::TYPE_INCOMING ? 'create_incoming' : 'create_outgoing';

        return app(ModuleTableGovernanceService::class)->canUserPerform(
            $user,
            'correspondence',
            $actionKey,
            $departmentId > 0 ? $departmentId : null
        );
    }

    protected function originOrgUnitOptions()
    {
        $allowedCodes = [
            'office',
            'operational_district',
            'provincial_hospital',
            'district_hospital',
            'health_center',
            'health_center_with_bed',
            'health_center_without_bed',
        ];

        $allowedTypeIds = OrgUnitType::query()
            ->whereIn('code', $allowedCodes)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $options = $this->orgUnitRuleService()->hierarchyOptions();
        if (empty($allowedTypeIds)) {
            return $options;
        }

        return $options->filter(function ($unit) use ($allowedTypeIds) {
            return in_array((int) ($unit->unit_type_id ?? 0), $allowedTypeIds, true);
        })->values();
    }

    protected function resolveRegistryNo(array $validated): ?string
    {
        $input = trim((string) ($validated['registry_no'] ?? ''));
        if ($input !== '') {
            return $input;
        }

        $date = $validated['received_date'] ?? $validated['letter_date'] ?? null;
        return $this->generateRegistryNo($date);
    }

    protected function generateRegistryNo(?string $date = null): string
    {
        $year = $date ? Carbon::parse($date)->year : now()->year;
        $yy = substr((string) $year, -2);

        $existing = CorrespondenceLetter::query()
            ->where('registry_no', 'like', '%/' . $yy)
            ->pluck('registry_no');

        $max = 0;
        foreach ($existing as $registryNo) {
            $registryNo = trim((string) $registryNo);
            if (preg_match('/^(\d+)\s*\/\s*' . preg_quote($yy, '/') . '$/', $registryNo, $matches)) {
                $value = (int) $matches[1];
                if ($value > $max) {
                    $max = $value;
                }
            }
        }

        $next = str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
        return $next . '/' . $yy;
    }

    protected function refreshCompletionState(CorrespondenceLetter $letter): void
    {
        $total = $letter->distributions()->count();
        if ($total <= 0) {
            return;
        }

        // Outgoing is completed when all recipients have at least acknowledged.
        // Incoming remains feedback-driven (feedback sent/closed).
        $doneStatuses = $letter->letter_type === CorrespondenceLetter::TYPE_OUTGOING
            ? [
                CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED,
                CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
                CorrespondenceLetterDistribution::STATUS_CLOSED,
            ]
            : [
                CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
                CorrespondenceLetterDistribution::STATUS_CLOSED,
            ];

        $doneCount = $letter->distributions()
            ->whereIn('status', $doneStatuses)
            ->count();

        if ($doneCount >= $total) {
            $letter->update([
                'current_step' => CorrespondenceLetter::STEP_CLOSED,
                'status' => CorrespondenceLetter::STATUS_COMPLETED,
                'completed_at' => now(),
                'updated_by' => (int) Auth::id(),
            ]);
        }
    }

    protected function createChildIncomingLetterFromDistribution(
        CorrespondenceLetter $parentLetter,
        CorrespondenceLetterDistribution $distribution,
        int $targetDepartmentId,
        ?int $targetUserId = null,
        string $note = ''
    ): ?CorrespondenceLetter {
        $targetDepartment = Department::withoutGlobalScopes()->find($targetDepartmentId);
        if (!$targetDepartment) {
            return null;
        }

        $sourceDepartmentId = (int) ($parentLetter->assigned_department_id ?: $parentLetter->origin_department_id);
        $sourceDepartmentName = $this->resolveDepartmentName($sourceDepartmentId);

        $fromOrg = $sourceDepartmentName !== ''
            ? $sourceDepartmentName
            : ((string) ($parentLetter->to_org ?: $parentLetter->from_org));

        $summary = (string) ($parentLetter->summary ?? '');
        if ($note !== '') {
            $summary = trim($summary . "\n" . localize('forward_note', 'Forward note') . ': ' . $note);
        }

        $childLetter = CorrespondenceLetter::create([
            'letter_type' => CorrespondenceLetter::TYPE_INCOMING,
            'registry_no' => $parentLetter->registry_no,
            'letter_no' => $parentLetter->letter_no,
            'subject' => $parentLetter->subject,
            'from_org' => $fromOrg,
            'to_org' => (string) ($targetDepartment->department_name ?? ''),
            'priority' => (string) ($parentLetter->priority ?: 'normal'),
            'status' => CorrespondenceLetter::STATUS_PENDING,
            'letter_date' => $parentLetter->letter_date,
            'received_date' => now()->toDateString(),
            'due_date' => $parentLetter->due_date,
            'summary' => $summary !== '' ? $summary : null,
            'origin_department_id' => $targetDepartmentId,
            'assigned_department_id' => $targetDepartmentId,
            'current_handler_user_id' => $targetUserId,
            'current_step' => CorrespondenceLetter::STEP_INCOMING_RECEIVED,
            'parent_letter_id' => (int) $parentLetter->id,
            'source_distribution_id' => (int) $distribution->id,
            'created_by' => (int) Auth::id(),
            'updated_by' => (int) Auth::id(),
        ]);

        $this->logAction(
            $childLetter,
            'auto_created_from_parent',
            CorrespondenceLetter::STEP_INCOMING_RECEIVED,
            $targetUserId,
            $targetDepartmentId,
            $note,
            [
                'parent_letter_id' => (int) $parentLetter->id,
                'source_distribution_id' => (int) $distribution->id,
            ]
        );

        return $childLetter;
    }

    protected function syncParentDistributionFromChild(
        CorrespondenceLetter $childLetter,
        string $status,
        ?string $note = null
    ): void {
        $sourceDistributionId = (int) ($childLetter->source_distribution_id ?? 0);
        if ($sourceDistributionId <= 0) {
            return;
        }

        $sourceDistribution = CorrespondenceLetterDistribution::query()->find($sourceDistributionId);
        if (!$sourceDistribution) {
            return;
        }

        $updates = [];
        $trimmedNote = trim((string) ($note ?? ''));

        if ($status === CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED) {
            if (in_array((string) $sourceDistribution->status, [
                CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
                CorrespondenceLetterDistribution::STATUS_CLOSED,
            ], true)) {
                return;
            }
            $updates['status'] = CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED;
            if (!$sourceDistribution->acknowledged_at) {
                $updates['acknowledged_at'] = now();
            }
            if ($trimmedNote !== '') {
                $updates['acknowledgement_note'] = $trimmedNote;
            }
        }

        if ($status === CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT) {
            $updates['status'] = CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT;
            $updates['feedback_at'] = now();
            if ($trimmedNote !== '') {
                $updates['feedback_note'] = $trimmedNote;
            }
        }

        if (empty($updates)) {
            return;
        }

        $sourceDistribution->update($updates);

        $parentLetter = $sourceDistribution->letter;
        if ($parentLetter) {
            $this->logAction(
                $parentLetter,
                $status === CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT ? 'child_feedback_received' : 'child_acknowledged',
                (string) $parentLetter->current_step,
                (int) ($sourceDistribution->target_user_id ?? 0) ?: null,
                (int) ($sourceDistribution->target_department_id ?? 0) ?: null,
                $trimmedNote !== '' ? $trimmedNote : null,
                [
                    'child_letter_id' => (int) $childLetter->id,
                    'source_distribution_id' => $sourceDistributionId,
                ]
            );
            $this->refreshCompletionState($parentLetter);
        }
    }

    protected function resolveDepartmentName(int $departmentId): string
    {
        if ($departmentId <= 0) {
            return '';
        }

        $department = Department::withoutGlobalScopes()
            ->where('id', $departmentId)
            ->first(['department_name']);

        return trim((string) ($department->department_name ?? ''));
    }

    protected function incomingWorkflowAssignments(CorrespondenceLetter $letter): array
    {
        $delegateAction = $letter->actions
            ->where('action_type', 'delegate')
            ->sortByDesc('id')
            ->first();

        $meta = (array) ($delegateAction->meta_json ?? []);
        $assignedDepartmentIds = collect($meta['assigned_department_ids'] ?? [])
            ->flatMap(function ($value) {
                return is_array($value) ? $value : [$value];
            })
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($assignedDepartmentIds->isEmpty()) {
            $fallbackDepartmentId = (int) ($letter->assigned_department_id ?: $letter->origin_department_id ?: 0);
            if ($fallbackDepartmentId > 0) {
                $assignedDepartmentIds = collect([$fallbackDepartmentId]);
            }
        }

        $relatedOfficeUserIds = collect($meta['office_comment_related_user_ids'] ?? [])
            ->flatMap(function ($value) {
                return is_array($value) ? $value : [$value];
            })
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        return [
            'assigned_department_ids' => $assignedDepartmentIds->all(),
            'office_comment_user_id' => (int) ($meta['office_comment_user_id'] ?? 0),
            'office_comment_related_user_ids' => $relatedOfficeUserIds->all(),
            'deputy_review_user_id' => (int) ($meta['deputy_review_user_id'] ?? 0),
            'director_user_id' => (int) ($meta['director_user_id'] ?? 0),
        ];
    }

    protected function repairIncomingWorkflowAssignments(CorrespondenceLetter $letter, array $assignments): array
    {
        $assignedDepartmentIds = collect($assignments['assigned_department_ids'] ?? [])
            ->flatMap(function ($value) {
                return is_array($value) ? $value : [$value];
            })
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($assignedDepartmentIds->isEmpty()) {
            $fallbackDepartmentId = (int) ($letter->assigned_department_id ?: $letter->origin_department_id ?: 0);
            if ($fallbackDepartmentId > 0) {
                $assignedDepartmentIds = collect([$fallbackDepartmentId]);
            }
        }

        $officeUserId = (int) ($assignments['office_comment_user_id'] ?? 0);
        if ($officeUserId <= 0) {
            $officeUserId = (int) ($letter->current_handler_user_id ?? 0);
        }

        $relatedOfficeUserIds = collect($assignments['office_comment_related_user_ids'] ?? [])
            ->flatMap(function ($value) {
                return is_array($value) ? $value : [$value];
            })
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->reject(fn ($id) => $id === $officeUserId)
            ->values();

        $deputyUserId = (int) ($assignments['deputy_review_user_id'] ?? 0);
        $directorUserId = (int) ($assignments['director_user_id'] ?? 0);
        $departmentId = (int) ($assignedDepartmentIds->first() ?? ($letter->assigned_department_id ?: $letter->origin_department_id ?: 0));

        if ($departmentId > 0 && $deputyUserId <= 0) {
            $deputyQuery = UserOrgRole::withoutGlobalScopes()
                ->effective()
                ->where('department_id', $departmentId)
                ->where(function ($query) {
                    $this->applyOrgRoleCodeFilter($query, [UserOrgRole::ROLE_DEPUTY_HEAD]);
                })
                ->when($officeUserId > 0, fn ($q) => $q->where('user_id', '!=', $officeUserId))
                ->orderByDesc('id');
            $deputyUserId = (int) ($deputyQuery->value('user_id') ?? 0);
        }

        if ($departmentId > 0 && $directorUserId <= 0) {
            $directorQuery = UserOrgRole::withoutGlobalScopes()
                ->effective()
                ->where('department_id', $departmentId)
                ->where(function ($query) {
                    $this->applyOrgRoleCodeFilter($query, [UserOrgRole::ROLE_HEAD]);
                })
                ->when($officeUserId > 0, fn ($q) => $q->where('user_id', '!=', $officeUserId))
                ->when($deputyUserId > 0, fn ($q) => $q->where('user_id', '!=', $deputyUserId))
                ->orderByDesc('id');
            $directorUserId = (int) ($directorQuery->value('user_id') ?? 0);
        }

        $relatedOfficeUserIds = $relatedOfficeUserIds
            ->reject(fn ($id) => in_array((int) $id, [$officeUserId, $deputyUserId, $directorUserId], true))
            ->values();

        $resolved = [
            'assigned_department_ids' => $assignedDepartmentIds->all(),
            'office_comment_user_id' => $officeUserId,
            'office_comment_related_user_ids' => $relatedOfficeUserIds->all(),
            'deputy_review_user_id' => $deputyUserId,
            'director_user_id' => $directorUserId,
        ];

        $delegateAction = CorrespondenceLetterAction::query()
            ->where('letter_id', (int) $letter->id)
            ->where('action_type', 'delegate')
            ->latest('id')
            ->first();

        if ($delegateAction) {
            $currentMeta = (array) ($delegateAction->meta_json ?? []);
            $updatedMeta = array_merge($currentMeta, $resolved);
            if ($updatedMeta !== $currentMeta) {
                $delegateAction->update(['meta_json' => $updatedMeta]);
            }
        }

        return $resolved;
    }

    protected function isRelatedOfficeCommentParticipant(CorrespondenceLetter $letter, int $userId): bool
    {
        if ($userId <= 0 || $letter->letter_type !== CorrespondenceLetter::TYPE_INCOMING) {
            return false;
        }

        $workflowAssignments = $this->incomingWorkflowAssignments($letter);
        $relatedIds = collect($workflowAssignments['office_comment_related_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        return $relatedIds->contains($userId);
    }

    protected function step3PendingRelatedUserIds(CorrespondenceLetter $letter, array $workflowAssignments): array
    {
        $relatedIds = collect($workflowAssignments['office_comment_related_user_ids'] ?? [])
            ->flatMap(function ($value) {
                return is_array($value) ? $value : [$value];
            })
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($relatedIds->isEmpty()) {
            return [];
        }

        $latestDelegateActionId = (int) CorrespondenceLetterAction::query()
            ->where('letter_id', (int) $letter->id)
            ->where('action_type', 'delegate')
            ->max('id');

        $completedIds = CorrespondenceLetterAction::query()
            ->where('letter_id', (int) $letter->id)
            ->whereIn('acted_by', $relatedIds->all())
            ->whereIn('action_type', ['office_comment_related', 'office_comment'])
            ->when($latestDelegateActionId > 0, fn ($q) => $q->where('id', '>', $latestDelegateActionId))
            ->pluck('acted_by')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        return $relatedIds
            ->reject(fn ($id) => $completedIds->contains((int) $id))
            ->values()
            ->all();
    }

    protected function formatUserDisplayList(array $userIds): string
    {
        $ids = collect($userIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return '-';
        }

        $names = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->whereIn('id', $ids)
            ->get(['id', 'full_name', 'email'])
            ->map(function ($item) {
                $name = trim((string) ($item->full_name ?? ''));
                $email = trim((string) ($item->email ?? ''));
                return $email !== '' ? ($name . ' (' . $email . ')') : ($name !== '' ? $name : ('#' . (int) $item->id));
            })
            ->values()
            ->all();

        if (!empty($names)) {
            return implode(', ', $names);
        }

        return implode(', ', array_map(fn ($id) => '#' . (int) $id, $ids));
    }

    protected function isUserLinkedToAnyDepartment(int $userId, array $departmentIds): bool
    {
        $userId = (int) $userId;
        $departmentIds = collect($departmentIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($userId <= 0 || empty($departmentIds)) {
            return false;
        }

        $inEmployee = Employee::query()
            ->where('user_id', $userId)
            ->where(function ($query) use ($departmentIds) {
                $query->whereIn('department_id', $departmentIds)
                    ->orWhereIn('sub_department_id', $departmentIds);
            })
            ->exists();

        if ($inEmployee) {
            return true;
        }

        return UserOrgRole::query()
            ->effective()
            ->where('user_id', $userId)
            ->whereIn('department_id', $departmentIds)
            ->exists();
    }

    protected function logAction(
        CorrespondenceLetter $letter,
        string $actionType,
        ?string $stepKey = null,
        ?int $targetUserId = null,
        ?int $targetDepartmentId = null,
        ?string $note = null,
        array $meta = []
    ): void {
        CorrespondenceLetterAction::create([
            'letter_id' => (int) $letter->id,
            'step_key' => $stepKey,
            'action_type' => $actionType,
            'acted_by' => Auth::id(),
            'target_user_id' => $targetUserId,
            'target_department_id' => $targetDepartmentId,
            'note' => $note !== '' ? $note : null,
            'meta_json' => !empty($meta) ? $meta : null,
            'acted_at' => now(),
        ]);
    }

    protected function markCorrespondenceNotificationsRead(int $letterId): void
    {
        if ($letterId <= 0) {
            return;
        }

        $user = Auth::user();
        if (!$user) {
            return;
        }

        $user->unreadNotifications()
            ->where('type', CorrespondenceAssignedNotification::class)
            ->where('data->letter_id', $letterId)
            ->update(['read_at' => now()]);
    }

    protected function notifyAssignmentRecipients(
        CorrespondenceLetter $letter,
        ?CorrespondenceLetterDistribution $distribution,
        string $note,
        string $context,
        ?int $targetUserId = null,
        ?int $targetDepartmentId = null,
        ?array $recipientRoles = null
    ): void {
        $resolvedTargetUserId = $targetUserId
            ?? ((int) ($distribution?->target_user_id ?? 0) ?: null);
        $resolvedTargetDepartmentId = $targetDepartmentId
            ?? ((int) ($distribution?->target_department_id ?? 0) ?: null);

        $recipientIds = collect();

        if ($resolvedTargetUserId) {
            $recipientIds->push($resolvedTargetUserId);
        }

        if ($resolvedTargetDepartmentId) {
            $roleFilters = !empty($recipientRoles)
                ? array_values(array_unique(array_filter(array_map(fn ($value) => trim((string) $value), $recipientRoles))))
                : [
                    UserOrgRole::ROLE_HEAD,
                    UserOrgRole::ROLE_DEPUTY_HEAD,
                    UserOrgRole::ROLE_MANAGER,
                ];

            $roleUserIds = UserOrgRole::query()
                ->effective()
                ->where('department_id', $resolvedTargetDepartmentId)
                ->where(function ($query) use ($roleFilters) {
                    $this->applyOrgRoleCodeFilter($query, $roleFilters);
                })
                ->pluck('user_id');

            $recipientIds = $recipientIds->merge($roleUserIds);

            if ($roleUserIds->isEmpty()) {
                $employeeUserIds = Employee::query()
                    ->where(function ($q) use ($resolvedTargetDepartmentId) {
                        $q->where('department_id', $resolvedTargetDepartmentId)
                            ->orWhere('sub_department_id', $resolvedTargetDepartmentId);
                    })
                    ->whereNotNull('user_id')
                    ->pluck('user_id');

                $recipientIds = $recipientIds->merge($employeeUserIds);
            }
        }

        $recipientIds = $recipientIds
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->reject(fn ($id) => (int) $id === (int) Auth::id());

        if ($recipientIds->isEmpty()) {
            return;
        }

        $targetDepartmentName = '';
        if ($resolvedTargetDepartmentId) {
            $targetDepartmentName = (string) Department::withoutGlobalScopes()
                ->where('id', $resolvedTargetDepartmentId)
                ->value('department_name');
        }

        $users = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->whereIn('id', $recipientIds->values())
            ->get(['id', 'full_name', 'email']);

        $actor = Auth::user();

        // Defer notification writes until after response to keep request latency low.
        app()->terminating(function () use (
            $users,
            $letter,
            $distribution,
            $actor,
            $context,
            $note,
            $targetDepartmentName,
            $resolvedTargetDepartmentId,
            $resolvedTargetUserId
        ) {
            foreach ($users as $recipient) {
                try {
                    $recipient->notify(new CorrespondenceAssignedNotification(
                        $letter,
                        $distribution,
                        $actor,
                        $context,
                        $note,
                        $targetDepartmentName !== '' ? $targetDepartmentName : null,
                        $resolvedTargetDepartmentId,
                        $resolvedTargetUserId
                    ));
                } catch (\Throwable $throwable) {
                    Log::warning('Correspondence notification failed', [
                        'letter_id' => (int) $letter->id,
                        'user_id' => (int) $recipient->id,
                        'error' => $throwable->getMessage(),
                    ]);
                }
            }
        });
    }

    protected function roleCodesToSystemRoleIds(array $roleCodes): array
    {
        $codes = collect($roleCodes)
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($codes)) {
            return [];
        }

        if (!Schema::hasTable('system_roles')) {
            return [];
        }

        return SystemRole::query()
            ->whereIn('code', $codes)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function applyOrgRoleCodeFilter($query, array $roleCodes): void
    {
        $codes = collect($roleCodes)
            ->map(fn ($code) => trim((string) $code))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($codes)) {
            return;
        }

        $systemRoleIds = $this->roleCodesToSystemRoleIds($codes);

        $query->where(function ($roleQuery) use ($codes, $systemRoleIds) {
            $roleQuery->whereIn('org_role', $codes);
            if (!empty($systemRoleIds)) {
                $roleQuery->orWhereIn('system_role_id', $systemRoleIds);
            }
        });
    }

    protected function orgAccessService(): OrgHierarchyAccessService
    {
        return app(OrgHierarchyAccessService::class);
    }

    protected function orgUnitRuleService(): OrgUnitRuleService
    {
        return app(OrgUnitRuleService::class);
    }

    protected function rolePermissionService(): OrgRolePermissionService
    {
        return app(OrgRolePermissionService::class);
    }
}
