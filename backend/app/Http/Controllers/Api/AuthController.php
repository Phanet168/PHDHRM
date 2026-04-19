<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileDeviceRegistration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\HumanResource\Entities\Employee;

class AuthController extends Controller
{
    public function sanctumUser(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function login(Request $request): JsonResponse
    {
        $normalizedDeviceId = trim((string) (
            $request->input('device_id')
            ?? $request->input('deviceId')
            ?? $request->input('device_name')
            ?? $request->input('deviceName')
            ?? $request->input('token_id')
            ?? ''
        ));
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
            'user'         => $this->buildUserProfilePayload($user),
        ]);
    }

    public function requestDeviceAccess(Request $request): JsonResponse
    {
        $normalizedDeviceId = trim((string) (
            $request->input('device_id')
            ?? $request->input('deviceId')
            ?? $request->input('device_name')
            ?? $request->input('deviceName')
            ?? $request->input('token_id')
            ?? ''
        ));
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
        $normalizedDeviceId = trim((string) (
            $request->input('device_id')
            ?? $request->input('deviceId')
            ?? $request->input('device_name')
            ?? $request->input('deviceName')
            ?? $request->input('token_id')
            ?? ''
        ));
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
        $normalizedDeviceId = trim((string) (
            $request->input('device_id')
            ?? $request->input('deviceId')
            ?? $request->input('device_name')
            ?? $request->input('deviceName')
            ?? $request->input('token_id')
            ?? ''
        ));
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

    public function profile(Request $request): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'code' => 'employee_profile',
            'message' => 'Employee profile retrieved successfully.',
            'user' => $this->buildUserProfilePayload($request->user()),
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

    private function buildUserProfilePayload(User $user): array
    {
        $user->loadMissing([
            'employee.department',
            'employee.sub_department',
            'employee.position',
            'employee.gender',
            'employee.marital_status',
            'employee.employee_type',
            'employee.currentPayGradeHistory.payLevel',
            'employee.latestPayGradeHistory.payLevel',
            'employee.profileExtra',
        ]);

        /** @var Employee|null $employee */
        $employee = $user->employee;
        if (!$employee) {
            $employee = Employee::query()
                ->with([
                    'department',
                    'sub_department',
                    'position',
                    'gender',
                    'marital_status',
                    'employee_type',
                    'currentPayGradeHistory.payLevel',
                    'latestPayGradeHistory.payLevel',
                    'profileExtra',
                ])
                ->where(function ($query) use ($user) {
                    $query->where('user_id', (int) $user->id);

                    if (!empty($user->email)) {
                        $query->orWhere('email', (string) $user->email);
                    }
                })
                ->orderByRaw('CASE WHEN user_id = ? THEN 0 ELSE 1 END', [(int) $user->id])
                ->first();
        }

        $roles = $user->getRoleNames()->values()->all();

        if (!$employee) {
            return [
                'id' => (int) $user->id,
                'user_id' => (int) $user->id,
                'auth_user_id' => (int) $user->id,
                'full_name' => (string) ($user->full_name ?? ''),
                'name' => (string) ($user->full_name ?? ''),
                'email' => (string) ($user->email ?? ''),
                'user_type_id' => (int) ($user->user_type_id ?? 0),
                'profile_pic' => $this->normalizeProfileImagePath($user->profile_image),
                'roles' => $roles,
                'role' => $roles[0] ?? null,
            ];
        }

        $profilePic = $employee->profile_image ?: $user->profile_image;
        $displayName = trim((string) ($employee->full_name ?: $user->full_name));
        $phone = $this->normalizeText($employee->phone)
            ?? $this->normalizeText($employee->cell_phone)
            ?? $this->normalizeText($user->contact_no);
        $positionDisplay = $this->normalizeText($employee->position?->position_name_km)
            ?? $this->normalizeText($employee->position?->position_name);
        $unitDisplay = $this->normalizeText($employee->sub_department?->department_name)
            ?? $this->normalizeText($employee->department?->department_name);
        $workStatus = $this->normalizeText($employee->work_status_name);
        $skillDisplay = $this->resolveEmployeeSkill($employee);
        $payLevelDisplay = $this->resolveEmployeePayLevel($employee);
        $statusDisplay = $this->resolveEmployeeStatus($employee);

        return [
            'id' => (int) $employee->id,
            'user_id' => (int) $user->id,
            'auth_user_id' => (int) $user->id,
            'full_name' => $displayName,
            'name' => $displayName,
            'first_name' => $this->normalizeText($employee->first_name),
            'middle_name' => $this->normalizeText($employee->middle_name),
            'last_name' => $this->normalizeText($employee->last_name),
            'first_name_latin' => $this->normalizeText($employee->first_name_latin),
            'last_name_latin' => $this->normalizeText($employee->last_name_latin),
            'full_name_latin' => $this->normalizeText($employee->full_name_latin),
            'email' => $this->normalizeText($employee->email) ?? (string) $user->email,
            'user_type_id' => (int) ($user->user_type_id ?? 0),
            'employee_id' => $this->normalizeText($employee->employee_id),
            'employee_code' => $this->normalizeText($employee->employee_code),
            'card_no' => $this->normalizeText($employee->card_no),
            'official_id_10' => $this->normalizeText($employee->official_id_10),
            'official_id' => $this->normalizeText($employee->official_id_10),
            'profile_pic' => $this->normalizeProfileImagePath($profilePic),
            'token_id' => $this->normalizeText($user->token_id),
            'roles' => $roles,
            'role' => $roles[0] ?? null,

            // Organization
            'department_id' => $employee->department_id ? (int) $employee->department_id : null,
            'department_name' => $this->normalizeText($employee->department?->department_name),
            'sub_department_id' => $employee->sub_department_id ? (int) $employee->sub_department_id : null,
            'sub_department_name' => $this->normalizeText($employee->sub_department?->department_name),
            'unit_name' => $unitDisplay,
            'position_id' => $employee->position_id ? (int) $employee->position_id : null,
            'position_name' => $this->normalizeText($employee->position?->position_name),
            'position_name_km' => $this->normalizeText($employee->position?->position_name_km),
            'role_display' => $positionDisplay,
            'employee_type_id' => $employee->employee_type_id ? (int) $employee->employee_type_id : null,
            'employee_type_name' => $this->normalizeText($employee->employee_type?->name),
            'employee_type_name_km' => $this->normalizeText($employee->employee_type?->name_km),

            // Personal
            'phone' => $phone,
            'mobile_no' => $phone,
            'alternate_phone' => $this->normalizeText($employee->alternate_phone),
            'date_of_birth' => $this->normalizeText($employee->date_of_birth),
            'gender_id' => $employee->gender_id ? (int) $employee->gender_id : null,
            'gender_name' => $this->normalizeText($employee->gender?->gender_name),
            'gender_display' => $this->resolveEmployeeGenderDisplay($employee),
            'marital_status_id' => $employee->marital_status_id ? (int) $employee->marital_status_id : null,
            'marital_status_name' => $this->normalizeText($employee->marital_status?->name)
                ?? $this->normalizeText($employee->marital_status?->marital_status_name),
            'nationality' => $this->normalizeText($employee->nationality) ?? $this->normalizeText($employee->citizenship),
            'religion' => $this->normalizeText($employee->religion),
            'ethnic_group' => $this->normalizeText($employee->ethnic_group),
            'present_address' => $this->normalizeText($employee->present_address) ?? $this->normalizeText($employee->present_address_address),
            'permanent_address' => $this->normalizeText($employee->permanent_address) ?? $this->normalizeText($employee->permanent_address_address),

            // Identity
            'national_id_no' => $this->normalizeText($employee->national_id_no),
            'national_id' => $this->normalizeText($employee->national_id),
            'passport_no' => $this->normalizeText($employee->passport_no),
            'driving_license_no' => $this->normalizeText($employee->driving_license_no),
            'legal_document_type' => $this->normalizeText($employee->legal_document_type),
            'legal_document_number' => $this->normalizeText($employee->legal_document_number),

            // Work timeline
            'joining_date' => $this->normalizeText($employee->joining_date),
            'hire_date' => $this->normalizeText($employee->hire_date),
            'service_start_date' => $this->normalizeText($employee->service_start_date),
            'contract_start_date' => $this->normalizeText($employee->contract_start_date),
            'contract_end_date' => $this->normalizeText($employee->contract_end_date),
            'full_right_date' => $this->normalizeText($employee->full_right_date),
            'is_full_right_officer' => $employee->is_full_right_officer !== null ? (int) $employee->is_full_right_officer : null,
            'work_status_name' => $this->normalizeText($employee->work_status_name),
            'work_status' => $workStatus,
            'service_state' => $this->normalizeText($employee->service_state),
            'employee_grade' => $this->normalizeText($employee->employee_grade),
            'skill_name' => $this->normalizeText($employee->skill_name),
            'current_work_skill' => $this->normalizeText($employee->skill_name),
            'skill' => $skillDisplay,
            'pay_level' => $payLevelDisplay,
            'status' => $statusDisplay,
        ];
    }

    private function resolveEmployeeSkill(Employee $employee): ?string
    {
        $skill = $this->normalizeText($employee->skill_name)
            ?? $this->normalizeText($employee->profileExtra?->current_work_skill)
            ?? (property_exists($employee, 'current_work_skill') ? $this->normalizeText($employee->current_work_skill) : null);

        return $skill;
    }

    private function resolveEmployeePayLevel(Employee $employee): ?string
    {
        $current = $employee->currentPayGradeHistory?->payLevel;
        if ($current) {
            $km = $this->normalizeText($current->level_name_km);
            if ($km !== null) {
                return $km;
            }

            $code = $this->normalizeText($current->level_code);
            if ($code !== null) {
                return $code;
            }
        }

        $latest = $employee->latestPayGradeHistory?->payLevel;
        if ($latest) {
            $km = $this->normalizeText($latest->level_name_km);
            if ($km !== null) {
                return $km;
            }

            $code = $this->normalizeText($latest->level_code);
            if ($code !== null) {
                return $code;
            }
        }

        return $this->normalizeText($employee->employee_grade);
    }

    private function resolveEmployeeStatus(Employee $employee): string
    {
        $serviceState = strtolower((string) ($employee->service_state ?? ''));
        if ($serviceState === 'suspended') {
            return 'suspended';
        }

        return ((int) ($employee->is_active ?? 0) === 1) ? 'active' : 'inactive';
    }

    private function resolveEmployeeGenderDisplay(Employee $employee): ?string
    {
        $gender = mb_strtolower(trim((string) ($employee->gender?->gender_name ?? '')));
        if (in_array($gender, ['male', 'm', 'ប្រុស'], true)) {
            return localize('male');
        }
        if (in_array($gender, ['female', 'f', 'ស្រី'], true)) {
            return localize('female');
        }

        return $this->normalizeText($employee->gender?->gender_name);
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function normalizeProfileImagePath(mixed $value): ?string
    {
        $path = $this->normalizeText($value);
        if ($path === null) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'storage/')) {
            return $path;
        }

        return 'storage/' . ltrim($path, '/');
    }
}
