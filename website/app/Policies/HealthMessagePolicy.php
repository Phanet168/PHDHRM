<?php

namespace App\Policies;

use App\Models\HealthMessage;
use App\Models\User;

class HealthMessagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('health-messages.manage');
    }

    public function view(User $user, HealthMessage $healthMessage): bool
    {
        return $user->can('health-messages.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('health-messages.manage');
    }

    public function update(User $user, HealthMessage $healthMessage): bool
    {
        return $user->can('health-messages.manage');
    }

    public function delete(User $user, HealthMessage $healthMessage): bool
    {
        return $user->can('health-messages.manage');
    }
}
