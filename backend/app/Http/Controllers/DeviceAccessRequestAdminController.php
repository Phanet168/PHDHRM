<?php

namespace App\Http\Controllers;

use App\Models\DeviceAccessRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class DeviceAccessRequestAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            if (auth()->user()->user_type_id != 1) {
                abort(403, 'Unauthorized');
            }
            return $next($request);
        });
    }

    public function index(Request $request): View
    {
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
        $statuses = ['pending', 'approved', 'rejected'];

        return view('backend.device-access-requests.index', [
            'requests' => $requests,
            'statuses' => $statuses,
            'currentStatus' => $request->status ?? '',
            'searchTerm' => $request->search ?? '',
        ]);
    }

    public function review(Request $request, DeviceAccessRequest $deviceAccessRequest): RedirectResponse
    {
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

        return back()->with('success', 'Device access request ' . $validated['status'] . ' successfully.');
    }
}
