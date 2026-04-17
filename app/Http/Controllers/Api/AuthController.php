<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDeviceRegistration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $normalizedDeviceId = trim((string) ($request->input('device_id') ?? $request->input('deviceId') ?? ''));
        if ($normalizedDeviceId === '') {
            return response()->json([
                'status'  => 'error',
                'code'    => 'device_id_required',
                'message' => 'Device ID is required.',
            ], 422);
        }

        $request->merge([
            'device_id' => $normalizedDeviceId,
            'device_name' => $request->input('device_name') ?? $request->input('deviceName') ?? $request->input('device_model'),
            'platform' => $request->input('platform') ?? $request->input('device_platform'),
            'imei' => $request->input('imei') ?? $request->input('device_imei'),
            'fingerprint' => $request->input('fingerprint') ?? $request->input('device_fingerprint'),
        ]);

        $validated = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'device_id'   => ['required', 'string', 'max:191'],
            'platform'    => ['nullable', 'in:android,ios,web'],
            'imei'        => ['nullable', 'string', 'max:50'],
            'fingerprint' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // --- Device registration & approval gate ---
        $deviceId   = trim((string) ($validated['device_id'] ?? ''));
        $deviceName = trim((string) ($validated['device_name'] ?? 'flutter-app'));
        $platform   = $validated['platform'] ?? null;
        $imei       = trim((string) ($validated['imei'] ?? '')) ?: null;
        $fingerprint = trim((string) ($validated['fingerprint'] ?? '')) ?: null;

        $device = MobileDeviceRegistration::where('user_id', $user->id)
            ->where('device_id', $deviceId)
            ->first();

        // Anti-spoofing: if device is known, verify IMEI has not changed
        if ($device && $imei && $device->imei && $device->imei !== $imei) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'device_imei_mismatch',
                'message' => 'Device hardware mismatch detected. Please contact your administrator.',
            ], 403);
        }

        if ($device) {
            if ($device->isBlocked()) {
                return response()->json([
                    'status'  => 'error',
                    'code'    => 'device_blocked',
                    'message' => 'This device has been blocked. Please contact your administrator.',
                ], 403);
            }
            if ($device->isRejected()) {
                return response()->json([
                    'status'  => 'error',
                    'code'    => 'device_rejected',
                    'message' => 'Your device registration was rejected' . ($device->rejection_reason ? ': ' . $device->rejection_reason : '.') . ' Please contact your administrator.',
                ], 403);
            }
            if ($device->isPending()) {
                return response()->json([
                    'status'  => 'error',
                    'code'    => 'device_pending',
                    'message' => 'Your device is waiting for administrator approval. Please try again later.',
                ], 403);
            }

            // Active – update last_login
            $device->update([
                'device_name' => $deviceName ?: $device->device_name,
                'platform' => $platform ?: $device->platform,
                'last_login_at' => now(),
            ]);
        } else {
            return response()->json([
                'status'  => 'error',
                'code'    => 'device_not_registered',
                'message' => 'This device is not registered. Please contact your administrator.',
            ], 403);
        }
        // -------------------------------------------

        $tokenName   = $deviceName ?: 'flutter-app';
        $accessToken = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'status'       => 'ok',
            'message'      => 'Login successful.',
            'token_type'   => 'Bearer',
            'access_token' => $accessToken,
            'user'         => [
                'id'           => $user->id,
                'full_name'    => $user->full_name,
                'email'        => $user->email,
                'user_type_id' => $user->user_type_id,
            ],
        ]);
    }

    public function requestDeviceAccess(Request $request): JsonResponse
    {
        $normalizedDeviceId = trim((string) ($request->input('device_id') ?? $request->input('deviceId') ?? ''));
        if ($normalizedDeviceId === '') {
            return response()->json([
                'status'  => 'error',
                'code'    => 'device_id_required',
                'message' => 'Device ID is required.',
            ], 422);
        }

        $request->merge([
            'device_id' => $normalizedDeviceId,
            'device_name' => $request->input('device_name') ?? $request->input('deviceName') ?? $request->input('device_model'),
            'platform' => $request->input('platform') ?? $request->input('device_platform'),
            'imei' => $request->input('imei') ?? $request->input('device_imei'),
            'fingerprint' => $request->input('fingerprint') ?? $request->input('device_fingerprint'),
            'request_note' => $request->input('request_note') ?? $request->input('requestNote'),
        ]);

        $validated = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'device_id'   => ['required', 'string', 'max:191'],
            'platform'    => ['nullable', 'in:android,ios,web'],
            'imei'        => ['nullable', 'string', 'max:50'],
            'fingerprint' => ['nullable', 'string', 'max:255'],
            'request_note' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $deviceId = trim((string) $validated['device_id']);
        $deviceName = trim((string) ($validated['device_name'] ?? '')) ?: null;
        $platform = $validated['platform'] ?? null;
        $imei = trim((string) ($validated['imei'] ?? '')) ?: null;
        $fingerprint = trim((string) ($validated['fingerprint'] ?? '')) ?: null;
        $requestNote = trim((string) ($validated['request_note'] ?? '')) ?: null;
        $requestRemark = $requestNote ? ('REQUEST_NOTE: ' . $requestNote) : null;

        $device = MobileDeviceRegistration::query()
            ->where('user_id', (int) $user->id)
            ->where('device_id', $deviceId)
            ->first();

        if ($device && $device->isActive()) {
            return response()->json([
                'status'  => 'ok',
                'code'    => 'device_already_approved',
                'message' => 'This device is already approved.',
            ]);
        }

        if ($device && $device->isBlocked()) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'device_blocked',
                'message' => 'This device has been blocked. Please contact your administrator.',
            ], 403);
        }

        if ($device) {
            $device->update([
                'device_name' => $deviceName ?? $device->device_name,
                'platform' => $platform ?? $device->platform,
                'imei' => $imei ?? $device->imei,
                'fingerprint' => $fingerprint ?? $device->fingerprint,
                'status' => 'pending',
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => $requestRemark,
                'approved_by' => null,
                'approved_at' => null,
                'register_ip' => $request->ip(),
                'register_ua' => substr((string) $request->userAgent(), 0, 500),
            ]);
        } else {
            MobileDeviceRegistration::create([
                'user_id' => (int) $user->id,
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'platform' => $platform,
                'imei' => $imei,
                'fingerprint' => $fingerprint,
                'status' => 'pending',
                'rejection_reason' => $requestRemark,
                'register_ip' => $request->ip(),
                'register_ua' => substr((string) $request->userAgent(), 0, 500),
            ]);
        }

        return response()->json([
            'status'  => 'ok',
            'code'    => 'device_request_submitted',
            'message' => 'Device request submitted. Please wait for administrator approval.',
        ]);
    }

    public function deviceRequestStatus(Request $request): JsonResponse
    {
        $normalizedDeviceId = trim((string) ($request->input('device_id') ?? $request->input('deviceId') ?? ''));
        if ($normalizedDeviceId === '') {
            return response()->json([
                'status'  => 'error',
                'code'    => 'device_id_required',
                'message' => 'Device ID is required.',
            ], 422);
        }

        $request->merge([
            'device_id' => $normalizedDeviceId,
        ]);

        $validated = $request->validate([
            'email'       => ['required', 'email'],
            'password'    => ['required', 'string'],
            'device_id'   => ['required', 'string', 'max:191'],
        ]);

        $user = User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        $device = MobileDeviceRegistration::query()
            ->where('user_id', (int) $user->id)
            ->where('device_id', trim((string) $validated['device_id']))
            ->first();

        if (!$device) {
            return response()->json([
                'status'  => 'error',
                'code'    => 'device_not_registered',
                'message' => 'This device is not registered. Please submit a request.',
                'device'  => null,
            ], 404);
        }

        return response()->json([
            'status' => 'ok',
            'code' => 'device_status',
            'message' => 'Device status retrieved successfully.',
            'device' => [
                'id' => (int) $device->id,
                'status' => (string) $device->status,
                'device_id' => (string) $device->device_id,
                'device_name' => $device->device_name,
                'platform' => $device->platform,
                'approved_at' => optional($device->approved_at)?->toDateTimeString(),
                'rejected_at' => optional($device->rejected_at)?->toDateTimeString(),
                'blocked_at' => optional($device->blocked_at)?->toDateTimeString(),
                'reason' => $device->rejection_reason,
            ],
        ]);
    }

    public function deviceHeartbeat(Request $request): JsonResponse
    {
        $normalizedDeviceId = trim((string) ($request->input('device_id') ?? $request->input('deviceId') ?? ''));
        if ($normalizedDeviceId === '') {
            return response()->json([
                'status'  => 'error',
                'code'    => 'device_id_required',
                'message' => 'Device ID is required.',
            ], 422);
        }

        $request->merge([
            'device_id' => $normalizedDeviceId,
            'device_name' => $request->input('device_name') ?? $request->input('deviceName') ?? $request->input('device_model'),
            'platform' => $request->input('platform') ?? $request->input('device_platform'),
        ]);

        $validated = $request->validate([
            'device_id' => ['required', 'string', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'platform' => ['nullable', 'in:android,ios,web'],
        ]);

        $user = $request->user();
        $device = MobileDeviceRegistration::query()
            ->where('user_id', (int) $user->id)
            ->where('device_id', trim((string) $validated['device_id']))
            ->first();

        if (!$device) {
            return response()->json([
                'status' => 'error',
                'code' => 'device_not_registered',
                'message' => 'This device is not registered.',
            ], 404);
        }

        if ($device->isBlocked()) {
            return response()->json([
                'status' => 'error',
                'code' => 'device_blocked',
                'message' => 'This device has been blocked. Please contact your administrator.',
            ], 403);
        }

        if ($device->isRejected()) {
            return response()->json([
                'status' => 'error',
                'code' => 'device_rejected',
                'message' => 'Your device registration was rejected. Please contact your administrator.',
            ], 403);
        }

        if ($device->isPending()) {
            return response()->json([
                'status' => 'error',
                'code' => 'device_pending',
                'message' => 'Your device is waiting for administrator approval.',
            ], 403);
        }

        $device->update([
            'device_name' => trim((string) ($validated['device_name'] ?? '')) ?: $device->device_name,
            'platform' => $validated['platform'] ?? $device->platform,
            'last_login_at' => now(),
        ]);

        return response()->json([
            'status' => 'ok',
            'code' => 'device_online',
            'message' => 'Device heartbeat received.',
            'last_seen_at' => optional($device->fresh()->last_login_at)?->toDateTimeString(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => 'ok',
            'message' => 'Logged out.',
        ]);
    }
}
