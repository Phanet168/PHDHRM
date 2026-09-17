<?php

namespace Modules\HumanResource\Support;

use Illuminate\Database\Eloquent\Builder;
use Modules\HumanResource\Entities\Employee;
use Modules\HumanResource\Entities\Mission;
use Modules\HumanResource\Entities\SystemRole;
use Modules\HumanResource\Enums\Mission\MissionCreationPath;
use Modules\HumanResource\Enums\Mission\MissionStatus;

class MissionAccess
{
    public function __construct(
        private readonly OrgHierarchyAccessService $hierarchy,
    ) {
    }

    public function can(string $permission): bool
    {
        $user = auth()->user();

        return $user && ($this->hierarchy->isSystemAdmin($user) || $user->can($permission));
    }

    public function employeeId(): ?int
    {
        $key = '_mission_employee_id_'.auth()->id();
        if (! request()->attributes->has($key)) {
            request()->attributes->set($key, Employee::where('user_id', auth()->id())->where('is_active', 1)->value('id'));
        }

        return request()->attributes->get($key);
    }

    public function employees(): Builder
    {
        $ids = $this->hierarchy->managedBranchIds(auth()->user());

        return Employee::query()->when($ids !== null, fn ($q) => $q->whereIn(
            \Illuminate\Support\Facades\DB::raw('COALESCE(NULLIF(employees.sub_department_id, 0), employees.department_id)'), $ids
        ));
    }

    public function managed(): Builder
    {
        if ($this->hierarchy->isSystemAdmin(auth()->user())) {
            return Mission::query();
        }
        $allowed = $this->employees()->select('employees.id');

        // A manager must control the whole team, including cancelled assignments.
        return Mission::query()->whereDoesntHave('assignments', fn ($q) => $q->whereNotIn('employee_id', $allowed))
            ->where(fn ($q) => $q->whereHas('assignments')->orWhere('requested_by', auth()->id()));
    }

    public function visible(bool $mine = false): Builder
    {
        $employeeId = $this->employeeId();

        return Mission::query()->where(function ($q) use ($employeeId, $mine) {
            $q->where('requested_by', auth()->id())
                ->orWhere(fn ($own) => $own->where('status', '!=', 'draft')->whereHas('assignments', fn ($a) => $a
                    ->where('employee_id', $employeeId ?? 0)->where('status', 'active')));
            if (! $mine && ($this->can('read_mission') || $this->can('create_mission') || $this->can('approve_mission'))) {
                $q->orWhereIn('id', $this->managed()->select('missions.id'));
            }
        });
    }

    public function manage(Mission $mission, string $permission = 'update_mission'): void
    {
        abort_unless($this->can($permission) && $this->managed()->whereKey($mission->id)->exists(), 403);
    }

    public function participant(Mission $mission): bool
    {
        $id = $this->employeeId();

        if ($mission->relationLoaded('assignments')) {
            return $id && $mission->assignments->contains(fn ($a) => (int) $a->employee_id === $id && $a->status === 'active');
        }

        return $id && $mission->assignments()->where('employee_id', $id)->where('status', 'active')->exists();
    }

    /** Flow A requests submitted by the current employee/user. */
    public function myRequests(): Builder
    {
        $employeeId = $this->employeeId();

        return Mission::query()
            ->where('creation_path', MissionCreationPath::EmployeeRequest->value)
            ->where(function (Builder $q) use ($employeeId) {
                $q->where('requested_by', auth()->id());
                if ($employeeId) {
                    $q->orWhere('requester_employee_id', $employeeId);
                }
            });
    }

    /** Requests waiting on the current user's office-head endorsement. */
    public function officeHeadQueue(): Builder
    {
        return $this->queueByResponsibility(SystemRole::CODE_MANAGER, MissionStatus::PendingOfficeHead);
    }

    /** Requests waiting on the current user's director approval. */
    public function directorQueue(): Builder
    {
        return $this->queueByResponsibility(SystemRole::CODE_HEAD, MissionStatus::PendingDirector);
    }

    /** Director-approved requests now in (or moving through) the Letter Manager's queue. */
    public function approvedQueue(): Builder
    {
        return Mission::query()->whereIn('lifecycle_status', array_map(
            fn (MissionStatus $s) => $s->value,
            MissionStatus::approvedQueue()
        ));
    }

    protected function queueByResponsibility(string $responsibilityCode, MissionStatus $status): Builder
    {
        $query = Mission::query()->where('lifecycle_status', $status->value);

        if ($this->hierarchy->isSystemAdmin(auth()->user())) {
            return $query;
        }

        // This is a display-only convenience for queue listings -- the
        // authoritative per-mission gate remains
        // WorkflowActorResolverService::canUserActOnStep(). Phase 3B.1:
        // delegates to the shared, canonical UserAssignment-backed resolver
        // instead of re-implementing scope-type expansion here.
        $departmentIds = $this->hierarchy->departmentIdsForResponsibility(auth()->user(), $responsibilityCode);
        if ($departmentIds === null) {
            return $query;
        }
        if (empty($departmentIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('source_department_id', $departmentIds);
    }
}
