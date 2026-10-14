<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('media.manage') || $user->can('media.view');
    }

    public function view(User $user, Media $media): bool
    {
        return $user->can('media.manage') || $user->can('media.view');
    }

    public function create(User $user): bool
    {
        return $user->can('media.manage') || $user->can('media.upload');
    }

    public function update(User $user, Media $media): bool
    {
        return $user->can('media.manage') || $user->can('media.edit');
    }

    public function move(User $user, Media $media): bool
    {
        return ($user->can('media.manage') || $user->can('media.move'))
            && (! $user->isFacilityScoped() || $media->uploaded_by === $user->id);
    }

    public function delete(User $user, Media $media): bool
    {
        return ($user->can('media.manage') || $user->can('media.delete'))
            && (! $user->isFacilityScoped() || $media->uploaded_by === $user->id);
    }

    public function copy(User $user, Media $media): bool
    {
        return $user->can('media.manage') || $user->can('media.copy');
    }

    public function restore(User $user, Media $media): bool
    {
        return ($user->can('media.manage') || $user->can('media.restore'))
            && (! $user->isFacilityScoped() || $media->uploaded_by === $user->id);
    }

    public function forceDelete(User $user, Media $media): bool
    {
        return $this->restore($user, $media);
    }
}
