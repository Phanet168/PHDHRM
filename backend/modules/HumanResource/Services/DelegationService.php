<?php

namespace Modules\HumanResource\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\HumanResource\Entities\Delegation;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\WorkflowDefinitionStep;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Modules\HumanResource\Support\WorkflowActorResolverService;

/**
 * Phase 3D.1: the ONE canonical Delegation service. Every rule from the
 * Phase 3D.0 audit / Phase 3D.1 brief is enforced here, in one place:
 *  - a delegator can only delegate authority they NATIVELY hold (checked via
 *    AccessControlService::approvalAuthorityForUser(), which is NOT
 *    delegation-aware -- it only reports native WorkflowActorResolverService
 *    standing. This is what makes re-delegation of borrowed authority and
 *    A->B->A circular reliance structurally impossible without any extra
 *    chain-tracking logic: a delegatee's authority is never "native", so
 *    they can never pass this same check to delegate it onward);
 *  - delegated scope can never exceed the delegator's own effective scope;
 *  - self-delegation is rejected;
 *  - authority is re-verified LIVE at check time (isActiveDelegateFor()),
 *    never trusted from creation time;
 *  - delegation never mutates roles/permissions/UserAssignments.
 *
 * AccessControlService::canApprove() calls INTO this service (section 16),
 * and this service calls AccessControlService::approvalAuthorityForUser()
 * back (above) -- to avoid a constructor circular dependency between the
 * two, AccessControlService is resolved lazily via app() here rather than
 * constructor-injected, the same lazy-resolution pattern already used
 * elsewhere in this codebase (e.g. ManualAttendanceController's
 * app(OrgScopeService::class) calls) instead of restructuring either
 * service's existing, already-tested internals.
 */
class DelegationService
{
    public function __construct(
        private readonly OrgHierarchyAccessService $orgHierarchyAccessService,
        private readonly WorkflowActorResolverService $workflowActorResolverService,
    ) {
    }

    /**
     * Module keys the given user could delegate right now -- i.e. modules
     * where they NATIVELY (non-delegation) qualify as a workflow actor on at
     * least one step. Source data for the Access Control Center's "Authority"
     * checklist (section 23: never show authorities the delegator does not
     * possess). Display labels are added by the presentation layer
     * (AccessControlCenterController already owns that mapping) -- this
     * stays data-only, matching AccessControlService::approvalAuthorityForUser()'s
     * own raw shape (which has no module_label key either).
     *
     * @return array<int, array{module_key: string}>
     */
    public function delegatableAuthoritiesFor(User $delegator): array
    {
        $rows = collect(app(\App\Services\AccessControlService::class)->approvalAuthorityForUser($delegator))
            ->where('can_act', true);

        return $rows->unique('module_key')
            ->map(fn (array $row) => ['module_key' => $row['module_key']])
            ->values()
            ->all();
    }

    /**
     * Department ids the delegator's OWN effective scope allows them to
     * delegate within. Null means unrestricted (system admin or an
     * ORGANIZATION-scope holder) -- any requested scope is in-bounds.
     *
     * @return int[]|null
     */
    public function delegatorBoundaryDepartmentIds(User $delegator): ?array
    {
        return $this->orgHierarchyAccessService->managedBranchIds($delegator);
    }

    /**
     * @param array{
     *   delegator_user_id:int, delegatee_user_id:int, authority_module_key:string,
     *   scope_type:string, scope_department_ids:array<int,int>,
     *   starts_at:string, ends_at:string, reason?:?string
     * } $payload
     */
    public function create(array $payload, ?int $actorId = null): Delegation
    {
        $normalized = $this->validateAndNormalize($payload);

        $delegation = Delegation::create(array_merge($normalized, [
            'authority_type' => Delegation::AUTHORITY_TYPE_WORKFLOW_APPROVAL,
            'status' => Delegation::STATUS_ACTIVE,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]));

        return $delegation->fresh(['delegator', 'delegatee']);
    }

    public function revoke(Delegation $delegation, ?int $actorId = null, ?string $reason = null): Delegation
    {
        if ($delegation->isRevoked()) {
            return $delegation;
        }

        $delegation->update([
            'status' => Delegation::STATUS_REVOKED,
            'revoked_by' => $actorId,
            'revoked_at' => now(),
            'updated_by' => $actorId,
            'reason' => $reason !== null ? trim($delegation->reason . "\n[revoked] " . $reason) : $delegation->reason,
        ]);

        return $delegation->fresh();
    }

    /**
     * @return Collection<int, Delegation>
     */
    public function list(array $filters = []): Collection
    {
        $query = Delegation::query()->with(['delegator', 'delegatee'])->orderByDesc('id');

        if (!empty($filters['delegator_user_id'])) {
            $query->where('delegator_user_id', (int) $filters['delegator_user_id']);
        }
        if (!empty($filters['delegatee_user_id'])) {
            $query->where('delegatee_user_id', (int) $filters['delegatee_user_id']);
        }
        if (!empty($filters['authority_module_key'])) {
            $query->where('authority_module_key', (string) $filters['authority_module_key']);
        }
        if (!empty($filters['department_id'])) {
            $deptId = (int) $filters['department_id'];
            $query->whereJsonContains('scope_department_ids', $deptId)
                ->orWhere('scope_type', UserAssignment::SCOPE_ALL);
        }

        $state = (string) ($filters['state'] ?? 'active');
        $now = now();
        switch ($state) {
            case 'active':
                $query->effectiveNow($now);
                break;
            case 'upcoming':
                $query->notRevoked()->where('starts_at', '>', $now);
                break;
            case 'expired':
                $query->notRevoked()->where('ends_at', '<', $now);
                break;
            case 'revoked':
                $query->where('status', Delegation::STATUS_REVOKED);
                break;
            case 'all':
            default:
                break;
        }

        return $query->limit(200)->get();
    }

    /**
     * The section-17 algorithm, exactly: find candidate active delegations
     * for this delegatee+module, then for EACH one verify scope AND
     * re-verify (live) that the delegator still qualifies as an original
     * actor for this exact step/context. Never just checks "does a
     * delegation row exist".
     */
    public function isActiveDelegateFor(
        User $delegatee,
        WorkflowDefinitionStep $step,
        string $moduleKey,
        int $sourceDepartmentId
    ): bool {
        $candidates = Delegation::query()
            ->with('delegator')
            ->where('delegatee_user_id', $delegatee->id)
            ->where('authority_type', Delegation::AUTHORITY_TYPE_WORKFLOW_APPROVAL)
            ->where('authority_module_key', $moduleKey)
            ->effectiveNow()
            ->get();

        foreach ($candidates as $delegation) {
            if ($sourceDepartmentId > 0 && !$this->scopeIncludesDepartment($delegation, $sourceDepartmentId)) {
                continue;
            }

            $delegator = $delegation->delegator;
            if (!$delegator) {
                continue;
            }

            // Live re-verification -- never trust delegation-creation-time
            // authority. If the delegator has since lost the underlying
            // authority, this delegation silently stops granting it.
            if ($this->workflowActorResolverService->canUserActOnStep($delegator, $step, $sourceDepartmentId, $moduleKey)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Explanation payload for Effective Access (section 30/31): only
     * returned for a delegation that is CURRENTLY effective.
     */
    public function explainSource(Delegation $delegation): array
    {
        return [
            'type' => 'temporary_delegation',
            'from_user_id' => $delegation->delegator_user_id,
            'from_user_name' => $delegation->delegator?->full_name,
            'authority_module_key' => $delegation->authority_module_key,
            'scope_type' => $delegation->scope_type,
            'scope_department_ids' => $delegation->scopeDepartmentIds(),
            'starts_at' => optional($delegation->starts_at)->toIso8601String(),
            'ends_at' => optional($delegation->ends_at)->toIso8601String(),
        ];
    }

    private function scopeIncludesDepartment(Delegation $delegation, int $departmentId): bool
    {
        $branchIds = $this->expandDelegationScope($delegation->scope_type, $delegation->scopeDepartmentIds());

        return $branchIds === null || in_array($departmentId, $branchIds, true);
    }

    /**
     * @param int[] $rootDepartmentIds
     * @return int[]|null null means unrestricted ('all')
     */
    private function expandDelegationScope(string $scopeType, array $rootDepartmentIds): ?array
    {
        if ($scopeType === UserAssignment::SCOPE_ALL) {
            return null;
        }

        if ($scopeType === UserAssignment::SCOPE_SELF_ONLY) {
            // May hold >1 root (the "selected units" case) -- each expands
            // to itself only, so the result is just the id list.
            return $rootDepartmentIds;
        }

        // self_unit_only / self_and_children always store exactly one root.
        $rootId = $rootDepartmentIds[0] ?? 0;
        if ($rootId <= 0) {
            return [];
        }

        return $this->orgHierarchyAccessService->expandScopeBranchIds($scopeType, $rootId) ?? null;
    }

    private function validateAndNormalize(array $payload): array
    {
        $delegatorId = (int) ($payload['delegator_user_id'] ?? 0);
        $delegateeId = (int) ($payload['delegatee_user_id'] ?? 0);

        if ($delegatorId <= 0 || $delegateeId <= 0) {
            throw ValidationException::withMessages(['delegator_user_id' => 'Delegator and delegatee are required.']);
        }
        if ($delegatorId === $delegateeId) {
            throw ValidationException::withMessages(['delegatee_user_id' => 'A user cannot delegate to themselves.']);
        }

        $delegator = User::query()->withoutGlobalScope('sortByLatest')->find($delegatorId);
        $delegatee = User::query()->withoutGlobalScope('sortByLatest')->find($delegateeId);
        if (!$delegator || !$delegatee) {
            throw ValidationException::withMessages(['delegator_user_id' => 'Delegator or delegatee user not found.']);
        }

        $moduleKey = trim((string) ($payload['authority_module_key'] ?? ''));
        if ($moduleKey === '') {
            throw ValidationException::withMessages(['authority_module_key' => 'An authority to delegate is required.']);
        }

        // Section 6/24: delegator must NATIVELY possess this exact authority.
        $delegatableModuleKeys = collect($this->delegatableAuthoritiesFor($delegator))->pluck('module_key')->all();
        if (!in_array($moduleKey, $delegatableModuleKeys, true)) {
            throw ValidationException::withMessages([
                'authority_module_key' => 'The delegator does not currently hold this approval authority, so it cannot be delegated.',
            ]);
        }

        $scopeType = trim((string) ($payload['scope_type'] ?? ''));
        if (!in_array($scopeType, UserAssignment::scopeOptions(), true)) {
            throw ValidationException::withMessages(['scope_type' => 'Invalid scope type.']);
        }

        $requestedDepartmentIds = array_values(array_unique(array_filter(
            array_map('intval', (array) ($payload['scope_department_ids'] ?? [])),
            fn (int $id) => $id > 0
        )));

        if ($scopeType !== UserAssignment::SCOPE_ALL && empty($requestedDepartmentIds)) {
            throw ValidationException::withMessages(['scope_department_ids' => 'At least one organization unit is required for this scope.']);
        }

        // Section 8/25: delegated scope must never exceed the delegator's
        // own effective scope.
        $this->assertScopeWithinDelegatorBoundary($delegator, $scopeType, $requestedDepartmentIds);

        $startsAt = $this->parseDateTime($payload['starts_at'] ?? null, 'starts_at');
        $endsAt = $this->parseDateTime($payload['ends_at'] ?? null, 'ends_at');
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw ValidationException::withMessages(['ends_at' => 'End date/time must be after the start date/time.']);
        }

        return [
            'delegator_user_id' => $delegatorId,
            'delegatee_user_id' => $delegateeId,
            'authority_module_key' => $moduleKey,
            'scope_type' => $scopeType,
            'scope_department_ids' => $scopeType === UserAssignment::SCOPE_ALL ? [] : $requestedDepartmentIds,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'reason' => !empty($payload['reason']) ? trim((string) $payload['reason']) : null,
        ];
    }

    private function assertScopeWithinDelegatorBoundary(User $delegator, string $scopeType, array $requestedDepartmentIds): void
    {
        $delegatorBoundaryIds = $this->delegatorBoundaryDepartmentIds($delegator);
        if ($delegatorBoundaryIds === null) {
            return; // delegator is unrestricted (system admin / ORGANIZATION scope)
        }

        $requestedBranchIds = $this->expandDelegationScope($scopeType, $requestedDepartmentIds);
        if ($requestedBranchIds === null) {
            // Requesting unrestricted ('all') while the delegator is themselves restricted.
            throw ValidationException::withMessages([
                'scope_type' => 'Delegated scope cannot exceed the delegator\'s own organization scope.',
            ]);
        }

        $outOfBounds = array_diff($requestedBranchIds, $delegatorBoundaryIds);
        if (!empty($outOfBounds)) {
            throw ValidationException::withMessages([
                'scope_department_ids' => 'Delegated scope includes units outside the delegator\'s own organization scope.',
            ]);
        }
    }

    private function parseDateTime($value, string $field): Carbon
    {
        if (empty($value)) {
            throw ValidationException::withMessages([$field => 'This field is required.']);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([$field => 'Invalid date/time.']);
        }
    }
}
