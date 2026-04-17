<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\DeviceAccessRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid credentials.',
            ], 401);
        }

        // Check device access request approval
        $deviceRequest = DeviceAccessRequest::where('email', $validated['email'])
            ->where('machine_number', $validated['device_name'])
            ->first();

        if (!$deviceRequest) {
            return response()->json([
                'status' => 'error',
                'error_code' => 'device_request_not_found',
                'message' => 'This device has not been registered. Please submit a device access request.',
            ], 403);
        }

        if ($deviceRequest->status !== 'approved') {
            return response()->json([
                'status' => 'error',
                'error_code' => 'device_request_not_approved',
                'message' => 'Device access request is ' . $deviceRequest->status . '. Please wait for admin approval.',
                'request_id' => $deviceRequest->id,
                'request_status' => $deviceRequest->status,
                'admin_note' => $deviceRequest->admin_note,
            ], 403);
        }

        $tokenName = $validated['device_name'];
        $accessToken = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'status' => 'ok',
            'message' => 'Login successful.',
            'token_type' => 'Bearer',
            'access_token' => $accessToken,
            'user' => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'user_type_id' => $user->user_type_id,
            ],
            'request_id' => $deviceRequest->id,
        ]);
    }
}
