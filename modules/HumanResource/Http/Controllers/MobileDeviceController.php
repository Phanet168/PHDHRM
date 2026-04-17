<?php

namespace Modules\HumanResource\Http\Controllers;

use App\Models\MobileDeviceRegistration;
use App\Models\User;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\HumanResource\Support\OrgScopeService;

class MobileDeviceController extends \App\Http\Controllers\Controller
{
    private function scopedQuery(OrgScopeService $orgScopeService)
    {
        $query = MobileDeviceRegistration::with(['user.employee']);

        $accessibleIds = $orgScopeService->accessibleDepartmentIds();
        if ($accessibleIds !== null) {
            $query->whereHas('user.employee', function ($q) use ($accessibleIds) {
                $q->where(function ($inner) use ($accessibleIds) {
                    $inner->whereIn('department_id', $accessibleIds)
                          ->orWhereIn('sub_department_id', $accessibleIds);
                });
            });
        }

        return $query;
    }

    public function index(Request $request, OrgScopeService $orgScopeService)
    {
        $tab    = $request->input('tab', 'pending');
        $search = $request->input('search');

        $query = $this->scopedQuery($orgScopeService)
            ->where('status', $tab)
            ->latest('created_at');

        if ($search) {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('full_name', 'like', "%{$search}%");
            });
        }

        $devices = $query->paginate(20);
        $devices->appends($request->query());

        $counts = [];
        foreach (['pending', 'active', 'blocked', 'rejected'] as $s) {
            $counts[$s] = (clone $this->scopedQuery($orgScopeService))->where('status', $s)->count();
        }

        return view('humanresource::mobile-device.index', compact('devices', 'tab', 'counts', 'search'));
    }

    public function store(Request $request, OrgScopeService $orgScopeService): RedirectResponse
    {
        $validated = $request->validate([
            'user_email' => ['required', 'email'],
            'device_id' => ['required', 'string', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'platform' => ['nullable', 'in:android,ios,web'],
            'imei' => ['nullable', 'string', 'max:50'],
            'fingerprint' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,blocked,pending,rejected'],
        ]);

        $user = User::query()->where('email', trim((string) $validated['user_email']))->first();
        if (!$user) {
            return back()->withErrors(['user_email' => localize('user_not_found', 'User not found')])->withInput();
        }

        $this->ensureUserIsAccessible($user, $orgScopeService);

        $device = MobileDeviceRegistration::query()->where('user_id', (int) $user->id)
            ->where('device_id', (string) $validated['device_id'])
            ->first();

        if ($device) {
            return back()->withErrors(['device_id' => localize('device_id_already_registered_for_user', 'This device ID is already registered for the user')])->withInput();
        }

        MobileDeviceRegistration::create([
            'user_id' => (int) $user->id,
            'device_id' => trim((string) $validated['device_id']),
            'device_name' => trim((string) ($validated['device_name'] ?? '')) ?: null,
            'platform' => $validated['platform'] ?? null,
            'imei' => trim((string) ($validated['imei'] ?? '')) ?: null,
            'fingerprint' => trim((string) ($validated['fingerprint'] ?? '')) ?: null,
            'status' => $validated['status'],
            'approved_by' => $validated['status'] === 'active' ? auth()->id() : null,
            'approved_at' => $validated['status'] === 'active' ? now() : null,
            'blocked_by' => $validated['status'] === 'blocked' ? auth()->id() : null,
            'blocked_at' => $validated['status'] === 'blocked' ? now() : null,
            'register_ip' => $request->ip(),
            'register_ua' => substr((string) $request->userAgent(), 0, 500),
        ]);

        Toastr::success(localize('device_registered_success', 'Device has been registered successfully'));
        return redirect()->route('mobile-devices.index', ['tab' => $validated['status'] === 'rejected' ? 'rejected' : ($validated['status'] === 'blocked' ? 'blocked' : ($validated['status'] === 'pending' ? 'pending' : 'active'))]);
    }

    public function approve(Request $request, MobileDeviceRegistration $device): RedirectResponse
    {
        if ($device->isActive()) {
            Toastr::warning(localize('device_already_active', 'ឧបករណ៍នេះសកម្មរួចហើយ'));
            return back();
        }

        $device->update([
            'status'           => 'active',
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
            'rejected_by'      => null,
            'rejected_at'      => null,
            'rejection_reason' => null,
        ]);

        Toastr::success(localize('device_approved_success', 'ឧបករណ៍ត្រូវបានអនុម័តដោយជោគជ័យ'));
        return back();
    }

    public function reject(Request $request, MobileDeviceRegistration $device): RedirectResponse
    {
        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $this->revokeTokens($device);

        $device->update([
            'status'           => 'rejected',
            'rejected_by'      => auth()->id(),
            'rejected_at'      => now(),
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            'approved_by'      => null,
            'approved_at'      => null,
        ]);

        Toastr::success(localize('device_rejected_success', 'ឧបករណ៍ត្រូវបានច្រានចោលដោយជោគជ័យ'));
        return back();
    }

    public function block(Request $request, MobileDeviceRegistration $device): RedirectResponse
    {
        if ($device->isBlocked()) {
            Toastr::warning(localize('device_already_blocked', 'ឧបករណ៍នេះត្រូវបានបិទរួចហើយ'));
            return back();
        }

        $this->revokeTokens($device);

        $device->update([
            'status'     => 'blocked',
            'blocked_by' => auth()->id(),
            'blocked_at' => now(),
        ]);

        Toastr::success(localize('device_blocked_success', 'ឧបករណ៍ត្រូវបានបិទដោយជោគជ័យ'));
        return back();
    }

    public function unblock(MobileDeviceRegistration $device): RedirectResponse
    {
        if (!$device->isBlocked()) {
            Toastr::warning(localize('device_not_blocked', 'ឧបករណ៍នេះមិនត្រូវបានបិទទេ'));
            return back();
        }

        $device->update([
            'status'     => 'active',
            'blocked_by' => null,
            'blocked_at' => null,
        ]);

        Toastr::success(localize('device_unblocked_success', 'ឧបករណ៍ត្រូវបានបើកដោយជោគជ័យ'));
        return back();
    }

    public function destroy(MobileDeviceRegistration $device): RedirectResponse
    {
        $this->revokeTokens($device);
        $device->delete();

        Toastr::success(localize('device_deleted_success', 'ឧបករណ៍ត្រូវបានលុបដោយជោគជ័យ'));
        return back();
    }

    private function revokeTokens(MobileDeviceRegistration $device): void
    {
        $user = $device->user;
        if ($user && $device->device_name) {
            $user->tokens()->where('name', $device->device_name)->delete();
        }
    }

    private function ensureUserIsAccessible(User $user, OrgScopeService $orgScopeService): void
    {
        $accessibleIds = $orgScopeService->accessibleDepartmentIds();
        if ($accessibleIds === null) {
            return;
        }

        $employee = $user->employee;
        $allowed = $employee && (
            in_array((int) ($employee->department_id ?? 0), $accessibleIds, true)
            || in_array((int) ($employee->sub_department_id ?? 0), $accessibleIds, true)
        );

        if (!$allowed) {
            abort(403, 'You are not allowed to register device for this user.');
        }
    }
}
