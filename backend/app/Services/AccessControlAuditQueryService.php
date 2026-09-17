<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\HumanResource\Entities\Delegation;
use Modules\HumanResource\Entities\Department;
use Spatie\Activitylog\Models\Activity;

/**
 * Phase 3E: read side of the unified Access Control Center Audit History.
 *
 * Merges two sources into ONE timeline rather than creating a second
 * storage table:
 *   1. `activity_log` rows written by AccessControlAuditService (log_name =
 *      'access_control') -- Role/Permission, User Access, Organization
 *      Scope, Approval Authority changes.
 *   2. `delegations` rows themselves, read directly -- created_by/
 *      revoked_by/revoked_at already capture "who created/revoked, when"
 *      with zero extra logging code, so Delegation events are derived here
 *      rather than duplicated into activity_log.
 *
 * This keeps exactly one writer per concept (no competing generic audit
 * writer) while still giving the admin one merged, filterable feed.
 */
class AccessControlAuditQueryService
{
    private const ACTION_LABELS_KM = [
        'role_created' => 'បង្កើតតួនាទីថ្មី',
        'role_renamed' => 'ប្តូរឈ្មោះតួនាទី',
        'role_deleted' => 'លុបតួនាទី',
        'role_permissions_updated' => 'កែប្រែសិទ្ធិរបស់តួនាទី',
        'user_roles_updated' => 'កែប្រែតួនាទីរបស់អ្នកប្រើប្រាស់',
        'user_direct_permissions_updated' => 'កែប្រែសិទ្ធិផ្ទាល់ខ្លួនរបស់អ្នកប្រើប្រាស់',
        'organization_scope_updated' => 'កែប្រែវិសាលភាពអង្គភាព',
        'approval_authority_updated' => 'កែប្រែការកំណត់សិទ្ធិអនុម័ត',
        'delegation_created' => 'បង្កើតការផ្ទេរសិទ្ធិ',
        'delegation_revoked' => 'ដកហូតការផ្ទេរសិទ្ធិ',
    ];

    private const CATEGORY_LABELS_KM = [
        AccessControlAuditService::CATEGORY_ROLE_PERMISSION => 'តួនាទី/សិទ្ធិ',
        AccessControlAuditService::CATEGORY_USER_ACCESS => 'សិទ្ធិអ្នកប្រើប្រាស់',
        AccessControlAuditService::CATEGORY_ORGANIZATION_SCOPE => 'វិសាលភាពអង្គភាព',
        AccessControlAuditService::CATEGORY_APPROVAL_AUTHORITY => 'សិទ្ធិអនុម័ត',
        AccessControlAuditService::CATEGORY_DELEGATION => 'ផ្ទេរសិទ្ធិ',
    ];

    /** How many raw rows to scan from each source before filtering/paginating -- generous for an internal admin screen, bounded to stay safe. */
    private const SCAN_LIMIT = 1000;

    /**
     * @param array{
     *   date_from?: string, date_to?: string, actor_id?: int,
     *   target_user_id?: int, category?: string, q?: string,
     *   page?: int, per_page?: int
     * } $filters
     * @return array{data: array, total: int, page: int, per_page: int}
     */
    public function query(array $filters): array
    {
        $category = trim((string) ($filters['category'] ?? ''));
        $rows = collect();

        if ($category === '' || $category !== AccessControlAuditService::CATEGORY_DELEGATION) {
            $rows = $rows->merge($this->activityRows($filters));
        }
        if ($category === '' || $category === AccessControlAuditService::CATEGORY_DELEGATION) {
            $rows = $rows->merge($this->delegationRows($filters));
        }

        $rows = $this->applyCommonFilters($rows, $filters)
            ->sortByDesc('occurred_at')
            ->values();

        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $total = $rows->count();
        $data = $rows->slice(($page - 1) * $perPage, $perPage)->values();

        return ['data' => $data->all(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public static function categoryOptions(): array
    {
        return collect(AccessControlAuditService::CATEGORIES)
            ->map(fn (string $c) => ['value' => $c, 'label' => self::CATEGORY_LABELS_KM[$c] ?? $c])
            ->values()->all();
    }

    private function activityRows(array $filters): Collection
    {
        $query = Activity::query()->where('log_name', AccessControlAuditService::LOG_NAME);

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }
        if (!empty($filters['actor_id'])) {
            $query->where('causer_id', (int) $filters['actor_id'])->where('causer_type', User::class);
        }

        $activities = $query->orderByDesc('created_at')->limit(self::SCAN_LIMIT)->get();

        $causerIds = $activities->pluck('causer_id')->filter()->unique()->values();
        $causers = User::query()->whereIn('id', $causerIds)->get(['id', 'full_name'])->keyBy('id');

        return $activities->map(function (Activity $activity) use ($causers) {
            // properties is cast to an Illuminate\Support\Collection by the
            // package, not a plain array -- (array) on it would cast the
            // Collection object itself, not its contents.
            $properties = $activity->properties instanceof Collection
                ? $activity->properties->toArray()
                : (array) $activity->properties;
            $causer = $causers->get($activity->causer_id);

            return [
                'id' => 'activity:' . $activity->id,
                'occurred_at' => optional($activity->created_at)->toIso8601String(),
                'category' => (string) ($properties['category'] ?? ''),
                'category_label' => self::CATEGORY_LABELS_KM[$properties['category'] ?? ''] ?? ($properties['category'] ?? ''),
                'action' => (string) $activity->event,
                'action_label' => self::ACTION_LABELS_KM[$activity->event] ?? $this->humanize((string) $activity->event),
                'actor' => $causer ? ['id' => $causer->id, 'full_name' => $causer->full_name] : null,
                'target_label' => (string) ($properties['target_label'] ?? ''),
                'target_user_id' => $activity->subject_type === User::class ? (int) $activity->subject_id : null,
                'before' => (array) ($properties['before'] ?? []),
                'after' => (array) ($properties['after'] ?? []),
                'source' => 'Access Control Center',
            ];
        });
    }

    private function delegationRows(array $filters): Collection
    {
        $query = Delegation::query()->withTrashed();

        if (!empty($filters['date_from'])) {
            $from = Carbon::parse($filters['date_from'])->startOfDay();
            $query->where(function ($q) use ($from) {
                $q->where('created_at', '>=', $from)->orWhere('revoked_at', '>=', $from);
            });
        }
        if (!empty($filters['date_to'])) {
            $to = Carbon::parse($filters['date_to'])->endOfDay();
            $query->where(function ($q) use ($to) {
                $q->where('created_at', '<=', $to)->orWhere('revoked_at', '<=', $to);
            });
        }

        $delegations = $query->orderByDesc('created_at')->limit(self::SCAN_LIMIT)->get();

        $userIds = $delegations->flatMap(fn (Delegation $d) => [$d->delegator_user_id, $d->delegatee_user_id, $d->created_by, $d->revoked_by])
            ->filter()->unique()->values();
        $users = User::query()->whereIn('id', $userIds)->get(['id', 'full_name'])->keyBy('id');

        $departmentIds = $delegations->flatMap(fn (Delegation $d) => $d->scopeDepartmentIds())->unique()->values();
        $departments = Department::query()->whereIn('id', $departmentIds)->pluck('department_name', 'id');

        $rows = collect();

        foreach ($delegations as $delegation) {
            $delegator = $users->get($delegation->delegator_user_id);
            $delegatee = $users->get($delegation->delegatee_user_id);
            $targetLabel = ($delegator?->full_name ?? '-') . ' → ' . ($delegatee?->full_name ?? '-') . ' (' . $delegation->authority_module_key . ')';
            $scopeLabel = $delegation->scopeDepartmentIds()
                ? collect($delegation->scopeDepartmentIds())->map(fn ($id) => $departments->get($id, "#{$id}"))->implode(', ')
                : $delegation->scope_type;

            $creator = $users->get($delegation->created_by);
            $rows->push([
                'id' => 'delegation:' . $delegation->uuid . ':created',
                'occurred_at' => optional($delegation->created_at)->toIso8601String(),
                'category' => AccessControlAuditService::CATEGORY_DELEGATION,
                'category_label' => self::CATEGORY_LABELS_KM[AccessControlAuditService::CATEGORY_DELEGATION],
                'action' => 'delegation_created',
                'action_label' => self::ACTION_LABELS_KM['delegation_created'],
                'actor' => $creator ? ['id' => $creator->id, 'full_name' => $creator->full_name] : null,
                'target_label' => $targetLabel,
                'target_user_id' => $delegation->delegatee_user_id,
                'before' => [],
                'after' => [
                    'authority' => $delegation->authority_module_key,
                    'scope' => $scopeLabel,
                    'starts_at' => optional($delegation->starts_at)->toIso8601String(),
                    'ends_at' => optional($delegation->ends_at)->toIso8601String(),
                    'reason' => $delegation->reason,
                ],
                'source' => 'Access Control Center',
            ]);

            if ($delegation->revoked_at) {
                $revoker = $users->get($delegation->revoked_by);
                $rows->push([
                    'id' => 'delegation:' . $delegation->uuid . ':revoked',
                    'occurred_at' => optional($delegation->revoked_at)->toIso8601String(),
                    'category' => AccessControlAuditService::CATEGORY_DELEGATION,
                    'category_label' => self::CATEGORY_LABELS_KM[AccessControlAuditService::CATEGORY_DELEGATION],
                    'action' => 'delegation_revoked',
                    'action_label' => self::ACTION_LABELS_KM['delegation_revoked'],
                    'actor' => $revoker ? ['id' => $revoker->id, 'full_name' => $revoker->full_name] : null,
                    'target_label' => $targetLabel,
                    'target_user_id' => $delegation->delegatee_user_id,
                    'before' => ['status' => 'active'],
                    'after' => ['status' => 'revoked'],
                    'source' => 'Access Control Center',
                ]);
            }
        }

        return $rows;
    }

    private function applyCommonFilters(Collection $rows, array $filters): Collection
    {
        $actorId = !empty($filters['actor_id']) ? (int) $filters['actor_id'] : null;
        $targetUserId = !empty($filters['target_user_id']) ? (int) $filters['target_user_id'] : null;
        $q = trim((string) ($filters['q'] ?? ''));

        return $rows->filter(function (array $row) use ($actorId, $targetUserId, $q) {
            if ($actorId !== null && (int) ($row['actor']['id'] ?? 0) !== $actorId) {
                return false;
            }
            if ($targetUserId !== null && (int) ($row['target_user_id'] ?? 0) !== $targetUserId) {
                return false;
            }
            if ($q !== '') {
                $haystack = mb_strtolower($row['action_label'] . ' ' . $row['target_label'] . ' ' . ($row['actor']['full_name'] ?? ''));
                if (!str_contains($haystack, mb_strtolower($q))) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private function humanize(string $key): string
    {
        return ucwords(str_replace(['_', '-', '.'], ' ', trim($key)));
    }
}
