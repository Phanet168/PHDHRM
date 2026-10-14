<?php

namespace App\Policies;

use App\Models\LeaderRecommendation;
use App\Models\User;

class LeaderRecommendationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('leader-recommendations.manage');
    }

    public function view(User $user, LeaderRecommendation $leaderRecommendation): bool
    {
        return $user->can('leader-recommendations.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('leader-recommendations.manage');
    }

    public function update(User $user, LeaderRecommendation $leaderRecommendation): bool
    {
        return $user->can('leader-recommendations.manage');
    }

    public function delete(User $user, LeaderRecommendation $leaderRecommendation): bool
    {
        return $user->can('leader-recommendations.manage');
    }
}
