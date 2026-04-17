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
            $trustedDevice = null;
            if ($imei || $fingerprint) {
                $trustedDevice = MobileDeviceRegistration::where('user_id', $user->id)
                    ->where(function ($q) use ($imei, $fingerprint) {
                        if ($imei) {
                            $q->orWhere('imei', $imei);
                        }
                        if ($fingerprint) {
                            $q->orWhere('fingerprint', $fingerprint);
                        }
                    })
                    ->latest('id')
                    ->first();
            }

            // If this is the same physical approved phone (e.g. app reinstall), reuse without new approval.
            if ($trustedDevice) {
                if ($trustedDevice->isBlocked()) {
                    return response()->json([
                        'status'  => 'error',
                        'code'    => 'device_blocked',
                        'message' => 'This device has been blocked. Please contact your administrator.',
                    ], 403);
                }
                if ($trustedDevice->isRejected()) {
                    return response()->json([
                        'status'  => 'error',
                        'code'    => 'device_rejected',
                        'message' => 'Your device registration was rejected' . ($trustedDevice->rejection_reason ? ': ' . $trustedDevice->rejection_reason : '.') . ' Please contact your administrator.',
                    ], 403);
                }
                if ($trustedDevice->isPending()) {
                    return response()->json([
                        'status'  => 'error',
                        'code'    => 'device_pending',
                        'message' => 'Your device is waiting for administrator approval. Please try again later.',
                    ], 403);
                }

                $trustedDevice->update([
                    'device_id' => $deviceId,
                    'device_name' => $deviceName ?: $trustedDevice->device_name,
                    'platform' => $platform ?: $trustedDevice->platform,
                    'imei' => $imei ?: $trustedDevice->imei,
                    'fingerprint' => $fingerprint ?: $trustedDevice->fingerprint,
                    'last_login_at' => now(),
                ]);
            } else {
            // New device – register as pending
                MobileDeviceRegistration::create([
                    'user_id'     => $user->id,
                    'device_id'   => $deviceId,
                    'device_name' => $deviceName ?: null,
                    'platform'    => $platform,
                    'imei'        => $imei,
                    'fingerprint' => $fingerprint,
                    'status'      => 'pending',
                    'register_ip' => $request->ip(),
                    'register_ua' => substr((string) $request->userAgent(), 0, 500),
                ]);

                return response()->json([
                    'status'  => 'error',
                    'code'    => 'device_pending',
                    'message' => 'Your device has been registered and is waiting for administrator approval.',
                ], 403);
            }
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

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => 'ok',
            'message' => 'Logged out.',
        ]);
    }
}
