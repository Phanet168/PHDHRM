<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccessControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Additive read-only endpoint over the Phase 2 AccessControlService.
 *
 * Does not touch AuthController's existing /auth/login or /auth/profile
 * payloads -- this is a new, separate endpoint so existing Flutter builds
 * keep working unchanged. Future Flutter work can migrate to this instead of
 * deriving UI visibility from the free-text `role` string.
 */
class CapabilityController extends Controller
{
    public function __construct(private readonly AccessControlService $accessControlService)
    {
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'data' => $this->accessControlService->capabilities($request->user()),
        ]);
    }
}
