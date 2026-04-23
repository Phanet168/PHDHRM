<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\UserOrgRole;
use Modules\HumanResource\Services\GovernanceAssignmentService;

class BackfillUserAssignments extends Command
{
    protected $signature = 'hr:backfill-user-assignments {--dry-run : Show what would be synced without writing} {--limit=0 : Limit number of rows} {--sync-primary : Auto-mark one canonical active primary assignment for each user missing one}';
    protected $description = 'Backfill canonical user_assignments from legacy user_org_roles.';

    public function handle(GovernanceAssignmentService $assignmentService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = max(0, (int) $this->option('limit'));
        $syncPrimary = (bool) $this->option('sync-primary');

        $processed = 0;
        $createdOrUpdated = 0;
        $primarySynced = 0;
        $touchedUserIds = [];

        $query = UserOrgRole::query()
            ->withoutGlobalScope('sortByLatest')
            ->whereNull('deleted_at')
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get();

        foreach ($rows as $legacyRole) {
            $processed++;

            $payload = [
                'user_id' => (int) $legacyRole->user_id,
                'department_id' => (int) $legacyRole->department_id,
                'org_role' => (string) ($legacyRole->org_role ?? ''),
                'system_role_id' => !empty($legacyRole->system_role_id) ? (int) $legacyRole->system_role_id : null,
                'scope_type' => (string) ($legacyRole->scope_type ?? 'self_and_children'),
                'effective_from' => $legacyRole->effective_from ? $legacyRole->effective_from->toDateString() : null,
                'effective_to' => $legacyRole->effective_to ? $legacyRole->effective_to->toDateString() : null,
                'is_active' => (bool) $legacyRole->is_active,
                'note' => $legacyRole->note,
                'is_primary' => false,
            ];

            if ($dryRun) {
                $this->line(sprintf(
                    '[DRY-RUN] legacy_id=%d user_id=%d department_id=%d org_role=%s',
                    (int) $legacyRole->id,
                    (int) $legacyRole->user_id,
                    (int) $legacyRole->department_id,
                    (string) ($legacyRole->org_role ?? '-')
                ));
                continue;
            }

            $assignment = $assignmentService->upsertFromLegacyPayload($payload, $legacyRole, null);
            $createdOrUpdated++;

            $userId = (int) ($assignment->user_id ?? 0);
            if ($userId > 0) {
                $touchedUserIds[$userId] = true;
            }
        }

        if (!$dryRun && $syncPrimary) {
            $primarySynced = $this->syncPrimaryAssignments($assignmentService, array_keys($touchedUserIds));
        }

        $this->info(sprintf(
            'Backfill completed. processed=%d, synced=%d, primary_synced=%d, dry_run=%s',
            $processed,
            $createdOrUpdated,
            $primarySynced,
            $dryRun ? 'yes' : 'no'
        ));

        return self::SUCCESS;
    }

    /**
     * Auto-pick one active effective assignment as primary for users who currently miss one.
     * Priority: employee primary unit posting -> employee sub_department_id -> employee department_id -> latest assignment.
     */
    protected function syncPrimaryAssignments(GovernanceAssignmentService $assignmentService, array $userIds): int
    {
        $synced = 0;

        foreach ($userIds as $rawUserId) {
            $userId = (int) $rawUserId;
            if ($userId <= 0) {
                continue;
            }

            $alreadyHasPrimary = UserAssignment::query()
                ->withoutGlobalScope('sortByLatest')
                ->effective()
                ->where('user_id', $userId)
                ->where('is_active', true)
                ->where('is_primary', true)
                ->exists();

            if ($alreadyHasPrimary) {
                continue;
            }

            $preferredDepartmentId = $this->resolvePreferredDepartmentId($userId);

            $candidateQuery = UserAssignment::query()
                ->withoutGlobalScope('sortByLatest')
                ->effective()
                ->where('user_id', $userId)
                ->where('is_active', true);

            $candidate = null;
            if ($preferredDepartmentId > 0) {
                $candidate = (clone $candidateQuery)
                    ->where('department_id', $preferredDepartmentId)
                    ->orderByDesc('id')
                    ->first();
            }

            if (!$candidate) {
                $candidate = (clone $candidateQuery)
                    ->orderByDesc('id')
                    ->first();
            }

            if (!$candidate) {
                continue;
            }

            $assignmentService->updateFromCanonicalPayload($candidate, [
                'user_id' => (int) $candidate->user_id,
                'department_id' => (int) $candidate->department_id,
                'position_id' => !empty($candidate->position_id) ? (int) $candidate->position_id : null,
                'responsibility_id' => !empty($candidate->responsibility_id) ? (int) $candidate->responsibility_id : null,
                'scope_type' => (string) $candidate->scope_type,
                'is_primary' => true,
                'effective_from' => $candidate->effective_from ? $candidate->effective_from->toDateString() : null,
                'effective_to' => $candidate->effective_to ? $candidate->effective_to->toDateString() : null,
                'is_active' => (bool) $candidate->is_active,
                'note' => $candidate->note,
            ], null);

            $synced++;
        }

        return $synced;
    }

    protected function resolvePreferredDepartmentId(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $employee = Employee::query()
            ->withoutGlobalScopes()
            ->with(['primaryUnitPosting:id,employee_id,department_id'])
            ->where('user_id', $userId)
            ->first(['id', 'user_id', 'department_id', 'sub_department_id']);

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
}
