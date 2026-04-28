<form id="editUserForm" class="validateEditForm" action="{{ route('role.user.update', $user->id) }}" method="POST"
    enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="id" id="id" value="{{ $user->id }}">
    <div class="modal-body">
        <div class="row">
            <div class="col-md-12 mt-3">
                <div class="row">
                    <label for="full_name"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('full_name') }}
                        <span class="text-danger">*</span></label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <input type="text" class="form-control" id="full_name" name="full_name"
                            value="{{ $user->full_name }}">
                        <span class="text-danger error_full_name"></span>
                    </div>
                </div>
            </div>
            <div class="col-md-12 mt-3">
                <div class="row">
                    <label for="email"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('email') }}<span
                            class="text-danger">*</span></label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <input type="text" class="form-control" id="email" name="email"
                            value="{{ $user->email }}">
                        <span class="text-danger error_email"></span>
                    </div>

                </div>
            </div>
            <div class="col-md-12 mt-3">
                <div class="row">
                    <label for="contact_no"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('mobile') }}<span
                            class="text-danger">*</span></label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <input type="text" class="form-control" id="contact_no" name="contact_no"
                            value="{{ $user->contact_no }}">
                        <span class="text-danger error_contact_no"></span>
                    </div>

                </div>
            </div>
            <div class="col-md-12 text-start">
                <div class="row mt-3">
                    <label for="role_id"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('role') }}<span
                            class="text-danger">*</span></label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <select name="role_id[]" id="role_id" multiple class="form-control select-basic-single">
                            @foreach ($roleList as $roleValue)
                                <option value="{{ $roleValue->id }}" @if (in_array($roleValue->id, $user->userRole->pluck('id')->toArray())) selected @endif>
                                    {{ $roleValue->name }}</option>
                            @endforeach
                        </select>
                        <span class="text-danger error_role_id"></span>
                    </div>

                </div>
            </div>
            <div class="col-md-12 mb-3">
                <div class="row">
                    <label for="user_type_id"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('user_type') }}<span
                            class="text-danger">*</span></label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <select name="user_type_id" id="user_type_id" class="form-control select-basic-single">
                            @foreach ($userTypes as $item)
                                <option value="{{ $item->id }}" @selected($item->id == $user->user_type_id)>{{ $item->user_type_title }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="col-md-12 mt-3">
                <div class="row">
                    <label for="employee_id"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('employee', 'Employee') }}</label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <select name="employee_id" id="employee_id" class="form-control select-basic-single">
                            <option value="">{{ localize('not_linked', 'Not linked') }}</option>
                            @foreach ($employeeOptions as $employee)
                                @php
                                    $displayName = trim((string) ($employee->full_name ?? ''))
                                        ?: trim((string) ($employee->first_name ?? '') . ' ' . (string) ($employee->last_name ?? ''))
                                        ?: trim((string) ($employee->maiden_name ?? ''))
                                        ?: trim((string) ($employee->first_name_latin ?? '') . ' ' . (string) ($employee->last_name_latin ?? ''))
                                        ?: (trim((string) $employee->email) ?: ('Employee #' . $employee->id));
                                    $employeeNumber = trim((string) ($employee->employee_id ?? ''));
                                    $employeeEmail = trim((string) ($employee->email ?? ''));
                                    $employeePhone = trim((string) ($employee->phone ?? ''))
                                        ?: trim((string) ($employee->cell_phone ?? ''));
                                    $isLinkedElsewhere = !empty($employee->user_id) && (int) $employee->user_id !== (int) $user->id;
                                @endphp
                                <option value="{{ $employee->id }}"
                                    data-full-name="{{ $displayName }}"
                                    data-email="{{ $employeeEmail }}"
                                    data-phone="{{ $employeePhone }}"
                                    @disabled($isLinkedElsewhere)
                                    @selected((int) ($linkedEmployee->id ?? 0) === (int) $employee->id)>
                                    {{ $employeeNumber !== '' ? $employeeNumber . ' - ' : '' }}{{ $displayName }}{{ $isLinkedElsewhere ? ' (Linked)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">{{ localize('optional', 'Optional') }}</small>
                        <span class="text-danger error_employee_id"></span>
                    </div>
                </div>
            </div>
            <div class="col-md-12 mt-3">
                <div class="row">
                    <label for="password"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('password') }}</label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <input type="password" class="form-control" id="password" name="password">
                        <span class="text-danger error_password"></span>
                    </div>

                </div>
            </div>
            <div class="col-md-12 mt-3">
                <div class="row">
                    <label for="profile_image"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('profile_image') }}</label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <input type="file" class="form-control" id="profile_image" name="profile_image">
                        <span class="text-danger error_profile_image"></span>
                    </div>

                </div>
            </div>
            <div class="col-md-12 text-start">
                <div class="row mt-3">
                    <label for="status"
                        class="col-form-label col-sm-3 col-md-12 col-xl-3 fw-semibold">{{ localize('status') }}<span
                            class="text-danger">*</span></label>
                    <div class="col-sm-9 col-md-12 col-xl-9">
                        <select name="status" id="status" class="form-control" required>
                            <option value="1" @if ($user->is_active == 1) selected @endif>
                                {{ localize('active') }}</option>
                            <option value="0" @if ($user->is_active == 0) selected @endif>
                                {{ localize('inactive') }}</option>

                        </select>
                        <span class="text-danger error_status"></span>
                    </div>
                </div>
            </div>

            <div class="col-md-12 mt-4">
                <hr class="my-0">
            </div>

            <div class="col-md-12 mt-3">
                <h6 class="fw-semibold mb-1">{{ localize('mobile_device_management', 'Mobile Device Management') }}</h6>
                <p class="text-muted small mb-2">
                    {{ localize('manage_user_devices_from_here', 'Register and control this user devices directly from the user form.') }}
                </p>
            </div>

            <div class="col-md-12">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold mb-1">{{ localize('device_id', 'Device ID') }} <span class="text-danger">*</span></label>
                        <input type="text" id="device_create_id" class="form-control form-control-sm" placeholder="ANDROID_ID / UUID">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold mb-1">{{ localize('device_name', 'Device Name') }}</label>
                        <input type="text" id="device_create_name" class="form-control form-control-sm" placeholder="Samsung A15">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold mb-1">{{ localize('platform', 'Platform') }}</label>
                        <select id="device_create_platform" class="form-control form-control-sm">
                            <option value="">--</option>
                            <option value="android">android</option>
                            <option value="ios">ios</option>
                            <option value="web">web</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold mb-1">{{ localize('status', 'Status') }}</label>
                        <select id="device_create_status" class="form-control form-control-sm">
                            <option value="pending">pending</option>
                            <option value="active">active</option>
                            <option value="blocked">blocked</option>
                            <option value="rejected">rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold mb-1">IMEI</label>
                        <input type="text" id="device_create_imei" class="form-control form-control-sm" placeholder="Optional">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold mb-1">Fingerprint</label>
                        <input type="text" id="device_create_fingerprint" class="form-control form-control-sm" placeholder="Optional">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-semibold mb-1">{{ localize('rejection_reason', 'Rejection Reason') }}</label>
                        <input type="text" id="device_create_reason" class="form-control form-control-sm" placeholder="{{ localize('optional', 'Optional') }}">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="button"
                            class="btn btn-primary btn-sm user-device-create-btn"
                            data-user-id="{{ $user->id }}"
                            data-url="{{ route('role.user.device.store', $user->id) }}">
                            {{ localize('add_device', 'Add Device') }}
                        </button>
                    </div>
                    <div class="col-md-12">
                        <span class="text-danger small device_error"></span>
                    </div>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ localize('device_id', 'Device ID') }}</th>
                                <th>{{ localize('device_name', 'Device Name') }}</th>
                                <th>{{ localize('platform', 'Platform') }}</th>
                                <th>IMEI</th>
                                <th>{{ localize('status', 'Status') }}</th>
                                <th>{{ localize('last_login', 'Last Login') }}</th>
                                <th>{{ localize('action', 'Action') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($mobileDevices as $device)
                                <tr>
                                    <td><code class="small">{{ $device->device_id }}</code></td>
                                    <td>{{ $device->device_name ?? '-' }}</td>
                                    <td>{{ $device->platform ?? '-' }}</td>
                                    <td><small>{{ $device->imei ?? '-' }}</small></td>
                                    <td>
                                        @php
                                            $statusClass = 'secondary';
                                            if ($device->status === 'active') $statusClass = 'success';
                                            elseif ($device->status === 'pending') $statusClass = 'warning';
                                            elseif ($device->status === 'blocked') $statusClass = 'danger';
                                        @endphp
                                        <span class="badge bg-{{ $statusClass }}">{{ $device->status }}</span>
                                        @if($device->status === 'rejected' && $device->rejection_reason)
                                            <div class="small text-danger mt-1">{{ $device->rejection_reason }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <small>{{ optional($device->last_login_at)->format('Y-m-d H:i') ?? '-' }}</small>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 flex-wrap">
                                            @if(!$device->isActive())
                                                <button type="button"
                                                    class="btn btn-success-soft btn-sm user-device-status-btn"
                                                    data-url="{{ route('role.user.device.status', $device->id) }}"
                                                    data-user-id="{{ $user->id }}"
                                                    data-status="active">
                                                    {{ localize('approve', 'Approve') }}
                                                </button>
                                            @endif

                                            @if(!$device->isBlocked())
                                                <button type="button"
                                                    class="btn btn-warning-soft btn-sm user-device-status-btn"
                                                    data-url="{{ route('role.user.device.status', $device->id) }}"
                                                    data-user-id="{{ $user->id }}"
                                                    data-status="blocked">
                                                    {{ localize('block', 'Block') }}
                                                </button>
                                            @endif

                                            @if(!$device->isRejected())
                                                <button type="button"
                                                    class="btn btn-danger-soft btn-sm user-device-status-btn"
                                                    data-url="{{ route('role.user.device.status', $device->id) }}"
                                                    data-user-id="{{ $user->id }}"
                                                    data-status="rejected"
                                                    data-requires-reason="1">
                                                    {{ localize('reject', 'Reject') }}
                                                </button>
                                            @endif

                                            @if(!$device->isPending())
                                                <button type="button"
                                                    class="btn btn-info-soft btn-sm user-device-status-btn"
                                                    data-url="{{ route('role.user.device.status', $device->id) }}"
                                                    data-user-id="{{ $user->id }}"
                                                    data-status="pending">
                                                    {{ localize('set_pending', 'Set Pending') }}
                                                </button>
                                            @endif

                                            <button type="button"
                                                class="btn btn-danger-soft btn-sm user-device-delete-btn"
                                                data-url="{{ route('role.user.device.delete', $device->id) }}"
                                                data-user-id="{{ $user->id }}">
                                                {{ localize('delete', 'Delete') }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">
                                        {{ localize('no_devices_found', 'No devices found for this user.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">{{ localize('close') }}</button>
        <button class="btn btn-success">{{ localize('update') }}</button>
    </div>
</form>
@php
    $userEditScriptVersion = @filemtime(public_path('module-assets/UserManagement/js/userEdit.js')) ?: time();
@endphp
<script src="{{ module_asset('UserManagement/js/userEdit.js') }}&t={{ $userEditScriptVersion }}"></script>
