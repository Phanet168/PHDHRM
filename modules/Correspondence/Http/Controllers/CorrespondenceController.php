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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Correspondence\Entities\CorrespondenceLetterAction;
use Modules\Correspondence\Entities\CorrespondenceLetterDistribution;
use Modules\Correspondence\Entities\CorrespondenceLetter;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\OrgUnitType;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Modules\HumanResource\Support\OrgRolePermissionService;
use Modules\HumanResource\Support\OrgUnitRuleService;
use Modules\Correspondence\Traits\CorrespondenceScope;

class CorrespondenceController extends Controller
{
    use CorrespondenceScope;

    protected const DASHBOARD_COUNT_CACHE_SECONDS = 45;

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
            $pendingQuery = (clone $this->accessibleLettersQuery())
                ->whereIn('status', [CorrespondenceLetter::STATUS_PENDING, CorrespondenceLetter::STATUS_IN_PROGRESS]);
            $completedQuery = (clone $this->accessibleLettersQuery())
                ->where('status', CorrespondenceLetter::STATUS_COMPLETED);

            $this->applyDateRange($incomingQuery, 'received_date', $filters['startDate'], $filters['endDate']);
            $this->applyDateRange($outgoingQuery, 'sent_date', $filters['startDate'], $filters['endDate']);
            $this->applyDateRange($pendingQuery, 'created_at', $filters['startDate'], $filters['endDate']);
            $this->applyDateRange($completedQuery, 'created_at', $filters['startDate'], $filters['endDate']);

            $incomingCount = $incomingQuery->count();
            $outgoingCount = $outgoingQuery->count();
            $pendingCount = $pendingQuery->count();
            $completedCount = $completedQuery->count();

            return [
                'incomingCount' => $incomingCount,
                'outgoingCount' => $outgoingCount,
                'pendingCount' => $pendingCount,
                'completedCount' => $completedCount,
            ];
        });

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
            ])
            ->latest('received_date')
            ->latest('letter_date')
            ->latest('id')
            ->simplePaginate($perPage)
            ->appends($request->query());

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
            ->simplePaginate($perPage)
            ->appends($request->query());

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
                $attachmentPaths[] = $file->store('correspondence/attachments', 'public');
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
            ->merge(array_values($workflowAssignments))
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
            ->get(['user_id', 'org_role'])
            ->groupBy('user_id')
            ->map(function ($rows) {
                $roleCodes = $rows
                    ->pluck('org_role')
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

        return view('correspondence::show', [
            'letter' => $letter,
            'level' => $this->corrLevel(),
            'orgUnitOptions' => $originUnitOptions,
            'workflowAssignments' => $workflowAssignments,
            'isRecipientActor' => $this->isRecipientActor($letter),
            'canDelegate' => $this->canDelegateIncoming($letter),
            'canOfficeComment' => $this->isRecipientActor($letter),
            'canDeputyReview' => $this->isRecipientActor($letter),
            'canDirectorDecision' => $this->isRecipientActor($letter),
            'canDistribute' => $this->canPerformModuleAction(
                $letter,
                'distribute',
                [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD]
            ),
            'canClose' => $this->canPerformModuleAction(
                $letter,
                'close',
                [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD]
            ),
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

        $path = (string) $attachments[$index];
        $disk = Storage::disk('public');

        if (!$disk->exists($path)) {
            abort(404);
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $previewableExts = ['pdf', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

        return view('correspondence::attachment-preview', [
            'fileUrl' => asset('storage/' . ltrim($path, '/')),
            'fileName' => basename($path),
            'fileExt' => $ext,
            'isPreviewable' => in_array($ext, $previewableExts, true),
        ]);
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
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'office_comment_user_id' => ['nullable', 'integer', 'exists:users,id'],
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

                if ($officeCommentUserId <= 0 || $deputyReviewUserId <= 0 || $directorUserId <= 0) {
                    return back()->withErrors([
                        'office_comment_user_id' => localize('incoming_chain_required', 'Please select users for steps 3, 4, and 5.'),
                    ])->withInput();
                }

                $letter->update([
                    'assigned_department_id' => !empty($validated['assigned_department_id']) ? (int) $validated['assigned_department_id'] : $letter->assigned_department_id,
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
                    !empty($validated['assigned_department_id']) ? (int) $validated['assigned_department_id'] : null,
                    $note,
                    [
                        'office_comment_user_id' => $officeCommentUserId,
                        'deputy_review_user_id' => $deputyReviewUserId,
                        'director_user_id' => $directorUserId,
                    ]
                );

                $assignedDepartmentId = !empty($validated['assigned_department_id'])
                    ? (int) $validated['assigned_department_id']
                    : (int) ($letter->assigned_department_id ?? 0);

                $this->notifyAssignmentRecipients(
                    $letter,
                    null,
                    $note,
                    'delegated',
                    $officeCommentUserId,
                    $assignedDepartmentId > 0 ? $assignedDepartmentId : null
                );
                break;

            case 'office_comment':
                if ((string) $letter->current_step !== CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT) {
                    return back()->with('error', localize('step_not_allowed', 'Current step does not allow office comment.'));
                }
                if (!$this->isRecipientActor($letter)) {
                    return back()->with('error', localize('permission_denied', 'Permission denied.'));
                }
                if ($note === '') {
                    return back()->withErrors(['note' => localize('comment_required', 'Comment is required.')]);
                }

                $workflowAssignments = $this->repairIncomingWorkflowAssignments($letter, $this->incomingWorkflowAssignments($letter));
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

        return redirect()
            ->route('correspondence.show', $letter->id)
            ->with('success', $successMessage);
    }

    public function distribute(Request $request, CorrespondenceLetter $letter)
    {
        $this->assertCanView($letter);

        if ($letter->letter_type === CorrespondenceLetter::TYPE_INCOMING && !$this->isRecipientActor($letter)) {
            return back()->with('error', localize('only_recipient_can_assign', 'Only the current recipient can assign this letter.'));
        }

        if (!$this->canPerformModuleAction($letter, 'distribute', [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD])) {
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

    public function searchUsers(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));
        $limit = max(10, min(50, (int) $request->query('limit', 20)));
        $authUser = Auth::user();

        $rows = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->when(
                !$this->orgAccessService()->isSystemAdmin($authUser),
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
            ->when($keyword !== '', function ($query) use ($keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('full_name', 'like', '%' . $keyword . '%')
                        ->orWhere('email', 'like', '%' . $keyword . '%');
                });
            })
            ->orderBy('full_name')
            ->limit($limit)
            ->get(['id', 'full_name', 'email']);

        return response()->json([
            'results' => $rows->map(function ($user) {
                $name = trim((string) ($user->full_name ?? ''));
                $email = trim((string) ($user->email ?? ''));
                return [
                    'id' => (int) $user->id,
                    'text' => $email !== '' ? "{$name} ({$email})" : $name,
                ];
            })->values(),
        ]);
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

        if ($this->orgAccessService()->isSystemAdmin($user)) {
            return $query;
        }

        $deptIds = $this->corrAccessibleDepartmentIds();

        // PHD level → null → sees everything
        if ($deptIds === null) {
            return $query;
        }

        // No org role → sees only own letters / personally assigned
        $userId = (int) $user->id;

        $query->where(function ($q) use ($deptIds, $userId) {
            $q->where('created_by', $userId)
                ->orWhere('current_handler_user_id', $userId)
                ->orWhereHas('distributions', function ($distributionQuery) use ($userId) {
                    $distributionQuery->where('target_user_id', $userId);
                });

            if (!empty($deptIds)) {
                $q->orWhereIn('origin_department_id', $deptIds)
                    ->orWhereIn('assigned_department_id', $deptIds)
                    ->orWhereHas('distributions', function ($distributionQuery) use ($deptIds) {
                        $distributionQuery->whereIn('target_department_id', $deptIds);
                    });
            }
        });

        return $query;
    }

    protected function assertCanView(CorrespondenceLetter $letter): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        if ($this->orgAccessService()->isSystemAdmin($user)) {
            return;
        }

        $userId = (int) $user->id;
        if (
            (int) $letter->created_by === $userId
            || (int) $letter->current_handler_user_id === $userId
            || $this->isUserInIncomingWorkflowChain($letter, $userId)
            || $letter->distributions()->where('target_user_id', $userId)->exists()
        ) {
            return;
        }

        $deptIds = $this->corrAccessibleDepartmentIds();

        // PHD level → null → can view everything
        if ($deptIds === null) {
            return;
        }

        if (!empty($deptIds)) {
            if (
                in_array((int) ($letter->origin_department_id ?? 0), $deptIds, true)
                || in_array((int) ($letter->assigned_department_id ?? 0), $deptIds, true)
                || $letter->distributions()->whereIn('target_department_id', $deptIds)->exists()
            ) {
                return;
            }
        }

        abort(403);
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
                return in_array((string) $role->org_role, $requiredRoles, true);
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
            if ($scopeType === UserOrgRole::SCOPE_SELF) {
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

        return [
            'corrLevel' => $level,
            'corrLevelLabel' => $this->corrLevelLabel(),
            'corrDepartmentName' => $dept ? (string) $dept->department_name : '',
            'canCreateIncoming' => $this->canCreateType(CorrespondenceLetter::TYPE_INCOMING),
            'canCreateOutgoing' => $this->canCreateType(CorrespondenceLetter::TYPE_OUTGOING),
        ];
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
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        if ($this->orgAccessService()->isSystemAdmin($user)) {
            return true;
        }

        $fallbackRoles = match ($type) {
            CorrespondenceLetter::TYPE_INCOMING => [
                UserOrgRole::ROLE_MANAGER,
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
            CorrespondenceLetter::TYPE_OUTGOING => [
                UserOrgRole::ROLE_MANAGER,
                UserOrgRole::ROLE_DEPUTY_HEAD,
                UserOrgRole::ROLE_HEAD,
            ],
            default => [],
        };

        if (empty($fallbackRoles)) {
            return false;
        }

        $actionKey = $type === CorrespondenceLetter::TYPE_INCOMING
            ? 'create_incoming'
            : 'create_outgoing';

        return $this->rolePermissionService()->canUserPerform(
            $user,
            'correspondence',
            $actionKey,
            null,
            $fallbackRoles
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

        return [
            'office_comment_user_id' => (int) ($meta['office_comment_user_id'] ?? 0),
            'deputy_review_user_id' => (int) ($meta['deputy_review_user_id'] ?? 0),
            'director_user_id' => (int) ($meta['director_user_id'] ?? 0),
        ];
    }

    protected function repairIncomingWorkflowAssignments(CorrespondenceLetter $letter, array $assignments): array
    {
        $officeUserId = (int) ($assignments['office_comment_user_id'] ?? 0);
        if ($officeUserId <= 0) {
            $officeUserId = (int) ($letter->current_handler_user_id ?? 0);
        }

        $deputyUserId = (int) ($assignments['deputy_review_user_id'] ?? 0);
        $directorUserId = (int) ($assignments['director_user_id'] ?? 0);
        $departmentId = (int) ($letter->assigned_department_id ?: $letter->origin_department_id ?: 0);

        if ($departmentId > 0 && $deputyUserId <= 0) {
            $deputyUserId = (int) (UserOrgRole::withoutGlobalScopes()
                ->effective()
                ->where('department_id', $departmentId)
                ->where('org_role', UserOrgRole::ROLE_DEPUTY_HEAD)
                ->when($officeUserId > 0, fn ($q) => $q->where('user_id', '!=', $officeUserId))
                ->orderByDesc('id')
                ->value('user_id') ?? 0);
        }

        if ($departmentId > 0 && $directorUserId <= 0) {
            $directorUserId = (int) (UserOrgRole::withoutGlobalScopes()
                ->effective()
                ->where('department_id', $departmentId)
                ->where('org_role', UserOrgRole::ROLE_HEAD)
                ->when($officeUserId > 0, fn ($q) => $q->where('user_id', '!=', $officeUserId))
                ->when($deputyUserId > 0, fn ($q) => $q->where('user_id', '!=', $deputyUserId))
                ->orderByDesc('id')
                ->value('user_id') ?? 0);
        }

        $resolved = [
            'office_comment_user_id' => $officeUserId,
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
                ->whereIn('org_role', $roleFilters)
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
