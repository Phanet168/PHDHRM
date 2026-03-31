<?php

namespace Modules\Correspondence\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\Correspondence\Entities\CorrespondenceLetterAction;
use Modules\Correspondence\Entities\CorrespondenceLetterDistribution;
use Modules\Correspondence\Entities\CorrespondenceLetter;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Modules\HumanResource\Support\OrgUnitRuleService;

class CorrespondenceController extends Controller
{
    public function index(Request $request)
    {
        $incomingCount = (clone $this->accessibleLettersQuery(CorrespondenceLetter::TYPE_INCOMING))->count();
        $outgoingCount = (clone $this->accessibleLettersQuery(CorrespondenceLetter::TYPE_OUTGOING))->count();
        $pendingCount = (clone $this->accessibleLettersQuery())
            ->whereIn('status', [CorrespondenceLetter::STATUS_PENDING, CorrespondenceLetter::STATUS_IN_PROGRESS])
            ->count();
        $completedCount = (clone $this->accessibleLettersQuery())
            ->where('status', CorrespondenceLetter::STATUS_COMPLETED)
            ->count();

        return view('correspondence::dashboard.index', [
            'incomingCount' => $incomingCount,
            'outgoingCount' => $outgoingCount,
            'pendingCount' => $pendingCount,
            'completedCount' => $completedCount,
        ]);
    }

    public function incoming(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $letters = $this->accessibleLettersQuery(CorrespondenceLetter::TYPE_INCOMING)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('registry_no', 'like', "%{$search}%")
                        ->orWhere('letter_no', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('from_org', 'like', "%{$search}%");
                });
            })
            ->with(['originDepartment', 'assignedDepartment'])
            ->latest('received_date')
            ->latest('letter_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('correspondence::incoming.index', [
            'letters' => $letters,
            'search' => $search,
        ]);
    }

    public function outgoing(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $letters = $this->accessibleLettersQuery(CorrespondenceLetter::TYPE_OUTGOING)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('registry_no', 'like', "%{$search}%")
                        ->orWhere('letter_no', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('to_org', 'like', "%{$search}%");
                });
            })
            ->with(['originDepartment', 'assignedDepartment'])
            ->latest('letter_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('correspondence::outgoing.index', [
            'letters' => $letters,
            'search' => $search,
        ]);
    }

    public function create(string $type)
    {
        abort_unless(in_array($type, [CorrespondenceLetter::TYPE_INCOMING, CorrespondenceLetter::TYPE_OUTGOING], true), 404);

        return view('correspondence::form', [
            'type' => $type,
            'orgUnitOptions' => $this->orgUnitRuleService()->hierarchyOptions(),
        ]);
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
            'due_date' => ['nullable', 'date'],
            'summary' => ['nullable', 'string'],
            'origin_department_id' => ['nullable', 'integer', 'exists:departments,id'],
        ]);

        $user = Auth::user();
        $originDepartmentId = (int) ($validated['origin_department_id'] ?? 0);
        if ($originDepartmentId <= 0) {
            $originDepartmentId = $this->resolveDefaultOriginDepartmentId($user);
        }

        $letter = CorrespondenceLetter::create([
            'letter_type' => (string) $validated['letter_type'],
            'registry_no' => $validated['registry_no'] ?? null,
            'letter_no' => $validated['letter_no'] ?? null,
            'subject' => (string) $validated['subject'],
            'from_org' => $validated['from_org'] ?? null,
            'to_org' => $validated['to_org'] ?? null,
            'priority' => (string) ($validated['priority'] ?? 'normal'),
            'status' => CorrespondenceLetter::STATUS_PENDING,
            'letter_date' => $validated['letter_date'] ?? null,
            'received_date' => $validated['received_date'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'summary' => $validated['summary'] ?? null,
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

        return redirect()
            ->route('correspondence.show', $letter->id)
            ->with('success', localize('data_save', 'Saved successfully.'));
    }

    public function show(CorrespondenceLetter $letter)
    {
        $this->assertCanView($letter);

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

        $relatedUserIds = collect([$letter->current_handler_user_id, $letter->created_by, $letter->updated_by])
            ->merge([$letter->parentLetter?->current_handler_user_id, $letter->parentLetter?->created_by])
            ->merge($letter->actions->pluck('acted_by'))
            ->merge($letter->actions->pluck('target_user_id'))
            ->merge($letter->distributions->pluck('target_user_id'))
            ->merge($letter->childLetters->pluck('current_handler_user_id'))
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

        return view('correspondence::show', [
            'letter' => $letter,
            'orgUnitOptions' => $this->orgUnitRuleService()->hierarchyOptions(),
            'canDelegate' => $this->canPerformRoleAction($letter, [UserOrgRole::ROLE_HEAD, UserOrgRole::ROLE_DEPUTY_HEAD]),
            'canOfficeComment' => $this->canPerformRoleAction($letter, [UserOrgRole::ROLE_MANAGER]),
            'canDeputyReview' => $this->canPerformRoleAction($letter, [UserOrgRole::ROLE_DEPUTY_HEAD]),
            'canDirectorDecision' => $this->canPerformRoleAction($letter, [UserOrgRole::ROLE_HEAD]),
            'canDistribute' => $this->canPerformRoleAction($letter, [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD]),
            'canClose' => $this->canPerformRoleAction($letter, [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD]),
            'stepLabels' => CorrespondenceLetter::stepLabels(),
            'sourceDistribution' => $sourceDistribution,
            'isChildLetter' => (int) ($letter->parent_letter_id ?? 0) > 0,
            'canSendParentFeedback' => (int) ($letter->source_distribution_id ?? 0) > 0 && (
                ((int) $letter->current_handler_user_id === (int) Auth::id())
                || $this->canPerformRoleAction($letter, [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD])
            ),
            'distributionStatuses' => [
                CorrespondenceLetterDistribution::STATUS_PENDING_ACK => localize('pending_ack', 'Pending acknowledge'),
                CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED => localize('acknowledged', 'Acknowledged'),
                CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT => localize('feedback_sent', 'Feedback sent'),
                CorrespondenceLetterDistribution::STATUS_CLOSED => localize('closed', 'Closed'),
            ],
            'userMap' => $userMap,
            'departmentMap' => $departmentMap,
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
            'decision' => ['nullable', Rule::in([CorrespondenceLetter::DECISION_APPROVED, CorrespondenceLetter::DECISION_REJECTED])],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $action = (string) $validated['action'];
        $note = trim((string) ($validated['note'] ?? ''));
        $user = Auth::user();

        switch ($action) {
            case 'delegate':
                if ($letter->letter_type !== CorrespondenceLetter::TYPE_INCOMING) {
                    return back()->with('error', localize('invalid_action', 'Invalid action for this letter type.'));
                }
                if (!in_array((string) $letter->current_step, [
                    CorrespondenceLetter::STEP_INCOMING_RECEIVED,
                    CorrespondenceLetter::STEP_INCOMING_DELEGATED,
                ], true)) {
                    return back()->with('error', localize('step_not_allowed', 'Current step does not allow delegation.'));
                }
                if (!$this->canPerformRoleAction($letter, [UserOrgRole::ROLE_HEAD, UserOrgRole::ROLE_DEPUTY_HEAD])) {
                    return back()->with('error', localize('permission_denied', 'Permission denied.'));
                }

                $letter->update([
                    'assigned_department_id' => !empty($validated['assigned_department_id']) ? (int) $validated['assigned_department_id'] : $letter->assigned_department_id,
                    'current_handler_user_id' => !empty($validated['target_user_id']) ? (int) $validated['target_user_id'] : $letter->current_handler_user_id,
                    'current_step' => CorrespondenceLetter::STEP_INCOMING_DELEGATED,
                    'status' => CorrespondenceLetter::STATUS_IN_PROGRESS,
                    'updated_by' => (int) $user->id,
                ]);

                $this->logAction(
                    $letter,
                    'delegate',
                    CorrespondenceLetter::STEP_INCOMING_DELEGATED,
                    !empty($validated['target_user_id']) ? (int) $validated['target_user_id'] : null,
                    !empty($validated['assigned_department_id']) ? (int) $validated['assigned_department_id'] : null,
                    $note
                );
                break;

            case 'office_comment':
                if ((string) $letter->current_step !== CorrespondenceLetter::STEP_INCOMING_DELEGATED) {
                    return back()->with('error', localize('step_not_allowed', 'Current step does not allow office comment.'));
                }
                if (!$this->canPerformRoleAction($letter, [UserOrgRole::ROLE_MANAGER])) {
                    return back()->with('error', localize('permission_denied', 'Permission denied.'));
                }
                if ($note === '') {
                    return back()->withErrors(['note' => localize('comment_required', 'Comment is required.')]);
                }

                $letter->update([
                    'current_step' => CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT,
                    'status' => CorrespondenceLetter::STATUS_IN_PROGRESS,
                    'updated_by' => (int) $user->id,
                ]);

                $this->logAction($letter, 'office_comment', CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT, null, null, $note);
                break;

            case 'deputy_review':
                if ((string) $letter->current_step !== CorrespondenceLetter::STEP_INCOMING_OFFICE_COMMENT) {
                    return back()->with('error', localize('step_not_allowed', 'Current step does not allow deputy review.'));
                }
                if (!$this->canPerformRoleAction($letter, [UserOrgRole::ROLE_DEPUTY_HEAD])) {
                    return back()->with('error', localize('permission_denied', 'Permission denied.'));
                }
                if ($note === '') {
                    return back()->withErrors(['note' => localize('comment_required', 'Comment is required.')]);
                }

                $letter->update([
                    'current_step' => CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW,
                    'status' => CorrespondenceLetter::STATUS_IN_PROGRESS,
                    'updated_by' => (int) $user->id,
                ]);

                $this->logAction($letter, 'deputy_review', CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW, null, null, $note);
                break;

            case 'director_decision':
                if ((string) $letter->current_step !== CorrespondenceLetter::STEP_INCOMING_DEPUTY_REVIEW) {
                    return back()->with('error', localize('step_not_allowed', 'Current step does not allow director decision.'));
                }
                if (!$this->canPerformRoleAction($letter, [UserOrgRole::ROLE_HEAD])) {
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
                $nextStep = $isApproved ? CorrespondenceLetter::STEP_INCOMING_DIRECTOR_DECISION : CorrespondenceLetter::STEP_CLOSED;

                $letter->update([
                    'final_decision' => $decision,
                    'decision_note' => $note !== '' ? $note : null,
                    'decision_at' => now(),
                    'current_step' => $nextStep,
                    'status' => $isApproved ? CorrespondenceLetter::STATUS_IN_PROGRESS : CorrespondenceLetter::STATUS_ARCHIVED,
                    'completed_at' => $isApproved ? null : now(),
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
                break;

            case 'close':
                if (!$this->canPerformRoleAction($letter, [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD])) {
                    return back()->with('error', localize('permission_denied', 'Permission denied.'));
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
            if ($action === 'close') {
                $feedbackNote = $note !== '' ? $note : localize('child_unit_completed', 'Child unit completed processing.');
                $this->syncParentDistributionFromChild($letter, CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT, $feedbackNote);
            } else {
                $ackNote = $note !== '' ? $note : localize('child_unit_in_progress', 'Child unit is processing this letter.');
                $this->syncParentDistributionFromChild($letter, CorrespondenceLetterDistribution::STATUS_ACKNOWLEDGED, $ackNote);
            }
        }

        return redirect()
            ->route('correspondence.show', $letter->id)
            ->with('success', localize('data_update', 'Updated successfully.'));
    }

    public function distribute(Request $request, CorrespondenceLetter $letter)
    {
        $this->assertCanView($letter);

        if (!$this->canPerformRoleAction($letter, [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD])) {
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
            'target_department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'target_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $targetDepartmentIds = collect($validated['target_department_ids'] ?? [])
            ->merge(!empty($validated['target_department_id']) ? [(int) $validated['target_department_id']] : [])
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $targetUserId = !empty($validated['target_user_id']) ? (int) $validated['target_user_id'] : null;
        $note = trim((string) ($validated['note'] ?? ''));

        if ($targetDepartmentIds->isEmpty() && !$targetUserId) {
            return back()->withErrors([
                'target_user_id' => localize('distribution_target_required', 'Please select at least one distribution target.'),
            ]);
        }

        $createdCount = 0;
        $childCount = 0;
        $skippedCount = 0;
        $allowTargetUserOnDepartmentRows = $targetDepartmentIds->count() <= 1;

        foreach ($targetDepartmentIds as $targetDepartmentId) {
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
                'target_user_id' => $allowTargetUserOnDepartmentRows ? $targetUserId : null,
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
            $createdCount++;
        }

        if ($targetDepartmentIds->isEmpty() && $targetUserId) {
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
            || $this->canPerformRoleAction($letter, [UserOrgRole::ROLE_MANAGER, UserOrgRole::ROLE_DEPUTY_HEAD, UserOrgRole::ROLE_HEAD]);

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

    public function searchUsers(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('q', ''));
        $limit = max(10, min(50, (int) $request->query('limit', 20)));

        $rows = User::query()
            ->withoutGlobalScope('sortByLatest')
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

        $managedBranchIds = $this->orgAccessService()->managedBranchIds($user);
        $userId = (int) $user->id;

        $query->where(function ($q) use ($managedBranchIds, $userId) {
            $q->where('created_by', $userId)
                ->orWhere('current_handler_user_id', $userId)
                ->orWhereHas('distributions', function ($distributionQuery) use ($userId) {
                    $distributionQuery->where('target_user_id', $userId);
                });

            if (is_array($managedBranchIds) && !empty($managedBranchIds)) {
                $q->orWhereIn('origin_department_id', $managedBranchIds)
                    ->orWhereIn('assigned_department_id', $managedBranchIds)
                    ->orWhereHas('distributions', function ($distributionQuery) use ($managedBranchIds) {
                        $distributionQuery->whereIn('target_department_id', $managedBranchIds);
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
            || $letter->distributions()->where('target_user_id', $userId)->exists()
        ) {
            return;
        }

        $managedBranchIds = $this->orgAccessService()->managedBranchIds($user);
        if (is_array($managedBranchIds) && !empty($managedBranchIds)) {
            if (
                in_array((int) ($letter->origin_department_id ?? 0), $managedBranchIds, true)
                || in_array((int) ($letter->assigned_department_id ?? 0), $managedBranchIds, true)
                || $letter->distributions()->whereIn('target_department_id', $managedBranchIds)->exists()
            ) {
                return;
            }
        }

        abort(403);
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

        return $this->canPerformRoleAction($distribution->letter, [
            UserOrgRole::ROLE_MANAGER,
            UserOrgRole::ROLE_DEPUTY_HEAD,
            UserOrgRole::ROLE_HEAD,
        ]);
    }

    protected function resolveDefaultOriginDepartmentId(?User $user): int
    {
        if (!$user) {
            return 0;
        }

        $role = $this->orgAccessService()->effectiveOrgRoles($user)->first();
        return (int) ($role->department_id ?? 0);
    }

    protected function refreshCompletionState(CorrespondenceLetter $letter): void
    {
        $total = $letter->distributions()->count();
        if ($total <= 0) {
            return;
        }

        $feedbackDone = $letter->distributions()
            ->whereIn('status', [
                CorrespondenceLetterDistribution::STATUS_FEEDBACK_SENT,
                CorrespondenceLetterDistribution::STATUS_CLOSED,
            ])
            ->count();

        if ($feedbackDone >= $total) {
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

    protected function orgAccessService(): OrgHierarchyAccessService
    {
        return app(OrgHierarchyAccessService::class);
    }

    protected function orgUnitRuleService(): OrgUnitRuleService
    {
        return app(OrgUnitRuleService::class);
    }
}
