<?php

namespace Modules\HumanResource\Support;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Entities\UserOrgRole;

/**
 * Unified facility-level scope service for all modules.
 *
 * Replaces per-module traits (PharmScope, CorrespondenceScope) with a single
 * service that resolves the user's facility level and accessible department IDs.
 * Also used directly by ManualAttendanceController.
 *
 * Unit-type mapping:
 *   1 = PHD  (Provincial Health Department) → sees everything
 *   4 = OD   (Operational District)         → sees own + children
 *   6 = Hospital                            → sees own only
 *   7 = HC   (Health Center)                → sees own + children
 *
 * Phase 3B.1 (scope consolidation): userDepartment() now resolves through
 * OrgHierarchyAccessService::effectiveOrgRoles() -- the same canonical
 * resolver AccessControlService/OrganizationScopeService/Leave/Notice already
 * use, which reads modules/HumanResource/Entities/UserAssignment.php first
 * and only falls back to the legacy user_org_roles table when a user has NO
 * UserAssignment rows at all. Previously this read user_org_roles directly
 * and unconditionally, which could silently miss/mismatch a user configured
 * only through the newer Access Control Center Organization Scope tab, or
 * return a stale legacy row that had drifted from its (soft-deleted, or
 * never-synced) canonical counterpart. Pharmaceutical/Correspondence/Manual
 * Attendance all inherit this fix automatically since they all resolve
 * department scope through this one shared service.
 */
class OrgScopeService
{
    public const LEVEL_MAP = [
        1 => 'phd',
        4 => 'od',
        6 => 'hospital',
        7 => 'hc',
    ];

    public const LEVEL_LABELS = [
        'phd'      => 'PHD',
        'od'       => 'OD',
        'hospital' => 'Hospital',
        'hc'       => 'HC',
    ];

    /** @var array<int, ?Department> */
    protected static array $deptCache = [];

    /** @var array<int, string> */
    protected static array $levelCache = [];

    public function __construct(
        protected readonly OrgHierarchyAccessService $orgHierarchyAccessService,
    ) {
    }

    /**
     * Resolve the user's facility level (phd/od/hospital/hc/unknown).
     */
    public function userLevel(?User $user = null): string
    {
        $user = $user ?: Auth::user();
        if (!$user) {
            return 'unknown';
        }

        $uid = (int) $user->id;
        if (isset(static::$levelCache[$uid])) {
            return static::$levelCache[$uid];
        }

        $dept = $this->userDepartment($user);
        if (!$dept) {
            return static::$levelCache[$uid] = 'unknown';
        }

        return static::$levelCache[$uid] = $this->resolveLevelFromDepartment($dept);
    }

    /**
     * Get department IDs the user can access.
     *
    * - PHD level      -> null (all departments)
    * - OD level       -> own + children/descendants
    * - Hospital level -> own only
    * - HC level       -> own + children/descendants (health posts under supervision)
    * - unknown        -> empty array (no access)
     */
    public function accessibleDepartmentIds(?User $user = null): ?array
    {
        $user = $user ?: Auth::user();
        $dept = $this->userDepartment($user);

        if (!$dept) {
            return [];
        }

        $level = $this->resolveLevelFromDepartment($dept);

        if ($level === 'phd') {
            return null; // all access
        }

        if (in_array($level, ['od', 'hc'], true)) {
            return array_merge([$dept->id], $this->allDescendantIds($dept->id));
        }

        return [$dept->id];
    }

    /**
     * Get the user's primary department from their active org role.
     */
    public function userDepartment(?User $user = null): ?Department
    {
        $user = $user ?: Auth::user();
        if (!$user) {
            return null;
        }

        $uid = (int) $user->id;
        if (array_key_exists($uid, static::$deptCache)) {
            return static::$deptCache[$uid];
        }

        /** @var UserOrgRole|null $orgRole */
        $orgRole = $this->orgHierarchyAccessService->effectiveOrgRoles($user)->first();

        if (!$orgRole || !$orgRole->department_id) {
            return static::$deptCache[$uid] = null;
        }

        $dept = Department::withoutGlobalScopes()->find($orgRole->department_id);
        return static::$deptCache[$uid] = $dept;
    }

    /**
     * Level label for display.
     */
    public function levelLabel(?User $user = null): string
    {
        $level = $this->userLevel($user);
        return self::LEVEL_LABELS[$level] ?? '';
    }

    /**
     * Expand department IDs based on scope_type (4 scope types).
     *
     * Phase 3B.2: delegates to OrgHierarchyAccessService::expandScopeBranchIds(),
     * which performs the exact same expansion (confirmed equivalent by
     * ScopeConsolidationOrgScopeServiceDedupTest, written against this
     * method's behavior before this delegation was introduced) -- this
     * class no longer maintains its own copy of scope_type -> department-ids
     * expansion logic.
     *
     * @return int[]|null  null means "all departments"
     */
    public function scopedDepartmentIds(Department $dept, string $scopeType): ?array
    {
        return $this->orgHierarchyAccessService->expandScopeBranchIds($scopeType, (int) $dept->id);
    }

    /**
     * Resolve level from a Department entity.
     */
    public function resolveLevelFromDepartment(Department $dept): string
    {
        if (isset(self::LEVEL_MAP[$dept->unit_type_id])) {
            return self::LEVEL_MAP[$dept->unit_type_id];
        }

        // Walk up parent chain to find a known level
        $current = $dept;
        $guard = 0;
        while ($current->parent_id && $guard < 5) {
            $parent = Department::withoutGlobalScopes()
                ->select('id', 'parent_id', 'unit_type_id')
                ->find($current->parent_id);

            if (!$parent) {
                break;
            }

            if (isset(self::LEVEL_MAP[$parent->unit_type_id])) {
                return self::LEVEL_MAP[$parent->unit_type_id];
            }

            $current = $parent;
            $guard++;
        }

        return 'unknown';
    }

    /**
     * Recursively collect all descendant department IDs, for the PHD/OD/
     * Hospital/HC FACILITY-LEVEL hierarchy used by accessibleDepartmentIds()
     * (LEVEL_MAP above) -- deliberately NOT consolidated onto
     * OrgHierarchyAccessService::expandScopeBranchIds()/branchIdsIncludingSelf()
     * during Phase 3B.2: those answer a different question (UserAssignment
     * scope_type expansion) and are not proven equivalent to this method --
     * this one has no unit_type_id-not-null filter and a depth cap, while
     * branchIdsIncludingSelf() has neither. Conflating the two here would
     * risk a silent behavior change for Pharmaceutical/Correspondence/Manual
     * Attendance's facility-level access, so scopedDepartmentIds() (the
     * generic scope_type expansion, confirmed equivalent) was consolidated
     * instead and this one was deliberately left alone.
     */
    protected function allDescendantIds(int $parentId, int $maxDepth = 5): array
    {
        if ($maxDepth <= 0) {
            return [];
        }

        $childIds = Department::withoutGlobalScopes()
            ->where('parent_id', $parentId)
            ->where('is_active', 1)
            ->pluck('id')
            ->toArray();

        $all = $childIds;
        foreach ($childIds as $childId) {
            $all = array_merge($all, $this->allDescendantIds($childId, $maxDepth - 1));
        }

        return $all;
    }

    /**
     * Clear internal caches (useful in tests / queue workers).
     */
    public static function flushCache(): void
    {
        static::$deptCache = [];
        static::$levelCache = [];
    }
}
