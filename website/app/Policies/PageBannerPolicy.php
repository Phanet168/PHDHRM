<?php

namespace App\Policies;

use App\Models\PageBanner;
use App\Models\User;

class PageBannerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('page-banners.manage');
    }

    public function update(User $user, PageBanner $pageBanner): bool
    {
        return $user->can('page-banners.manage');
    }
}
