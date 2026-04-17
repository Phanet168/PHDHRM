<?php

namespace Modules\Correspondence\Traits;

use Illuminate\Support\Facades\Auth;
use Modules\HumanResource\Entities\Department;
use Modules\HumanResource\Support\OrgScopeService;

/**
 * 4-level department scope for Correspondence module.
 *
 * Delegates to the shared OrgScopeService for consistency across modules.
 */
trait CorrespondenceScope
{
    protected function corrLevel(): string
    {
        return app(OrgScopeService::class)->userLevel(Auth::user());
    }

    protected function corrAccessibleDepartmentIds(): ?array
    {
        return app(OrgScopeService::class)->accessibleDepartmentIds(Auth::user());
    }

    protected function corrLevelLabel(): string
    {
        return app(OrgScopeService::class)->levelLabel(Auth::user());
    }

    protected function corrUserDepartment(): ?Department
    {
        return app(OrgScopeService::class)->userDepartment(Auth::user());
    }
}
