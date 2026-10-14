<?php

namespace App\Policies;

use App\Models\HeroSlide;
use App\Models\User;

class HeroSlidePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('hero-slides.manage');
    }

    public function view(User $user, HeroSlide $heroSlide): bool
    {
        return $user->can('hero-slides.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('hero-slides.manage');
    }

    public function update(User $user, HeroSlide $heroSlide): bool
    {
        return $user->can('hero-slides.manage');
    }

    public function delete(User $user, HeroSlide $heroSlide): bool
    {
        return $user->can('hero-slides.manage');
    }
}
