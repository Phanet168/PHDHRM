<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

/**
 * Phase 3E: the ONE writer for Access Control Center governance history.
 *
 * This does not introduce a second audit system -- it is a thin, structured
 * wrapper around the already-installed and already-wired
 * spatie/laravel-activitylog package (see app/Helpers/Helpers.php's
 * logNow(), and the 9+ models already using LogsActivity). It writes to the
 * SAME `activity_log` table every other activity record in this system
 * already uses, tagged with a dedicated log name so it can be queried back
 * out without interfering with unrelated activity (e.g. Setting module CRUD
 * notifications, User model login events).
 *
 * Delegation create/revoke events are deliberately NOT written here -- the
 * `delegations` table already carries created_by/updated_by/revoked_by/
 * revoked_at/reason for free, and duplicating that into a second log would
 * be exactly the kind of competing generic writer the locked architecture
 * forbids. AccessControlAuditQueryService reads Delegation rows directly
 * and merges them into the unified timeline instead.
 */
class AccessControlAuditService
{
    public const LOG_NAME = 'access_control';

    public const CATEGORY_ROLE_PERMISSION = 'ROLE_PERMISSION';
    public const CATEGORY_USER_ACCESS = 'USER_ACCESS';
    public const CATEGORY_ORGANIZATION_SCOPE = 'ORGANIZATION_SCOPE';
    public const CATEGORY_APPROVAL_AUTHORITY = 'APPROVAL_AUTHORITY';
    public const CATEGORY_DELEGATION = 'DELEGATION';

    public const CATEGORIES = [
        self::CATEGORY_ROLE_PERMISSION,
        self::CATEGORY_USER_ACCESS,
        self::CATEGORY_ORGANIZATION_SCOPE,
        self::CATEGORY_APPROVAL_AUTHORITY,
        self::CATEGORY_DELEGATION,
    ];

    /**
     * @param string $category one of self::CATEGORIES
     * @param string $action a short snake_case verb, e.g. "role_created", "user_roles_updated"
     * @param Model|null $subject the record the action was performed on (Role, User, WorkflowDefinition, ...)
     * @param string $targetLabel human-readable label for the subject, shown in the UI without a join
     * @param array $before sanitized, display-ready "before" state -- never raw credentials/tokens
     * @param array $after sanitized, display-ready "after" state
     */
    public function record(
        string $category,
        string $action,
        ?Model $subject,
        string $targetLabel,
        array $before,
        array $after,
        ?int $causerId = null
    ): ?ActivityContract {
        $causer = $causerId ? User::query()->find($causerId) : Auth::user();

        $logger = activity(self::LOG_NAME)
            ->causedBy($causer)
            ->event($action)
            ->withProperties([
                'category' => $category,
                'target_label' => $targetLabel,
                'before' => $before,
                'after' => $after,
            ]);

        if ($subject) {
            $logger->performedOn($subject);
        }

        return $logger->log($category . '.' . $action);
    }
}
