<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('page.view');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->can('page.view');
    }

    public function create(User $user): bool
    {
        return $user->can('page.create');
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can('page.edit');
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->can('page.delete');
    }

    public function restore(User $user, Page $page): bool
    {
        return $user->can('page.restore');
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return $user->can('page.delete') && $user->can('page.restore');
    }

    public function submitReview(User $user, Page $page): bool
    {
        return $user->can('page.submit_review');
    }

    public function review(User $user, Page $page): bool
    {
        return $user->can('page.review');
    }

    public function approve(User $user, Page $page): bool
    {
        return $user->can('page.approve');
    }

    public function publish(User $user, Page $page): bool
    {
        return $user->can('page.publish');
    }

    public function unpublish(User $user, Page $page): bool
    {
        return $user->can('page.unpublish');
    }

    public function manageMenu(User $user, Page $page): bool
    {
        return $user->can('page.manage_menu');
    }

    public function manageMedia(User $user, Page $page): bool
    {
        return $user->can('page.manage_media');
    }

    public function useCustomHtml(User $user): bool
    {
        return $user->can('page.custom_html');
    }
}
