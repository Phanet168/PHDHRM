<?php

namespace Modules\Report\Services;

use App\Models\User;
use Modules\Report\Entities\ReportIndicator;
use Modules\Report\Entities\ReportUserUnit;

/**
 * Who may enter values (same rule as PHDReport's data entry):
 * settings managers edit everything; other users with update permission edit indicators of the
 * units assigned to them (report_user_units.can_edit) or indicators they own.
 */
class ReportAccess
{
    private array $unitCache = [];

    public function isManager(?User $user): bool
    {
        return $user !== null && $user->can('update_health_report_settings');
    }

    public function canEnter(?User $user): bool
    {
        return $user !== null && $user->can('update_health_activity_report');
    }

    /** @return int[] unit ids the user may edit, or null meaning "all units" */
    public function editableUnitIds(?User $user): ?array
    {
        if ($this->isManager($user)) {
            return null;
        }
        if (!$this->canEnter($user)) {
            return [];
        }

        return $this->unitCache[$user->id] ??= ReportUserUnit::where('user_id', $user->id)
            ->where('can_edit', true)->pluck('unit_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return int[] units assigned to the user (edit or view), used to default the data-entry filter */
    public function assignedUnitIds(User $user): array
    {
        return ReportUserUnit::where('user_id', $user->id)->pluck('unit_id')->map(fn ($id) => (int) $id)->all();
    }

    public function canEditIndicator(?User $user, ReportIndicator $indicator): bool
    {
        $units = $this->editableUnitIds($user);
        if ($units === null) {
            return true;
        }
        if (!$this->canEnter($user)) {
            return false;
        }

        return in_array((int) $indicator->unit_id, $units, true) || (int) $indicator->owner_user_id === (int) $user->id;
    }
}
