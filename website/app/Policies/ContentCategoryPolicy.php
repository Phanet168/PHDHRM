<?php

namespace App\Policies;

use App\Models\ContentCategory;
use App\Models\User;

class ContentCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.view');
    }

    public function view(User $user, ContentCategory $category): bool
    {
        return $user->can('content.view');
    }

    public function create(User $user): bool
    {
        return $user->can('content.manage_categories');
    }

    public function update(User $user, ContentCategory $category): bool
    {
        return $user->can('content.manage_categories');
    }

    public function delete(User $user, ContentCategory $category): bool
    {
        return $user->can('content.manage_categories');
    }
}
