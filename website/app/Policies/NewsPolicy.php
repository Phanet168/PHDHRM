<?php

namespace App\Policies;

use App\Models\News;
use App\Models\User;

class NewsPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('news.view');
    }

    public function view(User $user, News $news): bool
    {
        return $user->can('news.view') && $this->inScope($user, $news);
    }

    public function create(User $user): bool
    {
        return $user->can('news.create');
    }

    public function update(User $user, News $news): bool
    {
        return $user->can('news.update') && $this->inScope($user, $news);
    }

    public function delete(User $user, News $news): bool
    {
        return $user->can('news.delete') && $this->inScope($user, $news);
    }

    public function restore(User $user, News $news): bool
    {
        return $user->can('news.restore') && $this->inScope($user, $news);
    }

    public function forceDelete(User $user, News $news): bool
    {
        return $user->can('news.delete') && $user->can('news.restore');
    }

    public function submit(User $user, News $news): bool
    {
        return $user->can('news.submit') && $this->inScope($user, $news);
    }

    public function approve(User $user, News $news): bool
    {
        return $user->can('news.approve');
    }

    public function publish(User $user, News $news): bool
    {
        return $user->can('news.publish');
    }

    private function inScope(User $user, News $news): bool
    {
        if (! $user->isFacilityScoped()) {
            return true;
        }

        return $news->facility_id !== null && $news->facility_id === $user->facility_id;
    }
}
