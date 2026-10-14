<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\AuditLog;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::with(['roles', 'facility'])->latest()->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        $roles = Role::orderBy('name')->pluck('name', 'name');
        $facilities = Facility::orderBy('name_km')->pluck('name_km', 'id');

        return view('admin.users.create', compact('roles', 'facilities'));
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'facility_id' => $data['facility_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $user->assignRole($data['role']);

        AuditLog::record('created', 'User', $user->id, "បង្កើតអ្នកប្រើប្រាស់៖ {$user->name}");

        return redirect()->route('admin.users.index')->with('status', 'បានបង្កើតអ្នកប្រើប្រាស់ដោយជោគជ័យ។');
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $roles = Role::orderBy('name')->pluck('name', 'name');
        $facilities = Facility::orderBy('name_km')->pluck('name_km', 'id');

        return view('admin.users.edit', compact('user', 'roles', 'facilities'));
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'facility_id' => $data['facility_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
            ...(! empty($data['password']) ? ['password' => Hash::make($data['password'])] : []),
        ]);

        $user->syncRoles([$data['role']]);

        AuditLog::record('updated', 'User', $user->id, "កែប្រែអ្នកប្រើប្រាស់៖ {$user->name}");

        return redirect()->route('admin.users.index')->with('status', 'បានរក្សាទុកដោយជោគជ័យ។');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        AuditLog::record('deleted', 'User', $user->id, "លុបអ្នកប្រើប្រាស់៖ {$user->name}");

        return redirect()->route('admin.users.index')->with('status', 'បានលុបអ្នកប្រើប្រាស់ដោយជោគជ័យ។');
    }
}
