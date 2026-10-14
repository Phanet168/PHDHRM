<?php

namespace App\Policies;

use App\Models\MediaFolder;
use App\Models\User;

class MediaFolderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('media.manage') || $user->can('media.view');
    }

    public function create(User $user): bool
    {
        return $user->can('media.manage') || $user->can('media.folder.create');
    }

    public function update(User $user, MediaFolder $folder): bool
    {
        return $user->can('media.manage') || $user->can('media.folder.rename');
    }

    public function move(User $user, MediaFolder $folder): bool
    {
        return $user->can('media.manage') || $user->can('media.folder.move');
    }

    public function delete(User $user, MediaFolder $folder): bool
    {
        return $user->can('media.manage') || $user->can('media.folder.delete');
    }

    public function copy(User $user, MediaFolder $folder): bool
    {
        return $user->can('media.manage') || $user->can('media.copy');
    }

    public function restore(User $user, MediaFolder $folder): bool
    {
        return $user->can('media.manage') || $user->can('media.restore');
    }

    public function forceDelete(User $user, MediaFolder $folder): bool
    {
        return $this->restore($user, $folder);
    }
}
