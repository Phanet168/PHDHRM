<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Http\JsonResponse;

/**
 * Phase 2: read-only backend for the future Access Control Center's
 * "Effective Access" screen (section 11/13). No UI is built here -- this is
 * a data endpoint an admin screen can consume later, gated behind the same
 * permission (`read_user_list`) already used to view the user list/profile
 * in modules/UserManagement, so it does not introduce a new access rule.
 */
class AccessControlAdminController extends Controller
{
    public function __construct(private readonly AccessControlService $accessControlService)
    {
        $this->middleware('permission:read_user_list');
    }

    public function effectiveAccess(User $user): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => $this->accessControlService->effectiveAccess($user),
        ]);
    }
}
