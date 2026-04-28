<?php

namespace Modules\HumanResource\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\HumanResource\Entities\ResponsibilityTemplate;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Entities\UserOrgRole;

class GovernanceAssignmentService
{
    public function createFromCanonicalPayload(array $payload, ?int $actorId = null): UserAssignment
    {
        return DB::transaction(function () use ($payload, $actorId): UserAssignment {
            $normalized = $this->normalizeCanonicalPayload($payload);
            $this->assertPrimaryBusinessRule($normalized['user_id'], $normalized);

            if ($normalized['is_primary'] && $normalized['is_active']) {
                $this->unsetOtherPrimaryAssignments((int) $normalized['user_id']);
            }

            $assignment = UserAssignment::query()->create(array_merge($normalized, [
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]));

            $legacy = $this->syncLegacyRoleFromAssignment($assignment, null, $actorId);
            if ($legacy) {
                $assignment->setRelation('legacyOrgRole', $legacy);
            }

            return $assignment->fresh([
                'user',
                'department',
                'position',
                'responsibility',
                'legacyOrgRole',
            ]);
        });
    }

    public function updateFromCanonicalPayload(UserAssignment $assignment, array $payload, ?int $actorId = null): UserAssignment
    {
        return DB::transaction(function () use ($assignment, $payload, $actorId): UserAssignment {
            $normalized = $this->normalizeCanonicalPayload($payload);
            $this->assertPrimaryBusinessRule($normalized['user_id'], $normalized, (int) $assignment->id);

            if ($normalized['is_primary'] && $normalized['is_active']) {
                $this->unsetOtherPrimaryAssignments((int) $normalized['user_id'], (int) $assignment->id);
            }

            $assignment->update(array_merge($normalized, [
                'updated_by' => $actorId,
            ]));

            $legacy = $this->syncLegacyRoleFromAssignment($assignment->fresh(), $assignment->legacyOrgRole, $actorId);
            if ($legacy && (int) ($legacy->user_assignment_id ?? 0) !== (int) $assignment->id) {
                $legacy->user_assignment_id = (int) $assignment->id;
                $legacy->save();
            }

            return $assignment->fresh([
                'user',
                'department',
                'position',
                'responsibility',
                'legacyOrgRole',
            ]);
        });
    }

    public function deleteAssignment(UserAssignment $assignment, ?int $actorId = null): void
    {
        DB::transaction(function () use ($assignment, $actorId): void {
            $legacy = $assignment->legacyOrgRole()
                ->withoutGlobalScopes()
                ->first();

            if ($legacy) {
                $legacy->updated_by = $actorId;
                $legacy->deleted_by = $actorId;
                $legacy->save();
                $legacy->delete();
            }

            $assignment->updated_by = $actorId;
            $assignment->deleted_by = $actorId;
            $assignment->save();
            $assignment->delete();
        });
    }

    public function upsertFromLegacyPayload(array $legacyPayload, ?UserOrgRole $legacyRecord = null, ?int $actorId = null): UserAssignment
    {
        return DB::transaction(function () use ($legacyPayload, $legacyRecord, $actorId): UserAssignment {
            $payload = $this->normalizeLegacyPayloadToCanonical($legacyPayload, $legacyRecord);

            $assignment = null;
            if ($legacyRecord && !empty($legacyRecord->user_assignment_id)) {
                $assignment = UserAssignment::query()
                    ->withoutGlobalScope('sortByLatest')
                    ->find((int) $legacyRecord->user_assignment_id);
            }

            if (!$assignment) {
                $assignment = UserAssignment::query()
                    ->withoutGlobalScope('sortByLatest')
                    ->where('user_id', (int) $payload['user_id'])
                    ->where('department_id', (int) $payload['department_id'])
                    ->where('responsibility_id', (int) $payload['responsibility_id'])
                    ->whereNull('deleted_at')
                    ->latest('id')
                    ->first();
            }

            if ($assignment) {
                $assignment = $this->updateFromCanonicalPayload($assignment, $payload, $actorId);
            } else {
                $assignment = $this->createFromCanonicalPayload($payload, $actorId);
            }

            $legacy = $this->syncLegacyRoleFromAssignment($assignment, $legacyRecord, $actorId);
            if ($legacy && (int) ($legacy->user_assignment_id ?? 0) !== (int) $assignment->id) {
                $legacy->user_assignment_id = (int) $assignment->id;
                $legacy->save();
            }

            return $assignment->fresh([
                'user',
                'department',
                'position',
                'responsibility',
                'legacyOrgRole',
            ]);
        });
    }

    public function deleteByLegacyRecord(UserOrgRole $legacyRecord, ?int $actorId = null): void
    {
        DB::transaction(function () use ($legacyRecord, $actorId): void {
            $assignment = null;
            if (!empty($legacyRecord->user_assignment_id)) {
                $assignment = UserAssignment::query()
                    ->withoutGlobalScope('sortByLatest')
                    ->find((int) $legacyRecord->user_assignment_id);
            }

            $legacyRecord->updated_by = $actorId;
            $legacyRecord->deleted_by = $actorId;
            $legacyRecord->save();
            $legacyRecord->delete();

            if ($assignment) {
                $assignment->updated_by = $actorId;
                $assignment->deleted_by = $actorId;
                $assignment->save();
                $assignment->delete();
            }
        });
    }

    protected function normalizeCanonicalPayload(array $payload): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            throw ValidationException::withMessages([
                'user_id' => localize('user_required', 'User is required.'),
            ]);
        }

        [$defaultDepartmentId, $defaultPositionId] = $this->resolveCurrentPlacementForUser($userId);

        $templateId = (int) ($payload['responsibility_template_id'] ?? 0);
        $template = null;
        if ($templateId > 0) {
            $template = ResponsibilityTemplate::query()
                ->withoutGlobalScope('sortByLatest')
                ->active()
                ->find($templateId);

            if (!$template) {
                throw ValidationException::withMessages([
                    'responsibility_template_id' => localize('template_not_found_or_inactive', 'Template not found or inactive.'),
                ]);
            }
        }

        $responsibilityId = (int) ($payload['responsibility_id'] ?? 0);
        if ($template) {
            $responsibilityId = (int) ($template->responsibility_id ?? 0);
        } elseif ($responsibilityId <= 0 && !empty($payload['responsibility_code'])) {
            $responsibilityId = (int) (SystemRole::query()
                ->where('code', trim((string) $payload['responsibility_code']))
                ->value('id') ?? 0);
        }

        if ($responsibilityId <= 0) {
            throw ValidationException::withMessages([
                'responsibility_template_id' => localize(
                    'template_or_responsibility_required',
                    'Responsibility template (or direct responsibility) is required.'
                ),
            ]);
        }

        $scopeSource = (string) ($payload['scope_type'] ?? ($template?->default_scope_type ?: UserAssignment::SCOPE_SELF_AND_CHILDREN));
        $scope = $this->normalizeScope($scopeSource);
        if (!in_array($scope, UserAssignment::scopeOptions(), true)) {
            throw ValidationException::withMessages([
                'scope_type' => localize('invalid_scope', 'Invalid scope type.'),
            ]);
        }

        $departmentId = (int) ($payload['department_id'] ?? 0);
        if ($departmentId <= 0) {
            $departmentId = $defaultDepartmentId;
        }

        $positionId = !empty($payload['position_id']) ? (int) $payload['position_id'] : 0;
        if ($positionId <= 0) {
            $positionId = (int) ($template?->position_id ?: $defaultPositionId);
        }

        $normalized = [
            'user_id' => $userId,
            'department_id' => $departmentId,
            'position_id' => $positionId > 0 ? $positionId : null,
            'responsibility_template_id' => $templateId > 0 ? $templateId : null,
            'responsibility_id' => $responsibilityId,
            'scope_type' => $scope,
            'is_primary' => (bool) ($payload['is_primary'] ?? false),
            'effective_from' => !empty($payload['effective_from']) ? (string) $payload['effective_from'] : null,
            'effective_to' => !empty($payload['effective_to']) ? (string) $payload['effective_to'] : null,
            'is_active' => (bool) ($payload['is_active'] ?? true),
            'note' => !empty($payload['note']) ? trim((string) $payload['note']) : null,
        ];

        if ($normalized['department_id'] <= 0) {
            throw ValidationException::withMessages([
                'department_id' => localize(
                    'department_required_or_set_placement',
                    'Department is required. Please select department or ensure current placement exists.'
                ),
            ]);
        }

        return $normalized;
    }

    protected function normalizeLegacyPayloadToCanonical(array $legacyPayload, ?UserOrgRole $legacyRecord = null): array
    {
        $scope = $this->normalizeScope((string) ($legacyPayload['scope_type'] ?? 'self_and_children'));

        $responsibilityId = (int) ($legacyPayload['system_role_id'] ?? 0);
        if ($responsibilityId <= 0 && !empty($legacyPayload['org_role'])) {
            $responsibilityId = (int) (SystemRole::query()
                ->where('code', trim((string) $legacyPayload['org_role']))
                ->value('id') ?? 0);
        }

        return [
            'user_id' => (int) ($legacyPayload['user_id'] ?? ($legacyRecord?->user_id ?? 0)),
            'department_id' => (int) ($legacyPayload['department_id'] ?? ($legacyRecord?->department_id ?? 0)),
            'position_id' => null,
            'responsibility_template_id' => null,
            'responsibility_id' => $responsibilityId,
            'scope_type' => $scope,
            'is_primary' => (bool) ($legacyPayload['is_primary'] ?? false),
            'effective_from' => !empty($legacyPayload['effective_from']) ? (string) $legacyPayload['effective_from'] : null,
            'effective_to' => !empty($legacyPayload['effective_to']) ? (string) $legacyPayload['effective_to'] : null,
            'is_active' => (bool) ($legacyPayload['is_active'] ?? true),
            'note' => !empty($legacyPayload['note']) ? trim((string) $legacyPayload['note']) : null,
        ];
    }

    protected function syncLegacyRoleFromAssignment(
        UserAssignment $assignment,
        ?UserOrgRole $legacyRecord = null,
        ?int $actorId = null
    ): ?UserOrgRole {
        $responsibilityId = (int) ($assignment->responsibility_id ?? 0);
        if ($responsibilityId <= 0) {
            return null;
        }

        $responsibilityCode = (string) (SystemRole::query()
            ->where('id', $responsibilityId)
            ->value('code') ?? '');
        if ($responsibilityCode === '') {
            return null;
        }

        $record = $legacyRecord;
        if (!$record && !empty($assignment->id)) {
            $record = UserOrgRole::query()
                ->withoutGlobalScope('sortByLatest')
                ->withTrashed()
                ->where('user_assignment_id', (int) $assignment->id)
                ->first();
        }

        if (!$record) {
            $record = UserOrgRole::query()
                ->withoutGlobalScope('sortByLatest')
                ->withTrashed()
                ->where('user_id', (int) $assignment->user_id)
                ->where('department_id', (int) $assignment->department_id)
                ->where('system_role_id', $responsibilityId)
                ->latest('id')
                ->first();
        }

        $payload = [
            'user_id' => (int) $assignment->user_id,
            'user_assignment_id' => (int) $assignment->id,
            'department_id' => (int) $assignment->department_id,
            'org_role' => $responsibilityCode,
            'system_role_id' => $responsibilityId,
            'scope_type' => $this->normalizeScope((string) $assignment->scope_type),
            'effective_from' => $assignment->effective_from,
            'effective_to' => $assignment->effective_to,
            'is_active' => (bool) $assignment->is_active,
            'note' => $assignment->note,
            'updated_by' => $actorId,
        ];

        if ($record) {
            if ($record->trashed()) {
                $record->restore();
            }
            $record->fill($payload);
            $record->save();
            return $record;
        }

        return UserOrgRole::query()
            ->withoutGlobalScope('sortByLatest')
            ->create(array_merge($payload, [
                'created_by' => $actorId,
            ]));
    }

    protected function normalizeScope(string $scope): string
    {
        $scope = trim(mb_strtolower($scope));
        if ($scope === UserOrgRole::SCOPE_SELF) {
            return UserAssignment::SCOPE_SELF_ONLY;
        }

        return $scope;
    }

    protected function unsetOtherPrimaryAssignments(int $userId, ?int $ignoreAssignmentId = null): void
    {
        if ($userId <= 0) {
            return;
        }

        $query = UserAssignment::query()
            ->withoutGlobalScope('sortByLatest')
            ->where('user_id', $userId)
            ->where('is_primary', true)
            ->where('is_active', true);

        if ($ignoreAssignmentId) {
            $query->where('id', '!=', $ignoreAssignmentId);
        }

        $query->update(['is_primary' => false]);
    }

    protected function assertPrimaryBusinessRule(int $userId, array $payload, ?int $ignoreAssignmentId = null): void
    {
        if ($userId <= 0 || !($payload['is_primary'] ?? false) || !($payload['is_active'] ?? false)) {
            return;
        }

        $newFrom = !empty($payload['effective_from']) ? (string) $payload['effective_from'] : null;
        $newTo = !empty($payload['effective_to']) ? (string) $payload['effective_to'] : null;

        $existing = UserAssignment::query()
            ->withoutGlobalScope('sortByLatest')
            ->where('user_id', $userId)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->when($ignoreAssignmentId, fn ($q) => $q->where('id', '!=', $ignoreAssignmentId))
            ->get(['id', 'effective_from', 'effective_to']);

        foreach ($existing as $row) {
            $currentFrom = $row->effective_from ? (string) $row->effective_from->toDateString() : null;
            $currentTo = $row->effective_to ? (string) $row->effective_to->toDateString() : null;

            if ($this->dateRangesOverlap($newFrom, $newTo, $currentFrom, $currentTo)) {
                throw ValidationException::withMessages([
                    'is_primary' => localize(
                        'duplicate_primary_assignment_overlap',
                        'This user already has another active primary assignment in the same effective period.'
                    ),
                ]);
            }
        }
    }

    protected function dateRangesOverlap(?string $aFrom, ?string $aTo, ?string $bFrom, ?string $bTo): bool
    {
        $startA = $aFrom ?: '1000-01-01';
        $endA = $aTo ?: '9999-12-31';
        $startB = $bFrom ?: '1000-01-01';
        $endB = $bTo ?: '9999-12-31';

        return $startA <= $endB && $startB <= $endA;
    }

    protected function resolveCurrentPlacementForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [0, 0];
        }

        $user = User::query()
            ->withoutGlobalScope('sortByLatest')
            ->with([
                'employee:id,user_id,department_id,sub_department_id,position_id',
                'employee.primaryUnitPosting' => function ($query): void {
                    $query->select([
                        'employee_unit_postings.id',
                        'employee_unit_postings.employee_id',
                        'employee_unit_postings.department_id',
                        'employee_unit_postings.position_id',
                    ]);
                },
            ])
            ->find($userId, ['id']);

        if (!$user || !$user->employee) {
            return [0, 0];
        }

        $employee = $user->employee;
        $departmentId = (int) ($employee->primaryUnitPosting?->department_id
            ?: $employee->sub_department_id
            ?: $employee->department_id
            ?: 0);
        $positionId = (int) ($employee->primaryUnitPosting?->position_id
            ?: $employee->position_id
            ?: 0);

        return [$departmentId, $positionId];
    }
}
