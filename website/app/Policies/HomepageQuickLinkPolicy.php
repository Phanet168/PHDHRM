<?php

namespace App\Policies;

use App\Models\HomepageQuickLink;
use App\Models\User;

class HomepageQuickLinkPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('homepage-quick-links.manage');
    }

    public function view(User $user, HomepageQuickLink $homepageQuickLink): bool
    {
        return $user->can('homepage-quick-links.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('homepage-quick-links.manage');
    }

    public function update(User $user, HomepageQuickLink $homepageQuickLink): bool
    {
        return $user->can('homepage-quick-links.manage');
    }

    public function delete(User $user, HomepageQuickLink $homepageQuickLink): bool
    {
        return $user->can('homepage-quick-links.manage');
    }
}
