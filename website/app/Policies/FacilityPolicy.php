<?php

namespace App\Policies;

use App\Models\Facility;
use App\Models\User;

class FacilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('facilities.view');
    }

    public function view(User $user, Facility $facility): bool
    {
        return $user->can('facilities.view') && $this->inScope($user, $facility);
    }

    public function create(User $user): bool
    {
        return $user->can('facilities.create') && ! $user->isFacilityScoped();
    }

    public function update(User $user, Facility $facility): bool
    {
        return $user->can('facilities.update') && $this->inScope($user, $facility);
    }

    public function delete(User $user, Facility $facility): bool
    {
        return $user->can('facilities.delete') && ! $user->isFacilityScoped();
    }

    public function publish(User $user, Facility $facility): bool
    {
        return $user->can('facilities.publish') && $this->inScope($user, $facility);
    }

    private function inScope(User $user, Facility $facility): bool
    {
        if (! $user->isFacilityScoped()) {
            return true;
        }

        return $user->facility_id === $facility->id;
    }
}
