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
 *
 * Unit-type mapping:
 *   1 = PHD  (Provincial Health Department) → sees everything
 *   4 = OD   (Operational District)         → sees own + children
 *   6 = Hospital                            → sees own only
 *   7 = HC   (Health Center)                → sees own + children
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

    public function __construct(protected readonly OrgUnitRuleService $orgUnitRuleService)
    {
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

        $orgRole = UserOrgRole::withoutGlobalScopes()
            ->where('user_id', $uid)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', now());
            })
            ->first();

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
     * @return int[]|null  null means "all departments"
     */
    public function scopedDepartmentIds(Department $dept, string $scopeType): ?array
    {
        $deptId = (int) $dept->id;

        return match ($scopeType) {
            UserOrgRole::SCOPE_SELF_ONLY,
            'self' // backward compat
                => [$deptId],

            UserOrgRole::SCOPE_SELF_UNIT_ONLY
                => $this->siblingSameTypeIds($dept),

            UserOrgRole::SCOPE_ALL
                => null,

            default // self_and_children
                => $this->orgUnitRuleService->branchIdsIncludingSelf($deptId),
        };
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
     * Recursively collect all descendant department IDs.
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
     * Get sibling department IDs with the same unit_type under the same parent.
     * Used for scope_type = 'self_unit_only'.
     */
    protected function siblingSameTypeIds(Department $dept): array
    {
        if (!$dept->parent_id || !$dept->unit_type_id) {
            return [(int) $dept->id];
        }

        return Department::withoutGlobalScopes()
            ->where('parent_id', $dept->parent_id)
            ->where('unit_type_id', $dept->unit_type_id)
            ->where('is_active', 1)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
