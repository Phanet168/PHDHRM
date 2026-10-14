<?php

namespace App\Policies;

use App\Models\Content;
use App\Models\User;

class ContentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('content.view');
    }

    public function view(User $user, Content $content): bool
    {
        return $user->can('content.view');
    }

    public function create(User $user): bool
    {
        return $user->can('content.create');
    }

    public function update(User $user, Content $content): bool
    {
        return $user->can('content.edit');
    }

    public function delete(User $user, Content $content): bool
    {
        return $user->can('content.delete');
    }

    public function submitReview(User $user, Content $content): bool
    {
        return $user->can('content.submit_review');
    }

    public function approve(User $user, Content $content): bool
    {
        return $user->can('content.approve');
    }

    public function publish(User $user, Content $content): bool
    {
        return $user->can('content.publish');
    }

    public function manageGallery(User $user, Content $content): bool
    {
        return $user->can('content.manage_gallery');
    }

    public function useCustomHtml(User $user): bool
    {
        return $user->can('content.custom_html');
    }
}
