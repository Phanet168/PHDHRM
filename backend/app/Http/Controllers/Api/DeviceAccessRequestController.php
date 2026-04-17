<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceAccessRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceAccessRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'machine_number' => ['required', 'string', 'max:255'],
            'device_summary' => ['nullable', 'string'],
            'device_info' => ['nullable', 'array'],
            'reason' => ['nullable', 'string'],
        ]);

        $existing = DeviceAccessRequest::where('email', $validated['email'])
            ->where('machine_number', $validated['machine_number'])
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'info',
                'message' => 'Device request already pending review.',
                'request_id' => $existing->id,
            ], 200);
        }

        $request_data = DeviceAccessRequest::create($validated);

        return response()->json([
            'status' => 'ok',
            'message' => 'Device access request submitted successfully.',
            'request_id' => $request_data->id,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DeviceAccessRequest::class);

        $query = DeviceAccessRequest::query();

        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('machine_number', 'like', "%{$search}%")
                    ->orWhere('device_summary', 'like', "%{$search}%");
            });
        }

        $requests = $query->latest()->paginate(20);

        return response()->json([
            'status' => 'ok',
            'data' => $requests,
        ]);
    }

    public function review(Request $request, DeviceAccessRequest $deviceAccessRequest): JsonResponse
    {
        $this->authorize('update', $deviceAccessRequest);

        $validated = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'admin_note' => ['nullable', 'string'],
        ]);

        $deviceAccessRequest->update([
            'status' => $validated['status'],
            'admin_note' => $validated['admin_note'] ?? null,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'status' => 'ok',
            'message' => 'Device access request ' . $validated['status'] . ' successfully.',
        ]);
    }
}
