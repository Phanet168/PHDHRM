<?php

namespace Modules\Pharmaceutical\Traits;

use Illuminate\Support\Facades\Auth;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Support\OrgScopeService;

/**
 * Provides department-based scope helpers for the Pharmaceutical module.
 *
 * Delegates to the shared OrgScopeService for consistency across modules.
 */
trait PharmScope
{
    protected function pharmLevel(): string
    {
        return app(OrgScopeService::class)->userLevel(Auth::user());
    }

    protected function pharmAccessibleDepartmentIds(): ?array
    {
        return app(OrgScopeService::class)->accessibleDepartmentIds(Auth::user());
    }

    protected function pharmCanDispense(): bool
    {
        if (Auth::check() && (int) Auth::user()->user_type_id === 1) {
            return true;
        }
        return in_array($this->pharmLevel(), ['hospital', 'hc'], true);
    }

    protected function pharmCanDistribute(): bool
    {
        if (Auth::check() && (int) Auth::user()->user_type_id === 1) {
            return true;
        }
        return in_array($this->pharmLevel(), ['phd', 'od'], true);
    }

    protected function pharmCanReview(): bool
    {
        if (Auth::check() && (int) Auth::user()->user_type_id === 1) {
            return true;
        }
        return in_array($this->pharmLevel(), ['phd', 'od'], true);
    }

    protected function pharmUserDepartment(): ?Department
    {
        return app(OrgScopeService::class)->userDepartment(Auth::user());
    }

    protected function pharmLevelLabel(): string
    {
        return app(OrgScopeService::class)->levelLabel(Auth::user());
    }
}
