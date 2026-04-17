<?php

namespace Modules\UserManagement\Http\Controllers;

use App\Models\MobileDeviceRegistration;
use App\Models\User;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Intervention\Image\Facades\Image;
use Modules\UserManagement\Entities\UserType;
use Modules\UserManagement\Http\DataTables\UserListDataTable;
use Modules\UserManagement\Http\Requests\PasswordChangeRequest;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:read_user_list')->only('userList', 'index');
        $this->middleware('permission:create_user_list')->only('userCreate', 'userStore');
        $this->middleware('permission:update_user_list')->only('userEdit', 'userUpdate', 'userDeviceStore', 'userDeviceStatus', 'userDeviceDelete');
        $this->middleware('permission:delete_user_list')->only('userDelete');

    }

    /**
     * Display a listing of the resource.
     * @return Renderable
     */
    public function index()
    {
        return view('usermanagement::index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create()
    {
        return view('usermanagement::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return Renderable
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function show($id)
    {
        return view('usermanagement::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id)
    {
        return view('usermanagement::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return Renderable
     */
    public function update(Request $request)
    {
        DB::beginTransaction();
        try {
            $id = Auth::user()->id;
            $validator = Validator::make($request->all(), [
                'full_name' => 'required|string|max:191',
                'email' => [
                    'required',
                    'email',
                    'max:191',
                    Rule::unique('users', 'email')->ignore($id),
                ],
                'contact_no' => [
                    'nullable',
                    'max:30',
                    Rule::unique('users', 'contact_no')->ignore($id),
                ],
                'signature' => 'nullable|file|mimes:jpeg,png,jpg,gif,svg,pdf|max:5120',
                'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation Error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $profileUpdate = User::with('employee')->findOrFail($id);
            $profileUpdate->full_name = $request->full_name;

            $profileUpdate->email = $request->email;
            if ($request->filled('contact_no')) {
                $profileUpdate->contact_no = $request->contact_no;
            }

            if ($request->hasFile('signature')) {
                $destination = public_path('storage/' . $profileUpdate->signature ?? null);

                if ($profileUpdate->signature != null && file_exists($destination)) {
                    unlink($destination);
                }

                $request_file = $request->file('signature');
                $name = time() . '.' . $request_file->getClientOriginalExtension();
                $path = Storage::disk('public')->putFileAs('signature', $request_file, $name);
                Image::make($request_file)->save(public_path('storage/' . $path));
                $profileUpdate->signature = $path;
            }

            if ($request->hasFile('profile_image')) {
                $destination = public_path('storage/' . $profileUpdate->profile_image ?? null);

                if ($profileUpdate->profile_image != null && file_exists($destination)) {
                    unlink($destination);
                }

                $request_file = $request->file('profile_image');
                $name = time() . rand(10, 1000) . '.' . $request_file->getClientOriginalExtension();
                $path = Storage::disk('public')->putFileAs('users', $request_file, $name);
                Image::make($request_file)->save(public_path('storage/' . $path));
                $profileUpdate->profile_image = $path;
            }

            $profileUpdate->save();

            $updateEmployee = $profileUpdate->employee;
            if ($updateEmployee) {
                $fullName = trim((string) $request->full_name);
                if ($fullName !== '') {
                    $nameParts = preg_split('/\s+/u', $fullName, -1, PREG_SPLIT_NO_EMPTY);
                    $updateEmployee->first_name = $nameParts[0] ?? $updateEmployee->first_name;
                    $updateEmployee->last_name = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : ($updateEmployee->last_name ?? '');
                }

                $updateEmployee->email = $request->email;
                if ($request->filled('contact_no')) {
                    $updateEmployee->phone = $request->contact_no;
                }

                if ($request->hasFile('signature')) {
                    $destination = public_path('storage/' . $updateEmployee->signature ?? null);

                    if ($updateEmployee->signature != null && file_exists($destination)) {
                        unlink($destination);
                    }

                    $request_file = $request->file('signature');
                    $name = time() . rand(10, 1000) . '.' . $request_file->getClientOriginalExtension();
                    $path = Storage::disk('public')->putFileAs('signature', $request_file, $name);
                    Image::make($request_file)->save(public_path('storage/' . $path));
                    $updateEmployee->signature = $path;
                }

                if ($request->hasFile('profile_image')) {
                    $destination = public_path('storage/employee/' . $updateEmployee->profile_img_name ?? null);

                    if ($updateEmployee->profile_img_name != null && file_exists($destination)) {
                        unlink($destination);
                    }

                    $request_file = $request->file('profile_image');
                    $filename = time() . rand(10, 1000) . '.' . $request_file->extension();
                    $path = $request_file->storeAs('employee', $filename, 'public');
                    $updateEmployee->profile_img_name = $filename;
                    $updateEmployee->profile_img_location = $path;
                }
                $updateEmployee->save();
            }

            DB::commit();
            $route = route('myProfile');
            return response()->json(['message' => localize('profile_updated'), 'route' => $route]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Profile update failed', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Something went wrong',
                    'error' => $e->getMessage(),
                ], 500);
            }

            activity()
                ->causedBy(auth()->user()) // The user causing the activity
                ->log('An error occurred: ' . $e->getMessage());
            Toastr::error('Something went wrong :)', 'Errors');
            return redirect()->back()->with('error', 'Something went wrong');
        }
    }

    /**
     * Remove the specified resource from storage.
     * @param int $id
     * @return Renderable
     */
    public function destroy($id)
    {
        //
    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function profilePictureUpdate(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $profileUpdate = User::with('employee')->findOrFail($id);
            if ($request->hasFile('profile_image')) {

                $destination = public_path('storage/' . $profileUpdate->profile_image ?? null);

                if ($profileUpdate->profile_image != null && file_exists($destination)) {
                    unlink($destination);
                }

                $request_file = $request->file('profile_image');
                $name = time() . '.' . $request_file->getClientOriginalExtension();
                $path = Storage::disk('public')->putFileAs('users', $request_file, $name);
                Image::make($request_file)->save(public_path('storage/' . $path));
                $profileUpdate->profile_image = $path;

            }
            $profileUpdate->save();

            $updateEmployee = $profileUpdate->employee;

            if ($updateEmployee) {
                $request_file = $request->file('profile_image');
                $name = time() . '.' . $request_file->getClientOriginalExtension();
                $path = Storage::disk('public')->putFileAs('users', $request_file, $name);
                Image::make($request_file)->save(public_path('storage/' . $path));
                $profileUpdate->profile_img_name = $name;
                $profileUpdate->profile_img_location = $path;
                $updateEmployee->update();
            }

            DB::commit();
            return response()->json(['message' => localize('profile_updated')]);

        } catch (\Exception $e) {
            DB::rollback();
            activity()
                ->causedBy(auth()->user()) // The user causing the activity
                ->log('An error occurred: ' . $e->getMessage());
            Toastr::error('Something went wrong :)', 'Errors');
            return redirect()->back()->with('error', 'Something went wrong');
        }
    }
    public function coverImageUpdate(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $profileUpdate = User::findOrFail($id);
            if ($request->hasFile('cover_image')) {

                $destination = public_path('storage/' . $profileUpdate->cover_image ?? null);

                if ($profileUpdate->cover_image != null && file_exists($destination)) {
                    unlink($destination);
                }

                $request_file = $request->file('cover_image');
                $name = time() . '.' . $request_file->getClientOriginalExtension();
                $path = Storage::disk('public')->putFileAs('users', $request_file, $name);
                Image::make($request_file)->save(public_path('storage/' . $path));
                $profileUpdate->cover_image = $path;
            }
            $profileUpdate->save();

            DB::commit();
            return response()->json(['message' => localize('cover_image_updated')]);

        } catch (\Exception $e) {
            DB::rollback();
            activity()
                ->causedBy(auth()->user()) // The user causing the activity
                ->log('An error occurred: ' . $e->getMessage());
            Toastr::error('Something went wrong :)', 'Errors');
            return redirect()->back()->with('error', 'Something went wrong');
        }
    }

    public function updatePassword(PasswordChangeRequest $request)
    {
        if (Hash::check($request->current_password, Auth::user()->password)) {
            $user = User::find(Auth::id());
            $user->password = Hash::make($request->password);
            $user->save();

            return response()->json([
                'status' => true,
                'message' => localize('password_changed_message'),
            ]);
        } else {
            return response()->json([
                'status' => false,
                'message' => localize('old_password_message'),
            ]);
        }
    }

    //user list
    public function userList(UserListDataTable $dataTable)
    {
        $roleList = Role::all();
        $userTypes = UserType::where('is_active', true)->get();

        return $dataTable->render('usermanagement::user-management.user-list', compact('roleList', 'userTypes'));
    }

    //store user
    public function userStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required',
            'email' => 'required|email|unique:users,email',
            'contact_no' => 'required',
            'password' => 'required|min:6',
            'role_id' => 'required',
            'user_type_id' => 'required',
            'profile_image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ],
            [
                'full_name.required' => 'The full name field is required.',
                'email.required' => 'The email field is required.',
                'email.email' => 'The email must be a valid email address.',
                'email.unique' => 'The email has already been taken.',
                'contact_no.required' => 'The mobile field is required.',
                'user_type_id.required' => 'The User Type field is required.',
                'password.required' => 'The password field is required.',
                'password.min' => 'The password must be at least 6 characters.',
                'role_id.required' => 'The role field is required.',
                'profile_image.image' => 'The profile image must be an image.',
                'profile_image.mimes' => 'The profile image must be a file of type: jpeg, png, jpg, gif, svg.',
                'profile_image.max' => 'The profile image may not be greater than 2048 kilobytes.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ]);
        }

        $user = new User();
        $user->user_type_id = $request->user_type_id;
        $user->is_active = $request->status;
        $user->full_name = $request->full_name;
        $user->email = $request->email;
        $user->contact_no = $request->contact_no;

        if ($request->hasFile('profile_image')) {
            $request_file = $request->file('profile_image');
            $name = time() . '.' . $request_file->getClientOriginalExtension();
            if (!file_exists(public_path('storage/users'))) {
                mkdir(public_path('storage/users'), 0777, true);
            }
            $path = Storage::disk('public')->putFileAs('users', $request_file, $name);
            Image::make($request_file)->save(public_path('storage/' . $path));
            $user->profile_image = $path;
        }
        $user->password = Hash::make($request->password);
        $user->save();
        $user->assignRole($request->role_id);

        return response()->json(['status' => 'success', 'message' => 'User Created Successfully']);
    }

    //edit user
    public function userEdit(User $user)
    {
        $user = User::with([
            'userRole',
            'mobileDeviceRegistrations' => function ($query) {
                $query->latest('created_at');
            },
            'mobileDeviceRegistrations.approver',
            'mobileDeviceRegistrations.blocker',
            'mobileDeviceRegistrations.rejecter',
        ])->findOrFail($user->id);

        $mobileDevices = $user->mobileDeviceRegistrations;
        $roleList = Role::all();
        $userTypes = UserType::where('is_active', true)->get();
        return response()->view('usermanagement::user-management.user-edit', compact('user', 'roleList', 'userTypes', 'mobileDevices'));
    }

    //update user
    public function userUpdate(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'full_name' => 'required',
                'email' => 'required|email|unique:users,email,' . $request->id,
                'contact_no' => 'required',
                'user_type_id' => 'required',
                'role_id' => 'required',
                'password' => 'nullable|min:6',
                'profile_image' => 'image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            ], [
                'role_id.required' => 'The Role field is required.',
                'full_name.required' => 'The full name field is required.',
                'email.required' => 'The email field is required.',
                'email.email' => 'The email must be a valid email address.',
                'email.unique' => 'The email has already been taken.',
                'contact_no.required' => 'The mobile field is required.',
                'user_type_id.required' => 'The User Type field is required.',
                'password.min' => 'The password must be at least 6 characters.',
                'profile_image.image' => 'The profile image must be an image.',
                'profile_image.mimes' => 'The profile image must be a file of type: jpeg, png, jpg, gif, svg.',
                'profile_image.max' => 'The profile image may not be greater than 2048 kilobytes.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation Error',
                    'errors' => $validator->errors(),
                ]);
            }
            $user = User::with(['userRole', 'employee'])->findOrFail($request->id);
            $user->full_name = $request->full_name;
            $user->user_type_id = $request->user_type_id;
            $user->is_active = $request->status;
            $user->email = $request->email;
            $user->contact_no = $request->contact_no;

            if ($request->password != null) {
                $user->password = Hash::make($request->password);
            }

            if ($request->hasFile('profile_image')) {

                $destination = public_path('storage/' . $user->profile_image ?? null);

                if ($user->profile_image != null && file_exists($destination)) {
                    unlink($destination);
                }

                $request_file = $request->file('profile_image');
                $name = time() . '.' . $request_file->getClientOriginalExtension();
                //create folder if not exists
                if (!file_exists(public_path('storage/users'))) {
                    mkdir(public_path('storage/users'), 0777, true);
                }
                $path = Storage::disk('public')->putFileAs('users', $request_file, $name);
                Image::make($request_file)->save(public_path('storage/' . $path));
                $user->profile_image = $path;
            }

            $user->save();

            $updateEmployee = $user->employee;
            if ($updateEmployee != null) {
                $fullName = explode(' ', $request->full_name);
                $updateEmployee->first_name = $fullName[0];
                $updateEmployee->last_name = $fullName[1] ?? '';
                $updateEmployee->email = $request->email;
                $updateEmployee->phone = $request->contact_no;

                if ($request->hasFile('profile_image')) {
                    $destination = public_path('storage/employee/' . $updateEmployee->profile_img_name ?? null);

                    if ($updateEmployee->profile_img_name != null && file_exists($destination)) {
                        unlink($destination);
                    }

                    $request_file = $request->file('profile_image');
                    $filename = time() . rand(10, 1000) . '.' . $request_file->extension();
                    $path = $request_file->storeAs('employee', $filename, 'public');
                    $updateEmployee->profile_img_name = $filename;
                    $updateEmployee->profile_img_location = $path;
                }
                $updateEmployee->save();
            }

            foreach ($user->userRole as $role) {
                $user->removeRole($role->id);
            }
            $user->assignRole($request->role_id);

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'User Updated Successfully']);

        } catch (\Exception $e) {
            DB::rollback();
            activity()
                ->causedBy(auth()->user()) // The user causing the activity
                ->log('An error occurred: ' . $e->getMessage());
            Toastr::error('Something went wrong :)', 'Errors');
            return redirect()->back()->with('error', 'Something went wrong');
        }
    }

    public function userDeviceStore(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => ['required', 'string', 'max:191'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'platform' => ['nullable', 'in:android,ios,web'],
            'imei' => ['nullable', 'string', 'max:50'],
            'fingerprint' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,blocked,pending,rejected'],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $deviceId = trim((string) $validated['device_id']);

        $alreadyExists = MobileDeviceRegistration::query()
            ->where('user_id', (int) $user->id)
            ->where('device_id', $deviceId)
            ->exists();

        if ($alreadyExists) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation Error',
                'errors' => [
                    'device_id' => ['This device ID is already registered for this user.'],
                ],
            ], 422);
        }

        $status = (string) $validated['status'];
        $payload = [
            'user_id' => (int) $user->id,
            'device_id' => $deviceId,
            'device_name' => trim((string) ($validated['device_name'] ?? '')) ?: null,
            'platform' => $validated['platform'] ?? null,
            'imei' => trim((string) ($validated['imei'] ?? '')) ?: null,
            'fingerprint' => trim((string) ($validated['fingerprint'] ?? '')) ?: null,
            'status' => $status,
            'register_ip' => $request->ip(),
            'register_ua' => substr((string) $request->userAgent(), 0, 500),
        ];

        if ($status === 'active') {
            $payload['approved_by'] = auth()->id();
            $payload['approved_at'] = now();
        } elseif ($status === 'blocked') {
            $payload['blocked_by'] = auth()->id();
            $payload['blocked_at'] = now();
        } elseif ($status === 'rejected') {
            $payload['rejected_by'] = auth()->id();
            $payload['rejected_at'] = now();
            $payload['rejection_reason'] = trim((string) ($validated['rejection_reason'] ?? '')) ?: null;
        }

        MobileDeviceRegistration::create($payload);

        return response()->json([
            'status' => 'success',
            'message' => localize('device_registered_success', 'Device has been registered successfully'),
        ]);
    }

    public function userDeviceStatus(Request $request, MobileDeviceRegistration $device)
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'in:active,blocked,pending,rejected'],
            'rejection_reason' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (isset($validated['user_id']) && (int) $validated['user_id'] !== (int) $device->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This device does not belong to the selected user.',
            ], 403);
        }

        $status = (string) $validated['status'];
        $updates = ['status' => $status];

        if ($status === 'active') {
            $updates = array_merge($updates, [
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'blocked_by' => null,
                'blocked_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
        } elseif ($status === 'blocked') {
            $updates = array_merge($updates, [
                'blocked_by' => auth()->id(),
                'blocked_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
            $this->revokeUserDeviceTokens($device);
        } elseif ($status === 'rejected') {
            $updates = array_merge($updates, [
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                'rejection_reason' => trim((string) ($validated['rejection_reason'] ?? '')) ?: null,
                'approved_by' => null,
                'approved_at' => null,
                'blocked_by' => null,
                'blocked_at' => null,
            ]);
            $this->revokeUserDeviceTokens($device);
        } else {
            $updates = array_merge($updates, [
                'approved_by' => null,
                'approved_at' => null,
                'blocked_by' => null,
                'blocked_at' => null,
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ]);
        }

        $device->update($updates);

        return response()->json([
            'status' => 'success',
            'message' => localize('device_status_updated', 'Device status updated successfully'),
        ]);
    }

    public function userDeviceDelete(Request $request, MobileDeviceRegistration $device)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation Error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (isset($validated['user_id']) && (int) $validated['user_id'] !== (int) $device->user_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'This device does not belong to the selected user.',
            ], 403);
        }

        $this->revokeUserDeviceTokens($device);
        $device->delete();

        return response()->json([
            'status' => 'success',
            'message' => localize('device_deleted_success', 'Device has been deleted successfully'),
        ]);
    }

    //delete user
    public function userDelete(Request $request)
    {
        $user = User::with('userRole')->findOrFail($request->id);

        // Prevent accidental self-deletion (this caused admin lockout before).
        if ((int) auth()->id() === (int) $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot delete the currently logged-in account.',
            ], 422);
        }

        foreach ($user->userRole as $role) {
            $user->removeRole($role->id);
        }

        $destination = public_path('storage/' . $user->profile_image ?? null);
        if ($user->profile_image != null && file_exists($destination)) {
            unlink($destination);
        }

        $user->delete();

        return response()->json(['status' => 'success', 'message' => 'User Deleted Successfully']);
    }

    //get user by ajax for select2 when search
    public function getUserByAjax(Request $request)
    {
        $data = User::where('full_name', 'LIKE', '%' . $request->input('term', '') . '%')->take(100)->get(['id', 'full_name as text']);
        //append one more data in first position

        $data->prepend(['id' => 0, 'text' => 'All']);
        return ['results' => $data];
    }

    private function revokeUserDeviceTokens(MobileDeviceRegistration $device): void
    {
        $user = $device->user;
        if ($user && $device->device_name) {
            $user->tokens()->where('name', $device->device_name)->delete();
        }
    }

}
