<?php

namespace App\Policies;

use App\Models\HealthContent;
use App\Models\User;

class HealthContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('health-content.view');
    }

    public function view(User $user, HealthContent $healthContent): bool
    {
        return $user->can('health-content.view');
    }

    public function create(User $user): bool
    {
        return $user->can('health-content.create');
    }

    public function update(User $user, HealthContent $healthContent): bool
    {
        return $user->can('health-content.update');
    }

    public function delete(User $user, HealthContent $healthContent): bool
    {
        return $user->can('health-content.delete');
    }

    public function submit(User $user, HealthContent $healthContent): bool
    {
        return $user->can('health-content.submit');
    }

    public function approve(User $user, HealthContent $healthContent): bool
    {
        return $user->can('health-content.approve');
    }

    public function publish(User $user, HealthContent $healthContent): bool
    {
        return $user->can('health-content.publish');
    }

    public function import(User $user): bool
    {
        return $user->can('health-content.import');
    }
}
