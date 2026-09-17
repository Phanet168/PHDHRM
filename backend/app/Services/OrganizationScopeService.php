<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\UserAssignment;
use Modules\HumanResource\Services\GovernanceAssignmentService;
use Modules\HumanResource\Support\OrgHierarchyAccessService;
use Modules\HumanResource\Support\OrgUnitRuleService;

/**
 * Phase 3B: canonical, read+write organization scope service.
 *
 * IMPORTANT (discovery finding): a literal "SELECTED_UNITS" scope_type value
 * does NOT exist anywhere in the current schema or code (verified by an
 * exhaustive search -- see the Phase 3B report). What DOES already exist,
 * and is reused here as-is, is the ability for one user to hold MULTIPLE
 * `UserAssignment` rows for the SAME responsibility (one row per department).
 * `OrgHierarchyAccessService::managedBranchIds()` already unions those rows'
 * branch IDs together for authorization purposes. SELECTED_UNITS is
 * therefore introduced here purely as a PRESENTATION/EDITING concept over
 * that existing multi-row capability -- when a responsibility has more than
 * one active UserAssignment row, this service presents and edits it as one
 * "SELECTED_UNITS" group. No new column and no new table were added.
 *
 * All actual row writes go through the existing
 * Modules\HumanResource\Services\GovernanceAssignmentService (create/update/
 * delete), so legacy-role syncing and the primary-assignment business rule
 * keep working exactly as they do for the existing HR governance UI.
 */
class OrganizationScopeService
{
    public const SELF = 'SELF';
    public const UNIT = 'UNIT';
    public const UNIT_TREE = 'UNIT_TREE';
    public const SELECTED_UNITS = 'SELECTED_UNITS';
    public const ORGANIZATION = 'ORGANIZATION';

    private const DB_TO_PRESENTATION = [
        UserAssignment::SCOPE_SELF_ONLY => self::SELF,
        UserAssignment::SCOPE_SELF_UNIT_ONLY => self::UNIT,
        UserAssignment::SCOPE_SELF_AND_CHILDREN => self::UNIT_TREE,
        UserAssignment::SCOPE_ALL => self::ORGANIZATION,
    ];

    private const PRESENTATION_TO_DB = [
        self::SELF => UserAssignment::SCOPE_SELF_ONLY,
        self::UNIT => UserAssignment::SCOPE_SELF_UNIT_ONLY,
        self::UNIT_TREE => UserAssignment::SCOPE_SELF_AND_CHILDREN,
        self::ORGANIZATION => UserAssignment::SCOPE_ALL,
        // A SELECTED_UNITS group is simply N rows, each scoped self_only to
        // one of the selected departments.
        self::SELECTED_UNITS => UserAssignment::SCOPE_SELF_ONLY,
    ];

    public const LABELS_KM = [
        self::SELF => 'ខ្លួនឯង',
        self::UNIT => 'អង្គភាពរបស់ខ្លួន',
        self::UNIT_TREE => 'អង្គភាពរបស់ខ្លួន និងអង្គភាពក្រោមឱវាទ',
        self::SELECTED_UNITS => 'អង្គភាពដែលបានជ្រើសរើស',
        self::ORGANIZATION => 'អង្គភាពទាំងមូល',
    ];

    public function __construct(
        private readonly OrgHierarchyAccessService $orgHierarchyAccessService,
        private readonly OrgUnitRuleService $orgUnitRuleService,
        private readonly GovernanceAssignmentService $governanceAssignmentService,
    ) {
    }

    public static function presentationOptions(): array
    {
        return [self::SELF, self::UNIT, self::UNIT_TREE, self::SELECTED_UNITS, self::ORGANIZATION];
    }

    /** Flat, searchable hierarchy list for the unit picker UI. Reuses OrgUnitRuleService as-is. */
    public function hierarchyPickerOptions(): Collection
    {
        return $this->orgUnitRuleService->hierarchyOptions()->map(fn ($node) => [
            'id' => (int) $node->id,
            'label' => $node->label,
            'path' => $node->path,
            'depth' => $node->depth,
        ])->values();
    }

    /** @return array<int, array> one entry per responsibility "assignment group" for this user */
    public function assignmentGroups(User $user): array
    {
        $rows = $this->activeAssignmentsFor($user);

        return $rows->groupBy(fn (UserAssignment $a) => $this->groupKey($a))
            ->map(fn ($groupRows, $groupKey) => $this->buildGroupPayload((string) $groupKey, $groupRows))
            ->values()
            ->all();
    }

    /**
     * Update one assignment group's scope. Reconciles the group's underlying
     * UserAssignment rows (create/update/delete as needed) via
     * GovernanceAssignmentService so legacy sync and the "one primary
     * assignment" business rule are preserved exactly as the existing HR
     * governance screen already enforces them. Never touches rows belonging
     * to a DIFFERENT assignment group for this user.
     *
     * @param int[] $departmentIds
     */
    public function updateGroupScope(User $user, string $groupKey, string $presentationScope, array $departmentIds, ?int $actorId): array
    {
        if (!in_array($presentationScope, self::presentationOptions(), true)) {
            throw ValidationException::withMessages(['scope_type' => 'វិសាលភាពមិនត្រឹមត្រូវ។']);
        }

        $existingRows = $this->activeAssignmentsFor($user)
            ->filter(fn (UserAssignment $a) => $this->groupKey($a) === $groupKey)
            ->values();

        if ($existingRows->isEmpty()) {
            throw ValidationException::withMessages([
                'group_key' => 'រកមិនឃើញការទទួលខុសត្រូវនេះទេ។ សូមបង្កើតការចាត់តាំង (assignment) ជាមុនសិនតាមរយៈអេក្រង់ Org Governance។',
            ]);
        }

        $validDepartmentIds = Department::query()
            ->whereIn('id', collect($departmentIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (empty($validDepartmentIds)) {
            throw ValidationException::withMessages(['department_ids' => 'សូមជ្រើសរើសអង្គភាពយ៉ាងហោចណាស់មួយ។']);
        }

        // Only SELECTED_UNITS is allowed to keep more than one department; any
        // other scope type collapses to exactly one (the first submitted).
        if ($presentationScope !== self::SELECTED_UNITS) {
            $validDepartmentIds = [$validDepartmentIds[0]];
        }

        $dbScopeType = self::PRESENTATION_TO_DB[$presentationScope];
        $template = $existingRows->first();
        $wasPrimary = $existingRows->contains(fn (UserAssignment $a) => (bool) $a->is_primary);
        $existingByDept = $existingRows->keyBy(fn (UserAssignment $a) => (int) $a->department_id);

        $keepIds = [];
        foreach (array_values($validDepartmentIds) as $index => $departmentId) {
            $payload = [
                'user_id' => $user->id,
                'department_id' => $departmentId,
                'position_id' => $template->position_id,
                'responsibility_template_id' => $template->responsibility_template_id,
                'responsibility_id' => $template->responsibility_id,
                'scope_type' => $dbScopeType,
                'is_primary' => $index === 0 && $wasPrimary,
                'is_active' => true,
                'effective_from' => optional($template->effective_from)->toDateString(),
                'effective_to' => optional($template->effective_to)->toDateString(),
                'note' => $template->note,
            ];

            if ($existingByDept->has($departmentId)) {
                $row = $this->governanceAssignmentService->updateFromCanonicalPayload($existingByDept->get($departmentId), $payload, $actorId);
            } else {
                $row = $this->governanceAssignmentService->createFromCanonicalPayload($payload, $actorId);
            }
            $keepIds[] = $row->id;
        }

        foreach ($existingRows as $row) {
            if (!in_array($row->id, $keepIds, true)) {
                $this->governanceAssignmentService->deleteAssignment($row, $actorId);
            }
        }

        $refreshed = $this->activeAssignmentsFor($user)->filter(fn (UserAssignment $a) => $this->groupKey($a) === $groupKey)->values();

        return $this->buildGroupPayload($groupKey, $refreshed);
    }

    private function activeAssignmentsFor(User $user): Collection
    {
        return UserAssignment::query()
            ->withoutGlobalScope('sortByLatest')
            ->with([
                'department:id,department_name',
                'responsibility:id,name,name_km,code',
                'responsibilityTemplate:id,name,name_km',
            ])
            ->where('user_id', $user->id)
            ->effective()
            ->orderBy('department_id')
            ->orderBy('id')
            ->get();
    }

    private function groupKey(UserAssignment $assignment): string
    {
        return ((int) $assignment->responsibility_id) . '-' . ((int) $assignment->responsibility_template_id);
    }

    private function buildGroupPayload(string $groupKey, Collection $rows): array
    {
        $first = $rows->first();
        $name = $first->responsibilityTemplate?->name_km
            ?: $first->responsibilityTemplate?->name
            ?: $first->responsibility?->name_km
            ?: $first->responsibility?->name
            ?: 'N/A';

        $rowPayloads = $rows->map(fn (UserAssignment $a) => [
            'id' => $a->id,
            'department_id' => $a->department_id,
            'department_name' => $a->department?->department_name,
            'scope_type' => $a->scope_type,
            'is_primary' => (bool) $a->is_primary,
            'effective_from' => optional($a->effective_from)->toDateString(),
            'effective_to' => optional($a->effective_to)->toDateString(),
        ])->values();

        $presentation = $rows->count() > 1
            ? self::SELECTED_UNITS
            : (self::DB_TO_PRESENTATION[(string) $first->scope_type] ?? self::UNIT_TREE);

        $effectiveUnits = [];
        $isOrganizationWide = false;
        foreach ($rows as $row) {
            $expanded = $this->orgHierarchyAccessService->expandScopeBranchIds((string) $row->scope_type, (int) $row->department_id);
            if ($expanded === null) {
                $isOrganizationWide = true;
                break;
            }
            $effectiveUnits = array_merge($effectiveUnits, $expanded);
        }

        return [
            'group_key' => $groupKey,
            'responsibility_id' => $first->responsibility_id,
            'responsibility_template_id' => $first->responsibility_template_id,
            'responsibility_name' => $name,
            'presentation_scope' => $presentation,
            'presentation_scope_label' => self::LABELS_KM[$presentation] ?? $presentation,
            'assignments' => $rowPayloads,
            'selected_departments' => $rowPayloads->map(fn ($r) => ['id' => $r['department_id'], 'name' => $r['department_name']])->values(),
            'effective_unit_count' => $isOrganizationWide ? null : count(array_unique($effectiveUnits)),
        ];
    }
}
